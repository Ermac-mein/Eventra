<?php
/**
 * Get Media API
 * Retrieves media files and folders for a client
 */
header('Content-Type: application/json');
require_once '../../config/database.php';

try {
    $client_id = $_GET['client_id'] ?? null;
    $folder_name = $_GET['folder_name'] ?? null;
    $file_type = $_GET['file_type'] ?? null;

    if (!$client_id) {
        echo json_encode(['success' => false, 'message' => 'Client ID is required']);
        exit;
    }

    // Build query
    $where_clauses = ["client_id = ?"];
    $params = [$client_id];

    // Folder filtering removed as column does not exist
    /*
    if ($folder_name) {
        $where_clauses[] = "folder_name = ?";
        $params[] = $folder_name;
    }
    */

    if ($file_type) {
        $where_clauses[] = "file_type = ?";
        $params[] = $file_type;
    }

    $where_sql = implode(' AND ', $where_clauses);

    // Get media files
    $stmt = $pdo->prepare("
        SELECT * FROM media
        WHERE $where_sql
        ORDER BY uploaded_at DESC
    ");
    $stmt->execute($params);
    $media = $stmt->fetchAll();

    // Get statistics
    // Removed total_folders count
    $stats_stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_files,
            SUM(file_size) as total_size,
            SUM(CASE WHEN file_type = 'image' THEN 1 ELSE 0 END) as total_images,
            SUM(CASE WHEN file_type = 'video' THEN 1 ELSE 0 END) as total_videos,
            SUM(CASE WHEN file_type = 'document' THEN 1 ELSE 0 END) as total_documents
        FROM media
        WHERE client_id = ?
    ");
    $stats_stmt->execute([$client_id]);
    $stats = $stats_stmt->fetch();

    // Get folder list - REMOVED
    $folders = [];
    /*
    $folders_stmt = $pdo->prepare("
        SELECT DISTINCT folder_name, COUNT(*) as file_count
        FROM media
        WHERE client_id = ?
        GROUP BY folder_name
    ");
    $folders_stmt->execute([$client_id]);
    $folders = $folders_stmt->fetchAll();
    */

    echo json_encode([
        'success' => true,
        'media' => $media,
        'stats' => $stats,
        'folders' => $folders
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>