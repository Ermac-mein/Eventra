<?php

/**
 * Forgot Password - Step 1: Request OTP (CLIENTS ONLY)
 * Sends OTP to client's registered phone number via SMS
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/helpers/otp-service.php';
require_once __DIR__ . '/../../includes/helpers/validation.php';

$data = json_decode(file_get_contents("php://input"), true);
$email = $data['email'] ?? $data['identity'] ?? null;

if (!$email || !validateEmail($email)) {
    // If it's a valid string but filter_var failed, we might still want to try it if it looks like an email
    // but for now let's just ensure we support both identity and email keys.
    if (!$email) {
        echo json_encode(['success' => false, 'message' => 'Email address is required.']);
        exit;
    }
}

try {
    // Check if email exists in auth_accounts (CLIENTS ONLY)
    $stmt = $pdo->prepare("
        SELECT a.id, c.phone, c.id as client_id
        FROM auth_accounts a
        INNER JOIN clients c ON a.id = c.client_auth_id
        WHERE a.email = ? AND a.role = 'client' AND a.deleted_at IS NULL
        LIMIT 1
    ");
    $stmt->execute([$email]);
    $account = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$account) {
        // Security: Don't reveal if email exists
        echo json_encode(['success' => true, 'message' => 'If an account exists with this email, you will receive an OTP shortly.']);
        exit;
    }

    // Get phone number from clients table
    $phone = $account['phone'] ?? null;

    if (!$phone) {
        error_log("[ForgotPassword] No phone number on file for client: " . $account['client_id']);
        echo json_encode(['success' => true, 'message' => 'If an account exists with this email, you will receive an OTP shortly.']);
        exit;
    }

    // Generate and send OTP
    $result = OTPService::generateOTP($phone, 'password_reset', $account['id']);

    if ($result['success']) {
        echo json_encode([
            'success' => true,
            'message' => 'OTP has been sent to your registered phone number.',
            'otp_purpose' => 'password_reset'
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => $result['message']]);
    }

} catch (PDOException $e) {
    error_log("Forgot password error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred. Please try again later.']);
}
