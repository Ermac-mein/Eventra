<?php

/**
 * Review Refund API (Organizer)
 *
 * POST: { refund_request_id, action: 'approve'|'decline', note?: string }
 */

header('Content-Type: application/json');
require_once '../../config/database.php';
require_once '../../config/payment.php';
require_once '../../includes/middleware/auth.php';
require_once '../../api/utils/notification-helper.php';

define('PROCESS_REFUND_INCLUDED', true);
require_once __DIR__ . '/process-refund.php'; // Include processRefund()

$client_auth_id = clientMiddleware();

$body              = json_decode(file_get_contents('php://input'), true) ?? [];
$refundRequestId   = (int)($body['refund_request_id'] ?? 0);
$action            = trim($body['action'] ?? '');
$note              = trim($body['note'] ?? '');

if (!$refundRequestId || !in_array($action, ['approve', 'decline'], true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'refund_request_id and action (approve|decline) are required.']);
    exit;
}

try {
    if (!$client_auth_id) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Client not found.']);
        exit;
    }
    $client_id = $client_auth_id;

    $checkStmt = $pdo->prepare("
        SELECT rr.id, rr.user_id, rr.status AS rr_status,
               o.id AS order_id, o.organizer_id, o.refund_status, o.amount,
               e.event_name,
        WHERE rr.id = ? AND o.organizer_id = ?
    ");
    $checkStmt->execute([$refundRequestId, $client_id]);
    $rr = $checkStmt->fetch(PDO::FETCH_ASSOC);

    if (!$rr) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Refund request not found or not belongs to your events.']);
        exit;
    }

    if ($rr['rr_status'] !== 'pending') {
        echo json_encode(['success' => false, 'message' => 'This refund has already been reviewed.']);
        exit;
    }

    if ($action === 'approve') {
        // Delegate to processRefund (calls Paystack)
        $result = processRefund($pdo, $refundRequestId);
        echo json_encode($result);
    } elseif ($action === 'decline') {
        $pdo->prepare("
            UPDATE refund_requests
            SET status = 'declined', organizer_note = ?, processed_at = NOW()
            WHERE id = ?
        ")->execute([$note, $refundRequestId]);

        $pdo->prepare("
            UPDATE orders SET refund_status = 'declined', updated_at = NOW() WHERE id = ?
        ")->execute([$rr['order_id']]);

        // Notify buyer of decline
        createNotification(
            $rr['user_id'],
            "Your refund request for \"{$rr['event_name']}\" was declined by the organizer." .
            ($note ? " Note: {$note}" : ''),
            'refund_declined',
            $client_id,
            'user',
            'client'
        );

        echo json_encode(['success' => true, 'message' => 'Refund request declined.']);
    }
} catch (PDOException $e) {
    error_log('[review-refund.php] DB error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to process refund review.']);
}
