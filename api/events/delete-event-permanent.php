<?php
/**
 * Permanently Delete Event API
 * Permanently removes an event from the database (hard delete)
 */
header('Content-Type: application/json');
require_once '../../config/database.php';
require_once '../../includes/middleware/auth.php';

// Check authentication (client or admin)
$user_id = checkAuth();
$user_role = $_SESSION['role'];

$data = json_decode(file_get_contents("php://input"), true);
$event_id = $data['event_id'] ?? null;

if (!$event_id) {
    echo json_encode(['success' => false, 'message' => 'Event ID is required']);
    exit;
}

try {
    // Get event details
    $stmt = $pdo->prepare("SELECT event_name, client_id, deleted_at FROM events WHERE id = ?");
    $stmt->execute([$event_id]);
    $event = $stmt->fetch();

    if (!$event) {
        echo json_encode(['success' => false, 'message' => 'Event not found']);
        exit;
    }

    if (!$event['deleted_at']) {
        echo json_encode(['success' => false, 'message' => 'Event must be in trash before permanent deletion']);
        exit;
    }

    // Resolve client_id if user is client
    $resolved_user_id = $user_id;
    if ($user_role === 'client') {
        $stmt = $pdo->prepare("SELECT id FROM clients WHERE auth_id = ?");
        $stmt->execute([$user_id]);
        $client = $stmt->fetch();
        if (!$client) {
            echo json_encode(['success' => false, 'message' => 'Client profile not found']);
            exit;
        }
        $resolved_user_id = $client['id'];
    }

    // Check permissions
    if ($user_role === 'client' && $event['client_id'] != $resolved_user_id) {
        echo json_encode(['success' => false, 'message' => 'You do not have permission to delete this event']);
        exit;
    }

    // Permanently delete the event (hard delete)
    $stmt = $pdo->prepare("DELETE FROM events WHERE id = ?");
    $stmt->execute([$event_id]);

    // Notify admin about permanent deletion if deleted by client
    if ($user_role === 'client') {
        $stmt = $pdo->prepare("SELECT business_name FROM clients WHERE auth_id = ?");
        $stmt->execute([$user_id]);
        $client_info = $stmt->fetch();
        $user_name = $client_info['business_name'] ?? 'A Client';

        $stmt = $pdo->prepare("SELECT id FROM auth_accounts WHERE role = 'admin' LIMIT 1");
        $stmt->execute();
        $admin = $stmt->fetch();

        if ($admin) {
            $message = "Event '{$event['event_name']}' has been permanently deleted by $user_name";
            $stmt = $pdo->prepare("INSERT INTO notifications (recipient_auth_id, sender_auth_id, message, type) VALUES (?, ?, ?, ?)");
            $stmt->execute([$admin['id'], $user_id, $message, 'event_deleted_permanent']);
        }
    }

    echo json_encode([
        'success' => true,
        'message' => 'Event permanently deleted'
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>