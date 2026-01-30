<?php
/**
 * Delete Media API
 * Deletes a media file
 */
header('Content-Type: application/json');
require_once '../../config/database.php';

// Check authentication
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'client') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);
$media_id = $data['media_id'] ?? null;
$client_id = $_SESSION['user_id'];

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

    // Delete physical file
    $file_path = '../../' . ltrim($media['file_path'], '/');
    if (file_exists($file_path)) {
        unlink($file_path);
    }

    // Delete from database
    $stmt = $pdo->prepare("DELETE FROM media WHERE id = ?");
    $stmt->execute([$media_id]);

    echo json_encode([
        'success' => true,
        'message' => 'Media deleted successfully'
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>