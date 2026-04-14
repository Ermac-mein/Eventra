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
    // 1. Auto-create 'Event Assets' folder and Sync Logic
    // ──────────────────────────────────────────────────────────────────────────
    $check_folder = $pdo->prepare("SELECT id FROM media_folders WHERE client_id = ? AND name = 'Event Assets' AND is_deleted = 0");
    $check_folder->execute([$client_id]);
    $folder_row = $check_folder->fetch();

    if (!$folder_row) {
        $pdo->prepare("INSERT INTO media_folders (client_id, name, created_at) VALUES (?, 'Event Assets', NOW())")
            ->execute([$client_id]);
        $event_assets_folder_id = $pdo->lastInsertId();
    } else {
        $event_assets_folder_id = $folder_row['id'];
    }

    // Sync files from events table
    $event_images = $pdo->prepare("SELECT DISTINCT image_path, event_name FROM events WHERE client_id = ? AND image_path IS NOT NULL AND image_path != '' AND deleted_at IS NULL");
    $event_images->execute([$client_id]);
    $images = $event_images->fetchAll();

    foreach ($images as $img) {
        $path = $img['image_path'];
        $name = $img['event_name'] . ' flyer';

        // Check if already in media
        $check_media = $pdo->prepare("SELECT id FROM media WHERE client_id = ? AND file_path = ? AND is_deleted = 0");
        $check_media->execute([$client_id, $path]);

        if (!$check_media->fetch()) {
            // Add to media table
            $ext = pathinfo($path, PATHINFO_EXTENSION);
            $pdo->prepare("INSERT INTO media (client_id, folder_id, file_name, file_path, file_size, file_type, uploaded_at) VALUES (?, ?, ?, ?, 0, ?, NOW())")
                ->execute([$client_id, $event_assets_folder_id, $name, $path, $ext ?: 'image']);
        }
    }
    // ──────────────────────────────────────────────────────────────────────────

    $folder_id = $_GET['folder_id'] ?? null;
    $file_type = $_GET['file_type'] ?? null;
    $status = $_GET['status'] ?? 'active';

    $is_trash = ($status === 'trash' ? 1 : 0);

    // Build query for current view (files)
    $where_clauses = ["client_id = ?", "is_deleted = ?"];
    $params = [$client_id, $is_trash];

    if ($folder_id) {
        $where_clauses[] = "folder_id = ?";
        $params[] = $folder_id;
    } else {
        $where_clauses[] = "folder_id IS NULL";
    }

    if ($file_type) {
        $where_clauses[] = "file_type = ?";
        $params[] = $file_type;
    }

    $where_sql = implode(' AND ', $where_clauses);

    // 1. Get media files for current view
    $stmt = $pdo->prepare("
        SELECT id, file_name as name, file_path, file_size, file_type, folder_id 
        FROM media
        WHERE $where_sql
        ORDER BY uploaded_at DESC
    ");
    $stmt->execute($params);
    $media_files = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 2. Get folders for current view
    // Note: media_folders doesn't support nested folders (no parent_id column)
    // So we'll just return top-level folders for this client
    $folders_sql = "SELECT id, name, created_at FROM media_folders WHERE client_id = ? AND is_deleted = ?";
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
            (SELECT COUNT(*) FROM media_folders WHERE client_id = ? AND is_deleted = 1) as deleted_total
    ");
    $ds_stmt->execute([$client_id, $client_id, $client_id, $client_id, $client_id]);
    $ds = $ds_stmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'media' => array_merge($folders, $media_files),
        'stats' => [
            'total_folders' => (int)($ds['folders_total'] ?? 0),
            'total_files' => (int)($ds['files_total'] ?? 0),
            'total_size' => (float)($ds['storage_total'] ?? 0),
            'total_deleted' => (int)($ds['deleted_total'] ?? 0)
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
