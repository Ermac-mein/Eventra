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
$user_role = $_SESSION['role'] ?? $_SESSION['user_role'] ?? 'user';

$data = json_decode(file_get_contents("php://input"), true);
$event_id = $data['event_id'] ?? null;

if (!$event_id) {
    echo json_encode(['success' => false, 'message' => 'Event ID is required']);
    exit;
}

try {
    // Get event details before deletion
    $stmt = $pdo->prepare("
        SELECT e.event_name, e.client_id, c.client_auth_id 
        FROM events e 
        JOIN clients c ON e.client_id = c.id 
        WHERE e.id = ?
    ");
    $stmt->execute([$event_id]);
    $event = $stmt->fetch();

    if (!$event) {
        echo json_encode(['success' => false, 'message' => 'Event not found']);
        exit;
    }

    // Use user_id directly if role is client
    $resolved_user_id = $user_id;

    // Check permissions (client can only delete their own events, admin can delete any)
    if ($user_role === 'client' && $event['client_id'] != $resolved_user_id) {
        echo json_encode(['success' => false, 'message' => 'You do not have permission to delete this event']);
        exit;
    }

    // LOCKING: Prevent deletion if there are payments
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM payments WHERE event_id = ? AND status = 'paid'");
    $stmt->execute([$event_id]);
    $payment_count = $stmt->fetchColumn();

    if ($payment_count > 0) {
        echo json_encode(['success' => false, 'message' => 'This event cannot be deleted because tickets have already been sold (Payments found).']);
        exit;
    }

    // Soft delete the event (set deleted_at timestamp)
    $stmt = $pdo->prepare("UPDATE events SET deleted_at = NOW() WHERE id = ?");
    $stmt->execute([$event_id]);

    // Define metadata for notifications
    $metadata = ['event_id' => $event_id, 'event_name' => $event['event_name']];
    $auth_id = $_SESSION['auth_id'];

    // Send notifications for deletion activity
    if ($user_role === 'client') {
        // Client deleted their event - notify admin
        $stmt = $pdo->prepare("SELECT business_name FROM clients WHERE id = ?");
        $stmt->execute([$user_id]);
        $client_info = $stmt->fetch();
        $user_name = $client_info['business_name'] ?? 'A Client';

        $admin_id = getAdminUserId();
        if ($admin_id) {
            $message = "Event '{$event['event_name']}' has been deleted by $user_name";
            createNotification($admin_id, $message, 'event_deleted', $auth_id, 'admin', 'client', $metadata);
        }
    } else {
        // Admin deleted the event - notify the client owner
        $message = "Your event '{$event['event_name']}' has been moved to trash.";
        $client_auth_id = $event['client_auth_id'];
        createNotification($client_auth_id, $message, 'event_deleted', $auth_id, 'client', 'admin', $metadata);
    }

    echo json_encode([
        'success' => true,
        'message' => 'Event deleted successfully'
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
