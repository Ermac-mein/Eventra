<?php
/**
 * Delete Event API
 * Handles event deletion with admin notification
 */
header('Content-Type: application/json');
require_once '../../config/database.php';
require_once '../utils/notification-helper.php';
require_once '../../includes/middleware/auth.php';

// Check authentication and role (client or admin)
$user_id = checkAuth();
$user_role = $_SESSION['role'];

$data = json_decode(file_get_contents("php://input"), true);
$event_id = $data['event_id'] ?? null;

if (!$event_id) {
    echo json_encode(['success' => false, 'message' => 'Event ID is required']);
    exit;
}

try {
    // Get event details before deletion
    $stmt = $pdo->prepare("SELECT event_name, client_id FROM events WHERE id = ?");
    $stmt->execute([$event_id]);
    $event = $stmt->fetch();

    if (!$event) {
        echo json_encode(['success' => false, 'message' => 'Event not found']);
        exit;
    }

    // Check permissions (client can only delete their own events, admin can delete any)
    if ($user_role === 'client' && $event['client_id'] != $user_id) {
        echo json_encode(['success' => false, 'message' => 'You do not have permission to delete this event']);
        exit;
    }

    // Delete the event
    $stmt = $pdo->prepare("DELETE FROM events WHERE id = ?");
    $stmt->execute([$event_id]);

    // Get user name for notification
    $stmt = $pdo->prepare("SELECT name FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    $user_name = $user['name'] ?? 'Unknown User';

    // Notify admin about event deletion
    $admin_id = getAdminUserId();
    if ($admin_id && $admin_id != $user_id) {
        createEventDeletedNotification($admin_id, $event['event_name'], $user_name);
    }

    echo json_encode([
        'success' => true,
        'message' => 'Event deleted successfully'
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>