<?php

/**
 * Forgot Password - Step 2: Verify OTP (CLIENTS ONLY)
 * Endpoint to verify OTP before allowing password change
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/helpers/otp-service.php';
require_once __DIR__ . '/../../includes/helpers/validation.php';

$data = json_decode(file_get_contents("php://input"), true);
$phone = $data['phone'] ?? null;
$otpCode = $data['otp_code'] ?? null;

if (!$phone || !$otpCode) {
    echo json_encode(['success' => false, 'message' => 'Phone number and OTP code are required.']);
    exit;
}

try {
    // Normalize phone number
    $phone = normalizePhoneNumber($phone);
    
    // Verify that the phone belongs to a client account
    $stmt = $pdo->prepare("
        SELECT c.id 
        FROM clients c
        INNER JOIN auth_accounts a ON c.client_auth_id = a.id
        WHERE c.phone = ? AND a.role = 'client' AND a.deleted_at IS NULL
        LIMIT 1
    ");
    $stmt->execute([$phone]);
    $client = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$client) {
        echo json_encode(['success' => false, 'message' => 'Phone number not found for any client account.']);
        exit;
    }
    
    // Verify OTP
    $result = OTPService::verifyOTP($phone, $otpCode, 'password_reset');

    if (!$result['valid']) {
        echo json_encode(['success' => false, 'message' => $result['error']]);
        exit;
    }

    // OTP is valid - return OTP ID for next step
    echo json_encode([
        'success' => true,
        'message' => 'OTP verified successfully. Please enter your new password.',
        'otp_id' => $result['otp_id']
    ]);

} catch (Exception $e) {
    error_log("OTP verification error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Verification failed. Please try again.']);
}
