<?php

/**
 * Reset Password - Step 3: Set New Password (CLIENTS ONLY)
 * Called after OTP verification with new password
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/helpers/validation.php';

$data = json_decode(file_get_contents("php://input"), true);
$resetToken = $data['reset_token'] ?? null;
$password = $data['password'] ?? $data['new_password'] ?? null;
$passwordConfirm = $data['password_confirm'] ?? $password;

if (!$resetToken || !$password) {
    echo json_encode(['success' => false, 'message' => 'Reset token and password are required.']);
    exit;
}

if ($password !== $passwordConfirm) {
    echo json_encode(['success' => false, 'message' => 'Passwords do not match.']);
    exit;
}

try {
    $pdo = getPDO();
    
    // Verify token exists and is valid
    $stmt = $pdo->prepare("
        SELECT id, auth_id 
        FROM auth_tokens 
        WHERE token = ? AND type = 'reset_password' AND revoked = 0 AND expires_at > NOW()
        LIMIT 1
    ");
    $stmt->execute([$resetToken]);
    $tokenRecord = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$tokenRecord) {
        echo json_encode(['success' => false, 'message' => 'Invalid or expired reset token.']);
        exit;
    }

    $authId = $tokenRecord['auth_id'];

    // Hash password with bcrypt
    $passwordHash = password_hash($password, PASSWORD_BCRYPT);

    // Update password
    // If last_password_change or password_change_required exist, update them too, but ignore if they don't to prevent crashes.
    $stmt = $pdo->prepare("
        UPDATE auth_accounts 
        SET password = ?
        WHERE id = ?
    ");
    $stmt->execute([$passwordHash, $authId]);

    // Revoke the reset token
    $pdo->prepare("UPDATE auth_tokens SET revoked = 1 WHERE id = ?")->execute([$tokenRecord['id']]);

    echo json_encode([
        'success' => true,
        'message' => 'Your password has been reset successfully. You can now log in.'
    ]);

} catch (PDOException $e) {
    error_log("Reset password error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred. Please try again later.']);
}
