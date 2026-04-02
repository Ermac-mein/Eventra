<?php

/**
 * Get Order API
 * Returns order details + ticket info by payment reference.
 * Used by the frontend callback page after Paystack redirect.
 */

// Enable full error reporting for debugging Phase
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');
require_once '../../config/database.php';
require_once '../../includes/middleware/auth.php';

/**
 * Helper: Resolve users.id from auth_id (auth_accounts.id or session users.id)
 */
function resolveUserId($pdo, $auth_id)
{
    // Try auth_accounts.id → users.user_auth_id
    $stmt = $pdo->prepare("SELECT id FROM users WHERE user_auth_id = ? LIMIT 1");
    $stmt->execute([$auth_id]);
    $user = $stmt->fetch();
    if ($user)
        return $user['id'];

    // Fallback: direct users.id (session-based)
    $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$auth_id]);
    return $stmt->fetch() ? $auth_id : null;
}

try {
    $auth_id = checkAuth('user');

    // Input validation & sanitation
    $reference = htmlspecialchars(trim($_GET['reference'] ?? ''));
    if (empty($reference)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'reference is required.']);
        exit;
    }

    // Resolve user_id dynamically from auth_id (handles both auth_accounts.id and users.id from session)
    $resolved_user_id = resolveUserId($pdo, $auth_id);
    if (!$resolved_user_id) {
        error_log("[get-order.php] User profile not found for auth_id: $auth_id");
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'User profile not found. Please complete registration/login.']);
        exit;
    }

    /**
     * Fetch order (must belong to this user)
     * We use LEFT JOIN for payments and tickets to handle the case where 
     * Paystack redirection happens before the background webhook/verification creates these entries.
     */
    $stmt = $pdo->prepare("
        SELECT 
            o.id, o.event_id, o.amount, o.payment_status, o.refund_status,
            o.transaction_reference, o.created_at,
            e.event_name, e.event_date, e.event_time, e.location, e.address, e.image_path,
            t.barcode, t.qr_code_path, t.status AS ticket_status
        FROM orders o
        INNER JOIN events e ON o.event_id = e.id
        LEFT JOIN payments p ON p.reference = o.transaction_reference COLLATE utf8mb4_unicode_ci
        LEFT JOIN tickets t ON (t.payment_id = p.id OR t.order_id = o.id)
        WHERE o.transaction_reference = ?
          AND o.user_id = ?
        LIMIT 1
    ");
    $stmt->execute([$reference, $resolved_user_id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        error_log("[get-order.php] Order not found for reference: $reference (for user_id: $resolved_user_id)");
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Order not found. Please verify your reference.']);
        exit;
    }

    // Build ticket download URL if ticket exists
    $downloadUrl = null;
    if (!empty($order['barcode'])) {
        $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $downloadUrl = "{$protocol}://{$host}/api/tickets/download-ticket.php?code=" . urlencode($order['barcode']);
    }

    // Determine safe status
    $status = $order['payment_status'];
    if (empty($order['barcode']) && $status === 'success') {
        // Logically inconsistent state: marked as success but no ticket generated yet
        $status = 'pending';
    }

    echo json_encode([
        'success' => true,
        'status' => $status,
        'order' => [
            'id' => (int) $order['id'],
            'event_id' => (int) $order['event_id'],
            'event_name' => $order['event_name'],
            'event_date' => $order['event_date'],
            'event_time' => $order['event_time'],
            'location' => $order['location'] ?? $order['address'],
            'image_path' => $order['image_path'],
            'amount' => (float) $order['amount'],
            'payment_status' => $order['payment_status'],
            'refund_status' => $order['refund_status'],
            'transaction_reference' => $order['transaction_reference'],
            'created_at' => $order['created_at'],
            'ticket' => $order['barcode'] ? [
                'barcode' => $order['barcode'],
                'status' => $order['ticket_status'],
                'download_url' => $downloadUrl,
            ] : null,
        ],
    ]);

} catch (PDOException $e) {
    error_log('[get-order.php] SQL/DB error: ' . $e->getMessage());

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error occurred while retrieving your order.',
        'debug' => (ini_get('display_errors') == '1') ? $e->getMessage() : null
    ]);
} catch (Exception $e) {
    error_log('[get-order.php] General error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'An unexpected error occurred.']);
}

