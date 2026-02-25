<?php
/**
 * Delete Folder API
 * Soft-deletes a folder and all its contents
 */
header('Content-Type: application/json');
require_once '../../config/database.php';
require_once '../utils/notification-helper.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'client') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);
$folder_id = $data['folder_id'] ?? null;
$user_id = $_SESSION['user_id'];

// Get the actual client_id from clients table
$stmt = $pdo->prepare("SELECT id FROM clients WHERE client_auth_id = ?");
$stmt->execute([$user_id]);
$client_id = $stmt->fetchColumn();

if (!$client_id) {
    echo json_encode(['success' => false, 'message' => 'Client profile not found']);
    exit;
}

if (!$folder_id) {
    echo json_encode(['success' => false, 'message' => 'Folder ID is required']);
    exit;
}

try {
    $pdo->beginTransaction();

    // Verify folder
    $stmt = $pdo->prepare("SELECT * FROM media_folders WHERE id = ? AND client_id = ?");
    $stmt->execute([$folder_id, $client_id]);
    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Folder not found']);
        exit;
    }

    // Soft delete folder
    $upd_folder = $pdo->prepare("UPDATE media_folders SET is_deleted = 1, deleted_at = NOW() WHERE id = ?");
    $upd_folder->execute([$folder_id]);

    // Soft delete contained media
    $upd_media = $pdo->prepare("UPDATE media SET is_deleted = 1, deleted_at = NOW() WHERE folder_id = ? AND client_id = ?");
    $upd_media->execute([$folder_id, $client_id]);

    $pdo->commit();

    // Trigger notification
    $stmt = $pdo->prepare("SELECT name FROM media_folders WHERE id = ?");
    $stmt->execute([$folder_id]);
    $folder_name = $stmt->fetchColumn();
    if ($folder_name) {
        createMediaDeletedNotification($client_id, $folder_name, 'folder');
    }

    echo json_encode([
        'success' => true,
        'message' => 'Folder and contents deleted successfully'
    ]);

} catch (PDOException $e) {
    if ($pdo->inTransaction())
        $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
