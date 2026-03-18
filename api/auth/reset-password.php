<?php
header('Content-Type: application/json');
require_once '../../config/database.php';

$data = json_decode(file_get_contents("php://input"), true);

if (!isset($data['reset_token']) || !isset($data['new_password'])) {
    echo json_encode(['success' => false, 'message' => 'Reset token and new password are required.']);
    exit;
}

$reset_token = $data['reset_token'];
$new_password = $data['new_password'];

if (!preg_match('/^(?=.*[A-Z])(?=.*[0-9])(?=.*[!@#$%^&*(),.?":{}|<>]).{8,}$/', $new_password)) {
    echo json_encode([
        'success' => false, 
        'message' => 'Password must be at least 8 characters long and include one uppercase letter, one digit, and one special character.'
    ]);
    exit;
}

try {
    // Verify reset token
    $stmt = $pdo->prepare("
        SELECT auth_id, id FROM auth_tokens 
        WHERE token = ? AND type = 'reset_password' 
        AND revoked = 0 AND expires_at > NOW()
        LIMIT 1
    ");
    $stmt->execute([$reset_token]);
    $token_row = $stmt->fetch();

    if ($token_row) {
        $auth_id = $token_row['auth_id'];
        $password_hash = password_hash($new_password, PASSWORD_DEFAULT);

        // Begin transaction
        $pdo->beginTransaction();

        // Update auth_accounts
        $stmt = $pdo->prepare("UPDATE auth_accounts SET password_hash = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$password_hash, $auth_id]);

        // Also update legacy password fields in users/clients/admins if they exist
        // (The system seems to have duplicated password fields in profile tables)
        
        // Update users
        $pdo->prepare("UPDATE users SET updated_at = NOW() WHERE user_auth_id = ?")->execute([$auth_id]);
        
        // Update clients (Note: clients table has a 'password' column, while auth_accounts has 'password_hash')
        $pdo->prepare("UPDATE clients SET password = ?, updated_at = NOW() WHERE client_auth_id = ?")
            ->execute([$password_hash, $auth_id]);

        // Update admins
        $pdo->prepare("UPDATE admins SET password = ?, updated_at = NOW() WHERE admin_auth_id = ?")
            ->execute([$password_hash, $auth_id]);

        // Revoke the reset token
        $pdo->prepare("UPDATE auth_tokens SET revoked = 1 WHERE id = ?")->execute([$token_row['id']]);

        $pdo->commit();

        echo json_encode([
            'success' => true,
            'message' => 'Password has been reset successfully. You can now log in.'
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid or expired reset token.']);
    }
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Reset Password Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred while resetting your password.']);
}
