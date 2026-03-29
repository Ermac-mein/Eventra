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
    // Check if email exists in auth_accounts and is a client
    $stmt = $pdo->prepare("SELECT id, username FROM auth_accounts WHERE email = ? AND role = 'client' AND deleted_at IS NULL");
    $stmt->execute([$email]);
    $account = $stmt->fetch();

    if (!$account) {
        // Security best practice: Don't reveal if email exists
        echo json_encode(['success' => true, 'message' => 'If a client account exists with this email, you will receive an OTP code shortly.']);
        exit;
    }

    // Generate 6-digit OTP
    $otpCode = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

    // Invalidate previous OTP tokens
    $stmt = $pdo->prepare("UPDATE auth_tokens SET revoked = 1 WHERE auth_id = ? AND type = 'otp'");
    $stmt->execute([$account['id']]);

    // Store OTP token (expires in 10 minutes using database time)
    $stmt = $pdo->prepare("INSERT INTO auth_tokens (auth_id, token, type, expires_at) VALUES (?, ?, 'otp', DATE_ADD(NOW(), INTERVAL 10 MINUTE))");
    $stmt->execute([$account['id'], $otpCode]);

    // Send OTP via email
    $subject = "Your Eventra Password Reset OTP";
    $body = "
        <div style='font-family: sans-serif; max-width: 600px; margin: auto; padding: 20px; border: 1px solid #eee; border-radius: 12px;'>
            <h2 style='color: #7c3aed;'>Password Reset Request</h2>
            <p>Hi there,</p>
            <p>We received a request to reset your Eventra password. Use the code below to verify your identity. This code will expire in 10 minutes.</p>
            <div style='text-align: center; margin: 30px 0;'>
                <div style='font-size: 32px; font-weight: bold; letter-spacing: 5px; color: #7c3aed; font-family: monospace;'>$otpCode</div>
            </div>
            <p style='color: #6b7280; font-size: 14px;'>Do not share this code with anyone. If you didn't request this, you can safely ignore this email.</p>
            <hr style='border: 0; border-top: 1px solid #eee; margin: 20px 0;'>
            <p style='font-size: 12px; color: #9ca3af; text-align: center;'>&copy; " . date('Y') . " Eventra.</p>
        </div>
    ";

    $emailResult = sendEmail($email, $subject, $body);

    if ($emailResult['success']) {
        echo json_encode(['success' => true, 'message' => 'OTP has been sent to your email.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to send OTP. Please try again later.']);
    }
} catch (PDOException $e) {
    error_log("Forgot password error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error occurred.']);
}
