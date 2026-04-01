<?php

/**
 * Get Order API
 * Returns order details + ticket info by payment reference.
 * Used by the frontend callback page after Paystack redirect.
 */

header('Content-Type: application/json');
require_once '../../config/database.php';
require_once '../../includes/middleware/auth.php';

$auth_id = checkAuth('user');

$reference = trim($_GET['reference'] ?? '');
if (empty($reference)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'reference is required.']);
    exit;
}

try {
    // Resolve actual users.id from auth_id (auth_accounts.id) — same pattern as verify-payment.php
    $userStmt = $pdo->prepare("SELECT id FROM users WHERE user_auth_id = ? LIMIT 1");
    $userStmt->execute([$auth_id]);
    $resolved_user_id = $userStmt->fetchColumn();

    if (!$resolved_user_id) {
        error_log("[get-order.php] User profile not found for auth account ID: $auth_id");
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'User profile not found. Please complete your registration.']);
        exit;
    }

    // Fetch order (must belong to this user)
    $stmt = $pdo->prepare("
        SELECT o.id, o.event_id, o.amount, o.payment_status, o.refund_status,
               o.transaction_reference, o.created_at,
               e.event_name, e.event_date, e.event_time, e.location, e.address, e.image_path,
               t.barcode, t.qr_code_path, t.status AS ticket_status
        FROM orders o
        JOIN events e ON o.event_id = e.id
        LEFT JOIN payments p ON p.reference = o.transaction_reference
        LEFT JOIN tickets t ON t.payment_id = p.id
        WHERE o.transaction_reference = ?
          AND o.user_id = ?
    ");
    $stmt->execute([$reference, $resolved_user_id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        error_log("[get-order.php] Order not found for reference: $reference (for user_id: $resolved_user_id)");
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Order not found for this reference.']);
        exit;
    }

    // Build ticket download URL if ticket exists
    $downloadUrl = null;
    if (!empty($order['barcode'])) {
        $protocol    = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
        $host        = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $downloadUrl = "{$protocol}://{$host}/api/tickets/download-ticket.php?code={$order['barcode']}";
    }

    echo json_encode([
        'success' => true,
        'order'   => [
            'id'                    => (int)$order['id'],
            'event_id'              => (int)$order['event_id'],
            'event_name'            => $order['event_name'],
            'event_date'            => $order['event_date'],
            'event_time'            => $order['event_time'],
            'location'              => $order['location'] ?? $order['address'],
            'image_path'            => $order['image_path'],
            'amount'                => (float)$order['amount'],
            'payment_status'        => $order['payment_status'],
            'refund_status'         => $order['refund_status'],
            'transaction_reference' => $order['transaction_reference'],
            'created_at'            => $order['created_at'],
            'ticket' => $order['barcode'] ? [
                'barcode'      => $order['barcode'],
                'status'       => $order['ticket_status'],
                'download_url' => $downloadUrl,
            ] : null,
        ],
    ]);
} catch (PDOException $e) {
    error_log('[get-order.php] DB error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to retrieve order.']);
}
