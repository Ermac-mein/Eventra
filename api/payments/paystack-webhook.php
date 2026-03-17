<?php
/**
 * Paystack Webhook Handler — Marketplace Edition
 *
 * Handles: charge.success | charge.failed | refund.processed
 */
header('Content-Type: application/json');
require_once '../../config/database.php';
require_once '../../config/payment.php';
require_once '../../includes/helpers/ticket-helper.php';
require_once '../../includes/helpers/email-helper.php';
require_once '../../includes/helpers/sms-helper.php';
require_once '../../api/utils/notification-helper.php';

$input = file_get_contents('php://input');

// ── Signature Verification ───────────────────────────────────────────────────
$signature = $_SERVER['HTTP_X_PAYSTACK_SIGNATURE'] ?? '';
if (!verifyPaystackSignature($input, $signature)) {
    http_response_code(401);
    exit;
}

http_response_code(200); // Acknowledge early

$event = json_decode($input, true);
$type  = $event['event'] ?? '';
$data  = $event['data']  ?? [];

// ── Shared helper: fetch order by reference ──────────────────────────────────
function fetchOrder(PDO $pdo, string $reference): ?array
{
    $stmt = $pdo->prepare("
        SELECT o.*,
               e.event_name, e.event_date, e.event_time, e.address, e.location,
               u.id AS user_id, u.name AS user_name,
               a.email AS user_email, u.phone AS user_phone,
               c.client_auth_id AS organizer_auth_id,
               ca.email AS organizer_email
        FROM orders o
        JOIN events  e  ON o.event_id    = e.id
        JOIN users   u  ON o.user_id     = u.id
        JOIN auth_accounts a ON u.user_auth_id = a.id
        JOIN clients c  ON o.organizer_id = c.id
        JOIN auth_accounts ca ON c.client_auth_id = ca.id
        WHERE o.transaction_reference = ?
    ");
    $stmt->execute([$reference]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

// ── Shared helper: post-payment processing (idempotent) ──────────────────────
function processSuccessfulPayment(PDO $pdo, array $order, array $psData): void
{
    // Idempotency guard: already processed?
    if ($order['payment_status'] === 'success') {
        return;
    }

    $pdo->beginTransaction();

    try {
        // Update order
        $pdo->prepare("
            UPDATE orders
            SET payment_status  = 'success',
                payment_method  = ?,
                updated_at      = NOW()
            WHERE id = ?
        ")->execute([
            $psData['channel'] ?? 'card',
            $order['id'],
        ]);

        // Increment event attendee count
        $pdo->prepare("
            UPDATE events SET attendee_count = attendee_count + 1 WHERE id = ?
        ")->execute([$order['event_id']]);

        // Check if ticket already exists (idempotency) via payment reference
        $tStmt = $pdo->prepare("
            SELECT t.id, t.barcode 
            FROM tickets t 
            JOIN payments p ON t.payment_id = p.id 
            WHERE p.reference = ?
        ");
        $tStmt->execute([$order['transaction_reference']]);
        $existingTicket = $tStmt->fetch(PDO::FETCH_ASSOC);

        if (!$existingTicket) {
            // Load ID Generator
            require_once '../../api/utils/id-generator.php';

            // 1. Insert into payments table first with custom_id
            $paymentCustomId = generatePaymentId($pdo);
            $payStmt = $pdo->prepare("
                INSERT INTO payments (event_id, user_id, custom_id, reference, amount, status, paystack_response, payment_id, transaction_id, paid_at)
                VALUES (?, ?, ?, ?, ?, 'paid', ?, ?, ?, NOW())
            ");
            $payStmt->execute([
                $order['event_id'],
                $order['user_id'],
                $paymentCustomId,
                $order['transaction_reference'],
                $order['amount'],
                json_encode($psData),
                $psData['id'] ?? null,
                $psData['reference'] ?? null
            ]);
            $payment_id = $pdo->lastInsertId();

            // 2. Generate barcode and custom_id for ticket
            $barcode = 'TKT-' . strtoupper(uniqid());
            $ticketCustomId = generateTicketId($pdo);

            // 3. Insert ticket with actual payment_id and custom_id
            $pdo->prepare("
                INSERT INTO tickets (user_id, event_id, payment_id, custom_id, barcode, status)
                VALUES (?, ?, ?, ?, ?, 'valid')
            ")->execute([
                $order['user_id'],
                $order['event_id'],
                $payment_id,
                $ticketCustomId,
                $barcode,
            ]);
            $ticket_id = $pdo->lastInsertId();

            // Generate PDF + QR
            $ticketData = [
                'barcode'        => $barcode,
                'event_id'       => $order['event_id'],
                'user_id'        => $order['user_id'],
                'order_id'       => $order['id'],
                'event_name'     => $order['event_name'],
                'event_date'     => $order['event_date'],
                'event_time'     => $order['event_time'],
                'location'       => $order['location'] ?? $order['address'],
                'address'        => $order['address'],
                'user_name'      => $order['user_name'],
                'payment_status' => 'paid',
            ];

            $pdfPath    = generateTicketPDF($ticketData);
            $qrCodePath = generateTicketQRCode($ticketData);

            // Save paths back to ticket row
            $pdo->prepare("
                UPDATE tickets SET qr_code_path = ? WHERE id = ?
            ")->execute([
                str_replace(__DIR__ . '/../../', '', $qrCodePath),
                $ticket_id,
            ]);

        } else {
            $barcode = $existingTicket['barcode'];
            $pdfPath = __DIR__ . '/../../uploads/tickets/pdfs/ticket_' . $barcode . '.pdf';
            if (!file_exists($pdfPath)) {
                $ticketData = [
                    'barcode'        => $barcode,
                    'event_id'       => $order['event_id'],
                    'user_id'        => $order['user_id'],
                    'order_id'       => $order['id'],
                    'event_name'     => $order['event_name'],
                    'event_date'     => $order['event_date'],
                    'event_time'     => $order['event_time'],
                    'location'       => $order['location'] ?? $order['address'],
                    'address'        => $order['address'],
                    'user_name'      => $order['user_name'],
                    'payment_status' => 'paid',
                ];
                $pdfPath = generateTicketPDF($ticketData);
            }
            $barcode = $existingTicket['barcode'];
        }

        $pdo->commit();

        // ── Send notifications (outside transaction) ──────────────────────────
        // Email with PDF ticket
        if (file_exists($pdfPath ?? '')) {
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

        // SMS to buyer
        if (!empty($order['user_phone'])) {
            sendSMS($order['user_phone'],
                "Hi {$order['user_name']}, your ticket for {$order['event_name']} is confirmed! Check your email for the PDF ticket."
            );
        }

        // In-app: buyer
        $userAuthStmt = $pdo->prepare("SELECT user_auth_id FROM users WHERE id = ?");
        $userAuthStmt->execute([$order['user_id']]);
        $userAuthRow = $userAuthStmt->fetch(PDO::FETCH_ASSOC);

        if ($userAuthRow) {
            createPaymentSuccessNotification($userAuthRow['user_auth_id'], $order['event_name'], $order['amount']);
            createTicketIssuedNotification($userAuthRow['user_auth_id'], $order['event_name'], $barcode ?? '');
        }

        // In-app: organizer (new sale alert)
        createNewSaleNotification($order['organizer_auth_id'], $order['user_name'], $order['event_name'], $order['amount']);

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('[Webhook] processSuccessfulPayment error: ' . $e->getMessage());
    }
}

// ── Event Routing ────────────────────────────────────────────────────────────
try {
    switch ($type) {

        case 'charge.success': {
            $reference = $data['reference'] ?? '';
            if (!$reference) break;

            $order = fetchOrder($pdo, $reference);
            if (!$order) {
                error_log("[Webhook] charge.success: order not found for reference {$reference}");
                break;
            }

            processSuccessfulPayment($pdo, $order, $data);
            break;
        }

        case 'charge.failed': {
            $reference = $data['reference'] ?? '';
            if (!$reference) break;

            $pdo->prepare("
                UPDATE orders SET payment_status = 'failed', updated_at = NOW()
                WHERE transaction_reference = ? AND payment_status = 'pending'
            ")->execute([$reference]);

            // Optionally notify buyer
            $order = fetchOrder($pdo, $reference);
            if ($order) {
                $userAuthStmt = $pdo->prepare("SELECT user_auth_id FROM users WHERE id = ?");
                $userAuthStmt->execute([$order['user_id']]);
                $userAuthRow = $userAuthStmt->fetch(PDO::FETCH_ASSOC);
                if ($userAuthRow) {
                    createNotification(
                        $userAuthRow['user_auth_id'],
                        "Your payment for {$order['event_name']} failed. Please try again.",
                        'payment_failed'
                    );
                }
            }
            break;
        }

        case 'refund.processed': {
            $reference = $data['transaction_reference'] ?? $data['reference'] ?? '';
            if (!$reference) break;

            $pdo->prepare("
                UPDATE orders
                SET payment_status = 'refunded',
                    refund_status  = 'processed',
                    updated_at     = NOW()
                WHERE transaction_reference = ?
            ")->execute([$reference]);

            // Mark ticket as cancelled
            $oStmt = $pdo->prepare("SELECT id, amount FROM orders WHERE transaction_reference = ?");
            $oStmt->execute([$reference]);
            $orderRow = $oStmt->fetch(PDO::FETCH_ASSOC);

            if ($orderRow) {
                $pdo->prepare("
                    UPDATE tickets SET status = 'cancelled' WHERE order_id = ?
                ")->execute([$orderRow['id']]);

                // Update refund_requests status
                $pdo->prepare("
                    UPDATE refund_requests SET status = 'approved', processed_at = NOW()
                    WHERE order_id = ? AND status IN ('pending', 'approved')
                ")->execute([$orderRow['id']]);

                $fullOrder = fetchOrder($pdo, $reference);
                if ($fullOrder) {
                    $userAuthStmt = $pdo->prepare("SELECT user_auth_id FROM users WHERE id = ?");
                    $userAuthStmt->execute([$fullOrder['user_id']]);
                    $userAuthRow = $userAuthStmt->fetch(PDO::FETCH_ASSOC);
                    if ($userAuthRow) {
                        createRefundProcessedNotification(
                            $userAuthRow['user_auth_id'],
                            $fullOrder['event_name'],
                            $fullOrder['amount']
                        );
                    }
                }
            }
            break;
        }

        default:
            // Unhandled event — acknowledged (200 already sent)
            break;
    }

} catch (Throwable $e) {
    error_log('[Paystack Webhook] Unhandled error: ' . $e->getMessage());
}
