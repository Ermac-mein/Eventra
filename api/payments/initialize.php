<?php
/**
 * Initialize Payment API — Marketplace Checkout
 *
 * POST body: { event_id: int, quantity?: int }
 *
 * Flow:
 *  1. Validate event is published & organizer has a subaccount_code
 *  2. Create a pending order in the orders table
 *  3. Call Paystack /transaction/initialize with subaccount split
 *  4. Return { authorization_url, reference, order_id }
 */
header('Content-Type: application/json');
require_once '../../config/database.php';
require_once '../../config/payment.php';
require_once '../../includes/middleware/auth.php';

// Must be a logged-in user
$auth_id = checkAuth('user');

// ── Input ────────────────────────────────────────────────────────────────────
$body     = json_decode(file_get_contents('php://input'), true) ?? [];
$event_id = (int)($body['event_id'] ?? $_POST['event_id'] ?? 0);
$quantity = max(1, (int)($body['quantity'] ?? $_POST['quantity'] ?? 1));

if (!$event_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'event_id is required.']);
    exit;
}

try {
    // ── Fetch user profile ───────────────────────────────────────────────────
    $uStmt = $pdo->prepare("
        SELECT u.id AS user_id, u.name, a.email
        FROM users u
        JOIN auth_accounts a ON u.user_auth_id = a.id
        WHERE u.user_auth_id = ?
    ");
    $uStmt->execute([$auth_id]);
    $user = $uStmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'User profile not found.']);
        exit;
    }

    // ── Fetch event + organizer subaccount ─────────────────────────────────
    $eStmt = $pdo->prepare("
        SELECT e.id, e.event_name, e.price, e.status, e.max_capacity, e.attendee_count,
               e.client_id AS organizer_id,
               c.subaccount_code, c.verification_status
        FROM events e
        JOIN clients c ON e.client_id = c.id
        WHERE e.id = ? AND e.deleted_at IS NULL
    ");
    $eStmt->execute([$event_id]);
    $event = $eStmt->fetch(PDO::FETCH_ASSOC);

    if (!$event) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Event not found.']);
        exit;
    }

    if ($event['status'] !== 'published') {
        echo json_encode(['success' => false, 'message' => 'This event is not available for booking.']);
        exit;
    }

    if (empty($event['subaccount_code'])) {
        echo json_encode(['success' => false, 'message' => 'The organizer has not set up payment details yet. Please check back later.']);
        exit;
    }

    // Capacity check
    if ($event['max_capacity'] !== null) {
        $available = $event['max_capacity'] - $event['attendee_count'];
        if ($quantity > $available) {
            echo json_encode(['success' => false, 'message' => "Only {$available} ticket(s) remaining."]);
            exit;
        }
    }

    // ── Calculate amount ─────────────────────────────────────────────────────
    $unit_price  = (float)$event['price'];
    $total       = $unit_price * $quantity;
    $amount_kobo = (int)round($total * 100); // Paystack expects kobo

    if ($amount_kobo <= 0) {
        echo json_encode(['success' => false, 'message' => 'This is a free event. Ticket booking handled separately.']);
        exit;
    }

    // ── Generate unique reference ────────────────────────────────────────────
    $reference = 'EVT-' . $event_id . '-' . strtoupper(substr(uniqid(), -8));

    $pdo->beginTransaction();

    // ── Insert pending order ─────────────────────────────────────────────────
    $oStmt = $pdo->prepare("
        INSERT INTO orders
            (user_id, event_id, organizer_id, subaccount_code, amount, transaction_reference, payment_status)
        VALUES (?, ?, ?, ?, ?, ?, 'pending')
    ");
    $oStmt->execute([
        $user['user_id'],
        $event_id,
        $event['organizer_id'],
        $event['subaccount_code'],
        $total,
        $reference,
    ]);
    $order_id = $pdo->lastInsertId();

    // ── Initialize Paystack transaction ──────────────────────────────────────
    $protocol    = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
    $host        = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $callbackUrl = "{$protocol}://{$host}/public/pages/payment.html?reference={$reference}";

    $paystackPayload = [
        'email'         => $user['email'],
        'amount'        => $amount_kobo,
        'reference'     => $reference,
        'subaccount'    => $event['subaccount_code'],
        'bearer'        => 'subaccount',  // organizer bears Paystack fees
        'callback_url'  => $callbackUrl,
        'metadata'      => [
            'order_id'   => $order_id,
            'event_id'   => $event_id,
            'event_name' => $event['event_name'],
            'quantity'   => $quantity,
            'user_id'    => $user['user_id'],
            'user_name'  => $user['name'],
        ],
    ];

    $ch = curl_init('https://api.paystack.co/transaction/initialize');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($paystackPayload),
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . PAYSTACK_SECRET_KEY,
            'Content-Type: application/json',
            'Cache-Control: no-cache',
        ],
    ]);
    $response  = curl_exec($ch);
    $curlError = curl_error($ch);

    if ($curlError || !$response) {
        $pdo->rollBack();
        http_response_code(502);
        echo json_encode(['success' => false, 'message' => 'Could not reach payment gateway. Please try again.']);
        exit;
    }

    $psResult = json_decode($response, true);

    if (!($psResult['status'] ?? false)) {
        $pdo->rollBack();
        $errMsg = $psResult['message'] ?? 'Paystack initialization failed.';
        echo json_encode(['success' => false, 'message' => $errMsg]);
        exit;
    }

    $pdo->commit();

    echo json_encode([
        'success'           => true,
        'authorization_url' => $psResult['data']['authorization_url'],
        'reference'         => $reference,
        'order_id'          => (int)$order_id,
        'amount'            => $total,
    ]);

} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('[initialize.php] DB error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Order creation failed. Please try again.']);
}
