<?php

/**
 * Reset Password - Step 3: Set New Password (CLIENTS ONLY)
 * Called after OTP verification with new password
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/helpers/otp-service.php';
require_once __DIR__ . '/../../includes/helpers/validation.php';

$data = json_decode(file_get_contents("php://input"), true);
$otpId = $data['otp_id'] ?? null;
$password = $data['password'] ?? null;
$passwordConfirm = $data['password_confirm'] ?? null;

if (!$otpId || !$password) {
    echo json_encode(['success' => false, 'message' => 'OTP ID and password are required.']);
    exit;
}

if ($password !== $passwordConfirm) {
    echo json_encode(['success' => false, 'message' => 'Passwords do not match.']);
    exit;
}

try {
    // Validate password strength
    $validation = validatePasswordStrength($password);
    if (!$validation['valid']) {
        echo json_encode([
            'success' => false,
            'message' => 'Password does not meet requirements: ' . implode(', ', $validation['errors'])
        ]);
        exit;
    }

    // Verify OTP still exists and is verified (CLIENT ONLY)
    $stmt = $pdo->prepare("
        SELECT otp.auth_id 
        FROM otp_requests otp
        INNER JOIN auth_accounts a ON otp.auth_id = a.id
        WHERE otp.id = ? AND otp.is_verified = 1 AND otp.purpose = 'password_reset' 
        AND a.role = 'client' AND a.deleted_at IS NULL
        LIMIT 1
    ");
    $stmt->execute([$otpId]);
    $otpRecord = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$otpRecord) {
        echo json_encode(['success' => false, 'message' => 'Invalid or unverified OTP.']);
        exit;
    }

    $authId = $otpRecord['auth_id'];

    // Hash password with bcrypt (saltRounds=12 for PASSWORD_BCRYPT)
    $passwordHash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

    // Update password
    $stmt = $pdo->prepare("
        UPDATE auth_accounts 
        SET password = ?, last_password_change = NOW(), password_change_required = 0 
        WHERE id = ?
    ");
    $stmt->execute([$passwordHash, $authId]);

    // Delete/consume the OTP
    OTPService::consumeOTP($otpId);

    echo json_encode([
        'success' => true,
        'message' => 'Your password has been reset successfully. You can now log in.'
    ]);

} catch (PDOException $e) {
    error_log("Reset password error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred. Please try again later.']);
}
