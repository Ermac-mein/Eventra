<?php

/**
 * Purchase Ticket API
 * Handles ticket purchases for events
 */

header('Content-Type: application/json');
require_once '../../config/database.php';
require_once '../../config/payment.php';
require_once '../../includes/middleware/auth.php';
require_once '../../api/utils/id-generator.php';

// Check authentication via standardized middleware
$auth_id = checkAuth('user');

// Get the actual user table ID using auth_id
$stmt = $pdo->prepare("SELECT id FROM users WHERE user_auth_id = ?");
$stmt->execute([$auth_id]);
$user_id = $stmt->fetchColumn();

if (!$user_id) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'User profile not found']);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);
$event_id = $data['event_id'] ?? null;
$quantity = (int) ($data['quantity'] ?? 1);
$payment_reference = $data['payment_reference'] ?? null;
$referred_by_client_name = $data['referred_by_client'] ?? null;

if (!$event_id || $quantity < 1) {
    echo json_encode(['success' => false, 'message' => 'Invalid event ID or quantity']);
    exit;
}

// 0. OTP Verification Check (Secure Requirement)
if ($payment_reference && $payment_reference !== 'free') {
    $otp_verified = false;

    if (isset($_SESSION['otp_verified_ref']) && $_SESSION['otp_verified_ref'] === $payment_reference) {
        $otp_verified = true;
    } else {
        // Double check database if session expired but OTP was valid
        // Check that OTP was verified and not expired
        $stmt = $pdo->prepare(
            "SELECT id FROM payment_otps 
             WHERE user_id = ? AND payment_reference = ? 
             AND verified_at IS NOT NULL 
             AND expires_at > NOW() 
             AND attempts < 5 
             ORDER BY verified_at DESC LIMIT 1"
        );
        $stmt->execute([$user_id, $payment_reference]);
        if ($stmt->fetch()) {
            $otp_verified = true;
        }
    }

    if (!$otp_verified) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'OTP verification required before payment confirmation.']);
        exit;
    }

    // Clear session flag after use to prevent reuse in subsequent requests
    unset($_SESSION['otp_verified_ref']);
}

