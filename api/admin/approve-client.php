<?php

/**
 * Approve / Decline Client API
 * Supports optional admin notes stored to DB and included in client notification.
 */

header('Content-Type: application/json');
require_once '../../config/database.php';
require_once '../../config/payment.php';
require_once '../../includes/middleware/auth.php';

// Unified admin middleware (consistent with other admin APIs)
$admin_id = checkAuth('admin');

$data = json_decode(file_get_contents('php://input'), true);
$client_id   = $data['client_id'] ?? null;
$status      = $data['status'] ?? null;      // 1 = approve, 0 = decline
$admin_notes = trim($data['admin_notes'] ?? '');

if (!$client_id || !in_array($status, [0, 1], true)) {
    echo json_encode(['success' => false, 'message' => 'Invalid parameters. Need client_id and status (0 or 1).']);
    exit;
}

try {
    $verification_status = $status ? 'verified' : 'rejected';

    // 1. If approving, check that client has payment setup (bank details + subaccount code)
    if ($status) {
        $checkPaymentStmt = $pdo->prepare("
            SELECT c.account_number, c.bank_code, c.subaccount_code, c.name, c.business_name, c.client_auth_id, a.email
            FROM clients c
            JOIN auth_accounts a ON c.client_auth_id = a.id
            WHERE c.id = ? AND c.deleted_at IS NULL
        ");
        $checkPaymentStmt->execute([$client_id]);
        $paymentInfo = $checkPaymentStmt->fetch(PDO::FETCH_ASSOC);

        if (!$paymentInfo) {
            echo json_encode([
                'success' => false,
                'message' => 'Client not found or has been deleted.'
            ]);
            exit;
        }

        // Ensure payment setup is complete before approving
        if (empty($paymentInfo['account_number']) || empty($paymentInfo['bank_code'])) {
            echo json_encode([
                'success' => false,
                'message' => 'Cannot approve: Client has not completed payment setup. Ensure they have valid bank details and a Paystack subaccount.'
            ]);
            exit;
        }

        // If no subaccount_code, create one now using the payment helper
        if (empty($paymentInfo['subaccount_code'])) {
            $subResult = ensureSubaccount(
                $pdo,
                $paymentInfo['client_auth_id'],
                $paymentInfo['bank_code'],
                $paymentInfo['account_number'],
                $paymentInfo['business_name'] ?: $paymentInfo['name'],
                $paymentInfo['email']
            );

            if (!$subResult['success']) {
                // Log the error but we might still allow approval if fallback is working, 
                // but usually, it's better to log it clearly.
                error_log("[approve-client.php] Subaccount creation failed for Client {$client_id}: " . $subResult['message']);
            } else {
                error_log("[approve-client.php] Subaccount created/linked for Client {$client_id}: " . $subResult['subaccount_code']);
            }
        }
    }

    // 2. Update verification_status and persist admin_notes
    if ($status) {
        $stmt = $pdo->prepare("
            UPDATE clients 
            SET verification_status = ?, admin_notes = ?, nin_verified = 1, bvn_verified = 1, updated_at = NOW() 
            WHERE id = ?
        ");
    } else {
        $stmt = $pdo->prepare("
            UPDATE clients 
            SET verification_status = ?, admin_notes = ?, updated_at = NOW() 
            WHERE id = ?
        ");
    }
    $stmt->execute([$verification_status, $admin_notes ?: null, $client_id]);

    $stmtStatusCheck = $pdo->prepare("SELECT verification_status FROM clients WHERE id = ?");
    $stmtStatusCheck->execute([$client_id]);
    $current_status = $stmtStatusCheck->fetchColumn();

    if ($stmt->rowCount() > 0 || $current_status === $verification_status) {
        $status_text = $status ? 'Approved' : 'Declined';

        // Send notification to client with decision + notes
        require_once '../utils/notification-helper.php';

        $clientStmt = $pdo->prepare("SELECT id, client_auth_id, name, business_name FROM clients WHERE id = ?");
        $clientStmt->execute([$client_id]);
        $client = $clientStmt->fetch();

        if ($client) {
            $display_name = $client['business_name'] ?: $client['name'];
            $recipient_auth_id = $client['client_auth_id'];
            $admin_auth_id = getAuthId();

            if ($status) {
                $msg = "🎉 Congratulations, {$display_name}! Your Event Planner account has been verified and approved. You can now create unlimited events and receive payments from ticket sales.";
                if (!empty($admin_notes)) {
                    $msg .= " Admin note: {$admin_notes}";
                }
                $type = 'account_approved';
            } else {
                $msg = "Your Event Planner account verification has been declined.";
                if (!empty($admin_notes)) {
                    $msg .= " Reason: {$admin_notes}";
                } else {
                    $msg .= " Please ensure your profile details (NIN, BVN, and bank account) are complete and accurate, then contact support for re-review.";
                }
                $type = 'account_declined';
            }

            createNotification(
                $recipient_auth_id,
                $msg,
                $type,
                $admin_auth_id,
                'client',
                'admin',
                ['admin_notes' => $admin_notes, 'decision' => $verification_status]
            );
        }

        echo json_encode([
            'success' => true,
            'message' => "Client profile successfully " . strtolower($status_text),
            'client' => [
                'id' => $client['id'],
                'name' => $client['business_name'] ?: $client['name'],
                'verification_status' => $verification_status
            ]
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Client not found or no changes made.']);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
