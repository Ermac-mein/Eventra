<?php

header('Content-Type: application/json');
require_once __DIR__ . '/../../config/database.php';

$data = json_decode(file_get_contents("php://input"), true);
$email = $data['email'] ?? null;
$otpCode = $data['otp_code'] ?? null;

if (!$email || !$otpCode) {
    echo json_encode(['success' => false, 'message' => 'Email and OTP are required.']);
    exit;
}

try {
    // Get the user account
    $stmt = $pdo->prepare("SELECT id FROM auth_accounts WHERE email = ? AND role = 'client' AND deleted_at IS NULL");
    $stmt->execute([$email]);
    $account = $stmt->fetch();

    if (!$account) {
        echo json_encode(['success' => false, 'message' => 'Account not found.']);
        exit;
    }

    // Verify OTP token
    $stmt = $pdo->prepare("
        SELECT id FROM auth_tokens 
        WHERE auth_id = ? AND token = ? AND type = 'otp' AND revoked = 0 AND expires_at > NOW()
    ");
    $stmt->execute([$account['id'], $otpCode]);
    $token = $stmt->fetch();

    if (!$token) {
        echo json_encode(['success' => false, 'message' => 'Invalid or expired OTP code.']);
        exit;
    }

    echo json_encode(['success' => true, 'message' => 'OTP verified successfully.']);
} catch (PDOException $e) {
    error_log("OTP verification error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error occurred.']);
}
?>
