<?php
/**
 * Publish Event API
 * Publishes a scheduled or draft event
 */
header('Content-Type: application/json');
require_once '../../config/database.php';
require_once '../../includes/middleware/auth.php';

// Check authentication
$user_id = clientMiddleware();
$role = $_SESSION['role'] ?? 'client';

$data = json_decode(file_get_contents("php://input"), true);
$event_id = $data['event_id'] ?? null;
// checkAuth('client') returns the auth_account.id stored as client_id in session
$user_id = $_SESSION['client_id'] ?? $_SESSION['user_id'] ?? null;

if (!$event_id) {
    echo json_encode(['success' => false, 'message' => 'Event ID is required']);
    exit;
}

try {
    // Resolve client_id from auth_id
    $stmt = $pdo->prepare("SELECT id FROM clients WHERE client_auth_id = ?");
    $stmt->execute([$user_id]);
    $client = $stmt->fetch();

    if (!$client && $role !== 'admin') {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Client profile not found']);
        exit;
    }

    $resolved_client_id = $client ? $client['id'] : null;

    // Get event details
    $stmt = $pdo->prepare("SELECT * FROM events WHERE id = ?");
    $stmt->execute([$event_id]);
    $event = $stmt->fetch();

    if (!$event) {
        echo json_encode(['success' => false, 'message' => 'Event not found']);
        exit;
    }

    // Check if user is the client who created the event or admin
    if ($role !== 'admin' && $event['client_id'] != $resolved_client_id) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'You do not have permission to publish this event']);
        exit;
    }

    // Update event status to published
    $stmt = $pdo->prepare("UPDATE events SET status = 'published' WHERE id = ?");
    $stmt->execute([$event_id]);

    // Create notification for client
    // For notifications, we need the auth_id. If a client is publishing, it's their own $user_id.
    // However, if an admin publishes, we need the client's auth_id.
    $recipient_auth_id = $user_id; // Default to current user
    if ($_SESSION['role'] === 'admin') {
        // Find the client's auth_id from their client_id
        $stmt = $pdo->prepare("SELECT client_auth_id as auth_id FROM clients WHERE id = ?");
        $stmt->execute([$event['client_id']]);
        $client_auth = $stmt->fetch();
        if ($client_auth) {
            $recipient_auth_id = $client_auth['auth_id'];
        }
    }

    $message = "Your event '{$event['event_name']}' has been published and is now live!";
    $stmt = $pdo->prepare("INSERT INTO notifications (recipient_auth_id, sender_auth_id, message, type) VALUES (?, ?, ?, ?)");
    $stmt->execute([$recipient_auth_id, $user_id, $message, 'event_published']);

    // Create notification for admin if client published
    if ($_SESSION['role'] === 'client') {
        $stmt = $pdo->prepare("SELECT id FROM auth_accounts WHERE role = 'admin' LIMIT 1");
        $stmt->execute();
        $admin = $stmt->fetch();

        if ($admin) {
            $message = "Event '{$event['event_name']}' has been published by a client";
            $stmt = $pdo->prepare("INSERT INTO notifications (recipient_auth_id, sender_auth_id, message, type) VALUES (?, ?, ?, ?)");
            $stmt->execute([$admin['id'], $user_id, $message, 'event_published']);
        }
    }

    echo json_encode([
        'success' => true,
        'message' => 'Event published successfully'
    ]);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
