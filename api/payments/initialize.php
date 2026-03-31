<?php

header('Content-Type: application/json');
require_once '../../config/database.php';
require_once '../../config/payment.php';
require_once '../../includes/middleware/auth.php';

// Must be a logged-in user
$user_id_or_auth_id = checkAuth('user');

// ── Input ────────────────────────────────────────────────────────────────────
$body     = json_decode(file_get_contents('php://input'), true) ?? [];
$event_id    = (int)($body['event_id']    ?? $_POST['event_id']    ?? 0);
$quantity    = max(1, (int)($body['quantity']    ?? $_POST['quantity']    ?? 1));
$ticket_type = $body['ticket_type'] ?? $_POST['ticket_type'] ?? 'regular';

if (!$event_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'event_id is required.']);
    exit;
}

try {
    // ── Determine actual auth_id and user_id ──────────────────────────────────
    // checkAuth returns auth_accounts.id if token-based, or users.id if session-based
    // We need both to properly complete the order
    
    $auth_id = null;
    $user_id = null;
    $user_email = null;
    $user_name = null;
    
    // First, assume it's an auth_accounts.id from Bearer token
    $uStmt = $pdo->prepare("
        SELECT u.id AS user_id, u.name, a.id AS auth_id, a.email 
        FROM users u 
        JOIN auth_accounts a ON u.user_auth_id = a.id 
        WHERE a.id = ?
    ");
    $uStmt->execute([$user_id_or_auth_id]);
    $user = $uStmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        // Try as users.id from session
        $uStmt = $pdo->prepare("
            SELECT u.id AS user_id, u.name, a.id AS auth_id, a.email 
            FROM users u 
            JOIN auth_accounts a ON u.user_auth_id = a.id 
            WHERE u.id = ?
        ");
        $uStmt->execute([$user_id_or_auth_id]);
        $user = $uStmt->fetch(PDO::FETCH_ASSOC);
    }

    if (!$user) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'User profile not found.']);
        exit;
    }

    $auth_id = $user['auth_id'];
    $user_id = $user['user_id'];
    $user_email = $user['email'];
    $user_name = $user['name'];

    // ── Fetch event + organizer subaccount ─────────────────────────────────
    $eStmt = $pdo->prepare("
        SELECT e.id, e.event_name, e.price, e.status, e.max_capacity, e.attendee_count,
               e.event_date, e.event_time, e.state,
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
        // NOTE: Only block paid events — free events don't need a subaccount
        $unit_price_check = (float)$event['price'];
        if ($unit_price_check > 0) {
            echo json_encode(['success' => false, 'message' => 'The organizer has not set up payment details yet. Please check back later.']);
            exit;
        }
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
    $amount_kobo = (int)round($total * 100);

    // ── Generate unique reference ────────────────────────────────────────────
    $reference = ($amount_kobo <= 0 ? 'FREE-' : 'EVT-') . $event_id . '-' . strtoupper(substr(uniqid(), -8));

    if ($amount_kobo <= 0) {
        // --- FREE EVENT PATH ---
        try {
            $pdo->beginTransaction();

            // 1. Create success order
            $oStmt = $pdo->prepare("
                INSERT INTO orders (user_id, event_id, organizer_id, subaccount_code, amount, transaction_reference, payment_status, payment_method)
                VALUES (?, ?, ?, ?, 0, ?, 'success', 'free')
            ");
            $oStmt->execute([$user_id, $event_id, $event['organizer_id'], $event['subaccount_code'], $reference]);
            $order_id = $pdo->lastInsertId();

            // 2. Create success payment
            require_once '../../api/utils/id-generator.php';
            $paymentCustomId = generatePaymentId($pdo);
            $payStmt = $pdo->prepare("
                INSERT INTO payments (event_id, user_id, custom_id, reference, amount, quantity, ticket_type, status, paid_at)
                VALUES (?, ?, ?, ?, 0, ?, ?, 'paid', NOW())
            ");
            $payStmt->execute([$event_id, $user_id, $paymentCustomId, $reference, $quantity, $ticket_type]);
            $payment_id = $pdo->lastInsertId();

            // 3. Create ticket(s)
            require_once '../../includes/helpers/ticket-helper.php';
            require_once '../../includes/helpers/email-helper.php';
            require_once '../../api/utils/notification-helper.php';

            $tickets = [];
            for ($i = 0; $i < $quantity; $i++) {
                $ticketCustomId = generateTicketId($pdo);
                $barcode = 'TKT-FREE-' . strtoupper(substr(uniqid(), -8));

                $pdo->prepare("
                    INSERT INTO tickets (user_id, event_id, payment_id, custom_id, barcode, status)
                    VALUES (?, ?, ?, ?, ?, 'valid')
                ")->execute([$user_id, $event_id, $payment_id, $ticketCustomId, $barcode]);
                $ticket_id = $pdo->lastInsertId();

                $ticketData = [
                    'barcode' => $barcode,
                    'event_id' => $event_id,
                    'user_id' => $user_id,
                    'order_id' => $order_id,
                    'event_name' => $event['event_name'],
                    'event_date' => $event['event_date'],
                    'event_time' => $event['event_time'],
                    'user_name' => $user_name
                ];
                $qrPath = generateTicketQRCode($ticketData);
                $pdo->prepare("UPDATE tickets SET qr_code_path = ? WHERE id = ?")
                    ->execute([str_replace(__DIR__ . '/../../', '', $qrPath), $ticket_id]);

                $tickets[] = ['barcode' => $barcode, 'id' => $ticket_id];
            }

            // 4. Update attendee count
            $pdo->prepare("UPDATE events SET attendee_count = attendee_count + ? WHERE id = ?")
                ->execute([$quantity, $event_id]);

            $pdo->commit();

            // 5. Notifications
            createPaymentSuccessNotification($auth_id, $event['event_name'], 0);
            createTicketIssuedNotification($auth_id, $event['event_name'], $tickets[0]['barcode']);

            echo json_encode([
                'success' => true,
                'message' => 'Free ticket claimed successfully!',
                'reference' => $reference,
                'order_id' => (int)$order_id,
                'is_free' => true
            ]);
            exit;
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('[initialize.php] Free checkout error: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to process free ticket.']);
            exit;
        }
    }

    $pdo->beginTransaction();

    // ── Insert pending order ─────────────────────────────────────────────────
    $oStmt = $pdo->prepare("
        INSERT INTO orders
            (user_id, event_id, organizer_id, subaccount_code, amount, transaction_reference, payment_status)
        VALUES (?, ?, ?, ?, ?, ?, 'pending')
    ");
    $oStmt->execute([
        $user_id,
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
        'email'         => $user_email,
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
            'ticket_type'=> $ticket_type,
            'user_id'    => $user_id,
            'user_name'  => $user_name,
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
        $psCode = $psResult['code'] ?? 'N/A';
        error_log("[initialize.php] Paystack Error: {$errMsg} (Code: {$psCode})");

        // Provide more helpful messages for common errors
        if (str_contains($errMsg, 'subaccount')) {
            $errMsg = "Organizer payment setup issue: " . $errMsg;
        }

        echo json_encode(['success' => false, 'message' => $errMsg, 'error_code' => $psCode]);
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
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('[initialize.php] Error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Initialization failed: ' . $e->getMessage()]);
}
