<?php
/**
 * Delete Media API
 * Deletes a media file
 */
header('Content-Type: application/json');
require_once '../../config/database.php';
require_once '../utils/notification-helper.php';

// Check authentication
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'client') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);
$media_id = $data['media_id'] ?? null;
$user_id = $_SESSION['user_id'];

// Get the actual client_id from clients table
$stmt = $pdo->prepare("SELECT id FROM clients WHERE client_auth_id = ?");
$stmt->execute([$user_id]);
$client_id = $stmt->fetchColumn();

if (!$client_id) {
    echo json_encode(['success' => false, 'message' => 'Client profile not found']);
    exit;
}

if (!$media_id) {
    echo json_encode(['success' => false, 'message' => 'Media ID is required']);
    exit;
}

try {
    // Get media details
    $stmt = $pdo->prepare("SELECT * FROM media WHERE id = ? AND client_id = ?");
    $stmt->execute([$media_id, $client_id]);
    $media = $stmt->fetch();

    if (!$media) {
        echo json_encode(['success' => false, 'message' => 'Media not found']);
        exit;
    }

    // Soft delete from database
    $stmt = $pdo->prepare("UPDATE media SET is_deleted = 1, deleted_at = NOW() WHERE id = ?");
    $stmt->execute([$media_id]);

    if ($stmt->rowCount() > 0) {
        // Create notification
        createMediaDeletedNotification($client_id, $media['file_name'], 'file');

        echo json_encode([
            'success' => true,
            'message' => 'Media deleted successfully'
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete media or already in trash']);
    }

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