try {
    $pdo->beginTransaction();

    // 1. Get event details & Capacity Check
    $stmt = $pdo->prepare("SELECT * FROM events WHERE id = ? AND status = 'published' FOR UPDATE");
    $stmt->execute([$event_id]);
    $event = $stmt->fetch();

    if (!$event) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Event not found or not available']);
        exit;
    }

    if ($event['max_capacity'] !== null && ($event['attendee_count'] + $quantity) > $event['max_capacity']) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Sorry, this event is sold out or has insufficient capacity.']);
        exit;
    }

    // 2. Calculate total price
    $total_price = (float) $event['price'] * $quantity;

    // 3. Referral Logic
    $referred_by_id = null;
    if ($referred_by_client_name) {
        $stmt = $pdo->prepare("SELECT id FROM clients WHERE name = ? OR REPLACE(LOWER(name), ' ', '-') = ?");
        $stmt->execute([$referred_by_client_name, $referred_by_client_name]);
        $referred_by_id = $stmt->fetchColumn() ?: null;
    }

    // 4. Handle Payment Binding & Verification
    $payment_id = null;
    if ($total_price > 0) {
        if (!$payment_reference) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => 'Payment reference required for paid events.']);
            exit;
        }

        // --- Payment Verification Logic ---
        $verificationSuccess = false;
        $gatewayResponse = "";

        // --- Real Paystack Verification ---
        $url = "https://api.paystack.co/transaction/verify/" . rawurlencode($payment_reference);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer " . PAYSTACK_SECRET_KEY,
            "Cache-Control: no-cache",
        ]);
        $gatewayResponse = curl_exec($ch);
        // curl_close($ch); is deprecated in PHP 8.4+ and no longer needed.

        $paystackResult = json_decode($gatewayResponse);
        if ($paystackResult && $paystackResult->status && $paystackResult->data->status === 'success') {
            $verificationSuccess = true;

            // Extra check: amount match
            $expectedAmountKobo = round($total_price * 100);
            if ($paystackResult->data->amount < $expectedAmountKobo) {
                $verificationSuccess = false;
                $gatewayResponse = json_encode(['success' => false, 'message' => 'Amount mismatch on gateway.']);
            }
        }

        if (!$verificationSuccess) {
            $pdo->rollBack();
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Payment verification failed. No valid transaction found.']);
            exit;
        }

        // --- Save Payment Record ---
        $paystack_id = (string)$paystackResult->data->id;
        $transaction_id = (string)$paystackResult->data->gateway_response;
        $customId = generatePaymentId($pdo);

        $stmt = $pdo->prepare("INSERT INTO payments (event_id, user_id, custom_id, reference, amount, status, paystack_response, payment_id, transaction_id, paid_at) VALUES (?, ?, ?, ?, ?, 'paid', ?, ?, ?, NOW())");
        $stmt->execute([$event_id, $user_id, $customId, $payment_reference, $total_price, $gatewayResponse, $paystack_id, $transaction_id]);
        $payment_id = $pdo->lastInsertId();
    } else {
        // Free ticket
        $ref = 'FREE-' . strtoupper(bin2hex(random_bytes(8)));
        $customId = generatePaymentId($pdo);
        $stmt = $pdo->prepare("INSERT INTO payments (event_id, user_id, custom_id, reference, amount, status, paystack_response, payment_id, transaction_id, paid_at) VALUES (?, ?, ?, ?, ?, 'paid', '{\"status\": \"free\"}', ?, ?, NOW())");
        $stmt->execute([$event_id, $user_id, $customId, $ref, 0, 'free_' . uniqid(), 'free_' . uniqid()]);
        $payment_id = $pdo->lastInsertId();
    }

    // 5. Insert tickets with full identity binding
    $stmt = $pdo->prepare("INSERT INTO tickets (user_id, event_id, payment_id, custom_id, barcode, ticket_code, status, used, created_at) VALUES (?, ?, ?, ?, ?, ?, 'valid', 0, NOW())");
    $tickets_generated = [];

    for ($i = 0; $i < $quantity; $i++) {
        // Requirement 10: Generate cryptographically secure UUID-based barcode
        $barcode = 'EVT-' . strtoupper(bin2hex(random_bytes(12)));
        $ticket_code = strtoupper(bin2hex(random_bytes(4))); // Short human-readable code
        $customId = generateTicketId($pdo);
        $stmt->execute([$user_id, $event_id, $payment_id, $customId, $barcode, $ticket_code]);
        $tickets_generated[] = $barcode;
    }

    // 6. Update event attendee count
    $stmt = $pdo->prepare("UPDATE events SET attendee_count = attendee_count + ? WHERE id = ?");
    $stmt->execute([$quantity, $event_id]);

    $pdo->commit();

    // 7. Post-Processing: Notifications
    try {
        require_once '../utils/notification-helper.php';
        $stmt = $pdo->prepare("SELECT name, email FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();

        $admin_id = function_exists('getAdminUserId') ? getAdminUserId() : null;
        if ($admin_id && function_exists('createTicketPurchaseNotification')) {
            createTicketPurchaseNotification(
                $admin_id,
                $event['client_id'],
                $user_id,
                $user['name'],
                $user['email'],
                $event['event_name'],
                $quantity,
                $total_price
            );
        }
    } catch (Exception $e) {
        error_log("Notification Error: " . $e->getMessage());
    }

    echo json_encode([
        'success' => true,
        'message' => 'Ticket purchased successfully',
        'tickets' => $tickets_generated,
        'quantity' => $quantity,
        'total_price' => $total_price,
        'event_name' => $event['event_name']
    ]);
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'General error: ' . $e->getMessage()]);
}
