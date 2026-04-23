<?php

/**
 * Publish Event API
 * Publishes a scheduled or draft event
 */

// Buffer output so stray PHP warnings don't corrupt the JSON response
ob_start();

header('Content-Type: application/json');
require_once '../../config/database.php';
require_once '../../includes/middleware/auth.php';

// Check authentication
$user_id = clientMiddleware();
$role = $_SESSION['role'] ?? 'client';

$data = json_decode(file_get_contents("php://input"), true);
$event_id = $data['event_id'] ?? null;

if (!$event_id) {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Event ID is required']);
    exit;
}

try {
    // Use user_id directly (it is client_id from clientMiddleware)
    $resolved_client_id = $user_id;

    // Get event details with client auth id
    $stmt = $pdo->prepare("
        SELECT e.*, c.client_auth_id 
        FROM events e 
        JOIN clients c ON e.client_id = c.id 
        WHERE e.id = ?
    ");
    $stmt->execute([$event_id]);
    $event = $stmt->fetch();

    if (!$event) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Event not found']);
        exit;
    }

    // Check if user is the client who created the event or admin
    if ($role !== 'admin' && $event['client_id'] != $resolved_client_id) {
        ob_end_clean();
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'You do not have permission to publish this event']);
        exit;
    }

    // Begin transaction — DB change is only committed if everything succeeds
    $pdo->beginTransaction();

    // Update event status to published and ensure admin_status is approved
    $stmt = $pdo->prepare("UPDATE events SET status = 'published', admin_status = 'approved' WHERE id = ?");
    $stmt->execute([$event_id]);

    require_once '../utils/notification-helper.php';

    $message = "Your event '{$event['event_name']}' has been published and is now live!";
    $auth_id = $_SESSION['auth_id'];
    $client_auth_id = $event['client_auth_id'];

    createNotification($client_auth_id, $message, 'event_published', $auth_id, 'client', ($role === 'admin' ? 'admin' : 'client'));

    // Notify Admin
    $admin_id = getAdminUserId();
    if ($admin_id && $auth_id != $admin_id) {
        $admin_message = "Event '{$event['event_name']}' has been published.";
        createNotification($admin_id, $admin_message, 'event_published', $auth_id, 'admin', 'client');
    }

    // Commit only after notifications are sent successfully
    $pdo->commit();

    ob_end_clean();
    echo json_encode([
        'success' => true,
        'message' => 'Event published successfully'
    ]);
} catch (Throwable $e) {
    // Roll back the DB change so the event is NOT published on error
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    ob_end_clean();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
