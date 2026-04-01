<?php

/**
 * API: Cancel Ticket
 * Handles ticket cancellation and triggers notification
 */

header('Content-Type: application/json');
require_once '../../config/database.php';
require_once '../../includes/middleware/auth.php';
require_once '../utils/notification-helper.php';

// Check authentication
$user_id_auth = checkAuth('user');
$user_id = $_SESSION['user_id'] ?? null;

if (!$user_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}
$ticket_id = $_POST['ticket_id'] ?? null;

if (!$ticket_id) {
    echo json_encode(['success' => false, 'message' => 'Ticket ID is required']);
    exit;
}

try {
    $pdo->beginTransaction();

    // Check if ticket exists and belongs to user (or if admin)
    $stmt = $pdo->prepare("SELECT * FROM tickets WHERE id = ?");
    $stmt->execute([$ticket_id]);
    $ticket = $stmt->fetch();

    if (!$ticket) {
        throw new Exception("Ticket not found");
    }

    // Role isolation check
    if ($_SESSION['role'] !== 'admin' && $ticket['user_id'] != $user_id) {
        throw new Exception("Access denied. You do not own this ticket.");
    }

    // Update status to cancelled
    $stmt = $pdo->prepare("UPDATE tickets SET status = 'cancelled', updated_at = NOW() WHERE id = ?");
    $stmt->execute([$ticket_id]);

    // Trigger Notification
    createNotification($user_id, "Your ticket (ID: #$ticket_id) has been cancelled.", 'ticket_cancelled', $user_id);

    // Notify Admin too
    $admin_id = getAdminUserId();
    if ($admin_id) {
        createNotification($admin_id, "Ticket #$ticket_id has been cancelled by user.", 'ticket_cancelled', $user_id);
    }

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Ticket cancelled successfully'
    ]);
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
