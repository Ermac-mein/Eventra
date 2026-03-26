<?php

/**
 * Delete Media API
 * Deletes a media file
 */

header('Content-Type: application/json');
require_once '../../config/database.php';
require_once '../utils/notification-helper.php';

// Check authentication
require_once '../../includes/middleware/auth.php';
$client_id = clientMiddleware();

$data = json_decode(file_get_contents("php://input"), true);
$media_id = $data['media_id'] ?? null;

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
