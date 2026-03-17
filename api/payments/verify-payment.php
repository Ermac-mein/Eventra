<?php
/**
 * Verify Payment API — Idempotent Fallback
 *
 * Called by the frontend after Paystack redirect.
 * If the webhook already processed the payment, returns the existing order state.
 * If not (webhook delay), verifies with Paystack and runs post-payment processing.
 */
header('Content-Type: application/json');
require_once '../../config/database.php';
require_once '../../config/payment.php';
require_once '../../includes/middleware/auth.php';
require_once '../../includes/helpers/ticket-helper.php';
require_once '../../includes/helpers/email-helper.php';
require_once '../../includes/helpers/sms-helper.php';
require_once '../../api/utils/notification-helper.php';

// Load shared webhook helper (processSuccessfulPayment is defined there)
// We replicate it inline here to keep the file self-contained.

$auth_id = checkAuth('user');

$body      = json_decode(file_get_contents('php://input'), true) ?? [];
$reference = trim($body['reference'] ?? $_GET['reference'] ?? '');

if (!$reference) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Payment reference is required.']);
    exit;
}

try {
    // ── Fetch user ───────────────────────────────────────────────────────────
    $uStmt = $pdo->prepare("SELECT id FROM users WHERE user_auth_id = ?");
    $uStmt->execute([$auth_id]);
    $user = $uStmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'User not found.']);
        exit;
    }

    // ── Check existing order ─────────────────────────────────────────────────
    $oStmt = $pdo->prepare("
        SELECT o.id, o.payment_status, o.amount, o.event_id, o.user_id, o.organizer_id,
               e.event_name, e.event_date, e.event_time, e.address, e.location,
               u.name AS user_name, u.phone AS user_phone,
               a.email AS user_email,
               c.client_auth_id AS organizer_auth_id
        FROM orders o
        JOIN events e ON o.event_id = e.id
        JOIN users u ON o.user_id = u.id
        JOIN auth_accounts a ON u.user_auth_id = a.id
        JOIN clients c ON o.organizer_id = c.id
        WHERE o.transaction_reference = ?
          AND o.user_id = ?
    ");
    $oStmt->execute([$reference, $user['id']]);
    $order = $oStmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Order not found. Please contact support with your reference.']);
        exit;
    }

    // ── Already marked success — no further action needed ────────────────────
    if ($order['payment_status'] === 'success') {
        echo json_encode([
            'success'        => true,
            'status'         => 'success',
            'message'        => 'Payment already verified.',
            'amount'         => (float)$order['amount'],
            'event_name'     => $order['event_name'],
            'reference'      => $reference,
        ]);
        exit;
    }

    // ── Verify with Paystack ─────────────────────────────────────────────────
    $url = 'https://api.paystack.co/transaction/verify/' . rawurlencode($reference);
    $ch  = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . PAYSTACK_SECRET_KEY,
            'Cache-Control: no-cache',
        ],
    ]);
    $response  = curl_exec($ch);
    $curlError = curl_error($ch);

    if ($curlError || !$response) {
        http_response_code(502);
        echo json_encode(['success' => false, 'message' => 'Could not reach payment gateway. Please try again.']);
        exit;
    }

    $result     = json_decode($response, true);
    $psStatus   = $result['data']['status'] ?? 'unknown';

    if (!$result || !($result['status'] ?? false) || $psStatus !== 'success') {
        // Mark as failed if Paystack says it failed
        if ($psStatus === 'failed') {
            $pdo->prepare("UPDATE orders SET payment_status = 'failed' WHERE transaction_reference = ?")
                ->execute([$reference]);
        }
        echo json_encode([
            'success' => false,
            'status'  => $psStatus,
            'message' => 'Payment not successful.',
        ]);
        exit;
    }

    // ── Paystack confirmed success — run post-payment processing ─────────────
    // (Same logic as webhook; fully idempotent)

    $pdo->beginTransaction();

    $pdo->prepare("
        UPDATE orders SET payment_status = 'success', payment_method = ?, updated_at = NOW()
        WHERE id = ? AND payment_status != 'success'
    ")->execute([$result['data']['channel'] ?? 'card', $order['id']]);

    $pdo->prepare("UPDATE events SET attendee_count = attendee_count + 1 WHERE id = ?")->execute([$order['event_id']]);

    // Check if ticket already exists (idempotency) via payment reference
    $tStmt = $pdo->prepare("
        SELECT t.id, t.barcode 
        FROM tickets t 
        JOIN payments p ON t.payment_id = p.id 
        WHERE p.reference = ?
    ");
    $tStmt->execute([$reference]);
    $existingTicket = $tStmt->fetch(PDO::FETCH_ASSOC);

    if (!$existingTicket) {
        // Load ID Generator
        require_once '../../api/utils/id-generator.php';

        // 1. Insert into payments table (fallback creation)
        $paymentCustomId = generatePaymentId($pdo);
        $payStmt = $pdo->prepare("
            INSERT INTO payments (event_id, user_id, custom_id, reference, amount, status, paystack_response, payment_id, transaction_id, paid_at)
            VALUES (?, ?, ?, ?, ?, 'paid', ?, ?, ?, NOW())
        ");
        $payStmt->execute([
            $order['event_id'],
            $order['user_id'],
            $paymentCustomId,
            $reference,
            $order['amount'],
            json_encode($result['data']),
            $result['data']['id'] ?? null,
            $result['data']['reference'] ?? null
        ]);
        $payment_id = $pdo->lastInsertId();

        // 2. Generate ticket custom_id and barcode
        $ticketCustomId = generateTicketId($pdo);
        $barcode = 'TKT-' . strtoupper(uniqid());

        // 3. Insert ticket linked to the actual payment_id
        $pdo->prepare("
            INSERT INTO tickets (user_id, event_id, payment_id, custom_id, barcode, status)
            VALUES (?, ?, ?, ?, ?, 'valid')
        ")->execute([
            $order['user_id'],
            $order['event_id'],
            $payment_id,
            $ticketCustomId,
            $barcode
        ]);
        $ticket_id = $pdo->lastInsertId();

        $ticketData = [
            'barcode'        => $barcode,
            'event_id'       => $order['event_id'],
            'user_id'        => $order['user_id'],
            'event_name'     => $order['event_name'],
            'event_date'     => $order['event_date'],
            'event_time'     => $order['event_time'],
            'location'       => $order['location'] ?? $order['address'],
            'address'        => $order['address'],
            'user_name'      => $order['user_name'],
            'payment_status' => 'paid',
            'custom_id'      => $ticketCustomId,
        ];
        $pdfPath    = generateTicketPDF($ticketData);
        $qrCodePath = generateTicketQRCode($ticketData);
        $pdo->prepare("UPDATE tickets SET qr_code_path = ? WHERE id = ?")
            ->execute([
                str_replace(__DIR__ . '/../../', '', $qrCodePath),
                $ticket_id,
            ]);
    } else {
        $barcode = $existingTicket['barcode'];
        $pdfPath = __DIR__ . '/../../uploads/tickets/pdfs/ticket_' . $barcode . '.pdf';
    }

    $pdo->commit();

    // ── Async notifications (fire-and-forget) ────────────────────────────────
    if (!empty($pdfPath) && file_exists($pdfPath)) {
        sendTicketEmailFull($order['user_email'], [
            'barcode'    => $barcode,
            'event_name' => $order['event_name'],
            'event_date' => $order['event_date'],
            'event_time' => $order['event_time'],
            'location'   => $order['location'] ?? $order['address'],
            'user_name'  => $order['user_name'],
            'order_id'   => $order['id'],
            'amount'     => $order['amount'],
        ], $pdfPath);
    }

    if (!empty($order['user_phone'])) {
        sendSMS($order['user_phone'],
            "Hi {$order['user_name']}, your ticket for {$order['event_name']} is confirmed! Check your email."
        );
    }

    createPaymentSuccessNotification($auth_id, $order['event_name'], $order['amount']);
    createTicketIssuedNotification($auth_id, $order['event_name'], $barcode ?? '');
    createNewSaleNotification($order['organizer_auth_id'], $order['user_name'], $order['event_name'], $order['amount']);

    echo json_encode([
        'success'    => true,
        'status'     => 'success',
        'message'    => 'Payment verified successfully.',
        'reference'  => $reference,
        'amount'     => (float)$order['amount'],
        'event_name' => $order['event_name'],
        'barcode'    => $barcode ?? null,
    ]);

} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('[verify-payment.php] DB error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Verification failed. Please contact support.']);
}


