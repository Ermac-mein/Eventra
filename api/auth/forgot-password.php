<?php

header('Content-Type: application/json');
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/helpers/email-helper.php';

$data = json_decode(file_get_contents("php://input"), true);
$email = $data['email'] ?? null;

if (!$email) {
    echo json_encode(['success' => false, 'message' => 'Email is required.']);
    exit;
}

try {
    // 1. Check if email exists in auth_accounts and is a client
    $stmt = $pdo->prepare("SELECT id, username FROM auth_accounts WHERE email = ? AND role = 'client' AND deleted_at IS NULL");
    $stmt->execute([$email]);
    $account = $stmt->fetch();

    if (!$account) {
        // Security best practice: Don't reveal if email exists, BUT if the role is restricted,
        // we can still say success to keep the UX smooth and not reveal account existence for other roles
        echo json_encode(['success' => true, 'message' => 'If a client account exists with this email, you will receive a password reset link shortly.']);
        exit;
    }

    // 2. Generate secure token
    $token = bin2hex(random_bytes(32));
    $expires_at = date('Y-m-d H:i:s', strtotime('+1 hour'));

    // 3. Invalidate previous tokens
    $stmt = $pdo->prepare("UPDATE auth_tokens SET revoked = 1 WHERE auth_id = ? AND type = 'reset_password'");
    $stmt->execute([$account['id']]);

    // 4. Store token
    $stmt = $pdo->prepare("INSERT INTO auth_tokens (auth_id, token, type, expires_at) VALUES (?, ?, 'reset_password', ?)");
    $stmt->execute([$account['id'], $token, $expires_at]);

    // 5. Build reset link
    $appUrl = rtrim($_ENV['APP_URL'] ?? 'http://' . $_SERVER['HTTP_HOST'], '/');
    $resetLink = "{$appUrl}/public/pages/reset-password.html?token=" . $token;

    // 6. Send Email
    $subject = "Reset Your Eventra Password";
    $body = "
        <div style='font-family: sans-serif; max-width: 600px; margin: auto; padding: 20px; border: 1px solid #eee; border-radius: 12px;'>
            <h2 style='color: #7c3aed;'>Password Reset Request</h2>
            <p>Hi there,</p>
            <p>We received a request to reset your Eventra password. Click the button below to choose a new one. This link will expire in 1 hour.</p>
            <div style='text-align: center; margin: 30px 0;'>
                <a href='{$resetLink}' style='background: #7c3aed; color: white; padding: 12px 24px; text-decoration: none; border-radius: 8px; font-weight: bold;'>Reset Password</a>
            </div>
            <p style='color: #6b7280; font-size: 14px;'>If you didn't request this, you can safely ignore this email.</p>
            <hr style='border: 0; border-top: 1px solid #eee; margin: 20px 0;'>
            <p style='font-size: 12px; color: #9ca3af; text-align: center;'>&copy; " . date('Y') . " Eventra.</p>
        </div>
    ";

    $emailResult = sendEmail($email, $subject, $body);

    if ($emailResult['success']) {
        echo json_encode(['success' => true, 'message' => 'Password reset link has been sent to your email.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to send reset email. Please try again later.']);
    }
} catch (PDOException $e) {
    error_log("Forgot password error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error occurred.']);
}
