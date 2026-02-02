<?php
/**
 * Toggle Favorite API
 */
header('Content-Type: application/json');
require_once '../../config/database.php';
require_once '../../includes/middleware/auth.php';

$user_id = checkAuth();
$data = json_decode(file_get_contents("php://input"), true);

if (!isset($data['event_id'])) {
    echo json_encode(['success' => false, 'message' => 'Event ID is required']);
    exit;
}

$event_id = $data['event_id'];

try {
    // Check if already favorited
    $stmt = $pdo->prepare("SELECT id FROM favorites WHERE user_id = ? AND event_id = ?");
    $stmt->execute([$user_id, $event_id]);
    $favorite = $stmt->fetch();

    if ($favorite) {
        // Remove from favorites
        $stmt = $pdo->prepare("DELETE FROM favorites WHERE user_id = ? AND event_id = ?");
        $stmt->execute([$user_id, $event_id]);
        $is_favorite = false;
        $message = "Removed from favorites";
    } else {
        // Add to favorites
        $stmt = $pdo->prepare("INSERT INTO favorites (user_id, event_id) VALUES (?, ?)");
        $stmt->execute([$user_id, $event_id]);
        $is_favorite = true;
        $message = "Added to favorites";
    }

    echo json_encode([
        'success' => true,
        'message' => $message,
        'is_favorite' => $is_favorite
    ]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>