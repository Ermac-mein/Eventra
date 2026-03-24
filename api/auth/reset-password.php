<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../config/database.php';

$data = json_decode(file_get_contents("php://input"), true);
$token = $data['token'] ?? null;
$password = $data['password'] ?? null;

if (!$token || !$password) {
    echo json_encode(['success' => false, 'message' => 'Token and new password are required.']);
    exit;
}

try {
    // 1. Verify token
    $stmt = $pdo->prepare("SELECT auth_id FROM auth_tokens WHERE token = ? AND type = 'reset_password' AND expires_at > NOW() AND revoked = 0");
    $stmt->execute([$token]);
    $authId = $stmt->fetchColumn();

    if (!$authId) {
        echo json_encode(['success' => false, 'message' => 'Invalid or expired reset link.']);
        exit;
    }

    // 2. Validate Password Strength (Uppercase, Digit, Special Character, Min 8 chars)
    if (!preg_match('/^(?=.*[A-Z])(?=.*[0-9])(?=.*[!@#$%^&*(),.?":{}|<>]).{8,}$/', $password)) {
        echo json_encode([
            'success' => false,
            'message' => 'Password must be at least 8 characters long and include one uppercase letter, one digit, and one special character.'
        ]);
        exit;
    }

    // 3. Update password in auth_accounts
    $password_hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("UPDATE auth_accounts SET password = ? WHERE id = ?");
    $stmt->execute([$password_hash, $authId]);

    // 4. Revoke token
    $stmt = $pdo->prepare("UPDATE auth_tokens SET revoked = 1 WHERE token = ?");
    $stmt->execute([$token]);

    echo json_encode(['success' => true, 'message' => 'Your password has been reset successfully. You can now log in.']);

} catch (PDOException $e) {
    error_log("Reset password error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error occurred.']);
}