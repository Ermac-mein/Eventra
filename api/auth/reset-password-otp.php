<?php

header('Content-Type: application/json');
require_once __DIR__ . '/../../config/database.php';

$data = json_decode(file_get_contents("php://input"), true);
$email = $data['email'] ?? null;
$password = $data['password'] ?? null;

if (!$email || !$password) {
    echo json_encode(['success' => false, 'message' => 'Email and password are required.']);
    exit;
}

if (strlen($password) < 8) {
    echo json_encode(['success' => false, 'message' => 'Password must be at least 8 characters.']);
    exit;
}

try {
    // Get the user account (any role)
    $stmt = $pdo->prepare("SELECT id, role FROM auth_accounts WHERE email = ? AND deleted_at IS NULL");
    $stmt->execute([$email]);
    $account = $stmt->fetch();

    if (!$account) {
        echo json_encode(['success' => false, 'message' => 'Account not found.']);
        exit;
    }

    // Update password
    $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
    $stmt = $pdo->prepare("UPDATE auth_accounts SET password = ? WHERE id = ?");
    $stmt->execute([$hashedPassword, $account['id']]);

    // Revoke all OTP tokens for this account
    $stmt = $pdo->prepare("UPDATE auth_tokens SET revoked = 1 WHERE auth_id = ? AND type = 'otp'");
    $stmt->execute([$account['id']]);

    echo json_encode([
        'success' => true, 
        'message' => 'Password updated successfully.',
        'role' => $account['role']
    ]);
} catch (PDOException $e) {
    error_log("Password reset error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error occurred.']);
}
?>
