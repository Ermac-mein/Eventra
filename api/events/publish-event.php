<?php
/**
 * Publish Event API
 * Publishes a scheduled or draft event
 */
header('Content-Type: application/json');
require_once '../../config/database.php';

// Check authentication
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);
$event_id = $data['event_id'] ?? null;
$user_id = $_SESSION['user_id'];

if (!$event_id) {
    echo json_encode(['success' => false, 'message' => 'Event ID is required']);
    exit;
}

try {
    // Get event details
    $stmt = $pdo->prepare("SELECT * FROM events WHERE id = ?");
    $stmt->execute([$event_id]);
    $event = $stmt->fetch();

    if (!$event) {
        echo json_encode(['success' => false, 'message' => 'Event not found']);
        exit;
    }

    // Check if user is the client who created the event or admin
    if ($_SESSION['role'] !== 'admin' && $event['client_id'] != $user_id) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'You do not have permission to publish this event']);
        exit;
    }

    // Update event status to published
    $stmt = $pdo->prepare("UPDATE events SET status = 'published' WHERE id = ?");
    $stmt->execute([$event_id]);

    // Create notification for client
    $message = "Your event '{$event['event_name']}' has been published and is now live!";
    $stmt = $pdo->prepare("INSERT INTO notifications (recipient_id, sender_id, message, type) VALUES (?, ?, ?, ?)");
    $stmt->execute([$event['client_id'], $user_id, $message, 'event_published']);

    // Create notification for admin if client published
    if ($_SESSION['role'] === 'client') {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE role = 'admin' LIMIT 1");
        $stmt->execute();
        $admin = $stmt->fetch();

        if ($admin) {
            $message = "Event '{$event['event_name']}' has been published";
            $stmt = $pdo->prepare("INSERT INTO notifications (recipient_id, sender_id, message, type) VALUES (?, ?, ?, ?)");
            $stmt->execute([$admin['id'], $user_id, $message, 'event_published']);
        }
    }

    echo json_encode([
        'success' => true,
        'message' => 'Event published successfully'
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>