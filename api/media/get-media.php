<?php
/**
 * Get Media API
 * Retrieves media files and folders for a client
 */

// MUST be the first two lines — no whitespace, no BOM before <?php
require_once __DIR__ . '/../../config.php'; 
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/middleware/auth.php';

// Then immediately set JSON response header
header('Content-Type: application/json');

// Handle CORS preflight — must come before any logic
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

try {
    $client_id = checkAuth('client');  // checkAuth now returns client_id directly

    // ──────────────────────────────────────────────────────────────────────────
    // Media Retrieval Logic
    // ──────────────────────────────────────────────────────────────────────────

    $folder_id = $_GET['folder_id'] ?? null;
    $file_type = $_GET['file_type'] ?? null;
    $status = $_GET['status'] ?? 'active';

    $is_trash = ($status === 'trash' ? 1 : 0);

    // Build query for current view (files)
    $where_clauses = ["m.client_id = ?", "m.is_deleted = ?"];
    $params = [$client_id, $is_trash];

    if ($folder_id) {
        $where_clauses[] = "m.folder_id = ?";
        $params[] = $folder_id;
    } else {
        $where_clauses[] = "m.folder_id IS NULL";
    }

    if ($file_type) {
        $where_clauses[] = "m.file_type = ?";
        $params[] = $file_type;
    }

    $where_sql = implode(' AND ', $where_clauses);

    // 1. Get media files for current view
    $stmt = $pdo->prepare("
        SELECT m.id, m.file_name as name, m.file_path, m.file_size, m.file_type, m.folder_id, m.uploaded_at,
               COALESCE(e.event_name, 'Unassigned') as event_association
        FROM media m
        LEFT JOIN events e ON m.file_path = e.image_path
        WHERE $where_sql
        ORDER BY m.uploaded_at DESC
    ");
    $stmt->execute($params);
    $media_files = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 2. Get folders for current view
    // Note: media_folders doesn't support nested folders (no parent_id column)
    // So we'll just return top-level folders for this client
    $folders_sql = "SELECT id, name, created_at FROM media_folders WHERE client_id = ? AND is_deleted = ? AND name != 'Event Assets'";
    $f_params = [$client_id, $is_trash];

    $f_stmt = $pdo->prepare($folders_sql);
    $f_stmt->execute($f_params);
    $db_folders = $f_stmt->fetchAll(PDO::FETCH_ASSOC);

    $folders = [];
    foreach ($db_folders as $f) {
        $count_stmt = $pdo->prepare("SELECT COUNT(*) FROM media WHERE folder_id = ? AND is_deleted = ?");
        $count_stmt->execute([$f['id'], $is_trash]);
        $folders[] = [
            'id' => $f['id'],
            'type' => 'folder',
            'name' => $f['name'],
            'file_count' => (int)$count_stmt->fetchColumn(),
            'created_at' => $f['created_at']
        ];
    }

    // 3. Overall Dashboard Stats (Filtered by client)
    $ds_stmt = $pdo->prepare("
        SELECT 
            (SELECT COUNT(*) FROM media_folders WHERE client_id = ? AND is_deleted = 0) as folders_total,
            (SELECT COUNT(*) FROM media WHERE client_id = ? AND is_deleted = 0) as files_total,
            (SELECT SUM(file_size) FROM media WHERE client_id = ? AND is_deleted = 0) as storage_total,
            (SELECT COUNT(*) FROM media WHERE client_id = ? AND is_deleted = 1) + 
            (SELECT COUNT(*) FROM media_folders WHERE client_id = ? AND is_deleted = 1) as deleted_total,
            (SELECT COALESCE(SUM(restoration_count), 0) FROM media_folders WHERE client_id = ?) as restored_total
    ");
    $ds_stmt->execute([$client_id, $client_id, $client_id, $client_id, $client_id, $client_id]);
    $ds = $ds_stmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'media' => array_merge($folders, $media_files),
        'stats' => [
            'total_folders' => (int)($ds['folders_total'] ?? 0),
            'total_files' => (int)($ds['files_total'] ?? 0),
            'total_size' => (float)($ds['storage_total'] ?? 0),
            'total_deleted' => (int)($ds['deleted_total'] ?? 0),
            'total_restored' => (int)($ds['restored_total'] ?? 0)
        ],
        'folders' => $folders
    ]);
} catch (PDOException $e) {
    error_log("[Get Media DB Error] " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
} catch (Throwable $e) {
    error_log("[Get Media Global Error] " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Internal server error: ' . $e->getMessage()]);
}
