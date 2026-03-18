<?php
header('Content-Type: application/json');
require_once '../../config/database.php';

$data = json_decode(file_get_contents("php://input"), true);

if (!isset($data['identity']) && !isset($data['email'])) {
    echo json_encode(['success' => false, 'message' => 'Email or Phone Number is required.']);
    exit;
}

$identity = $data['identity'] ?? $data['email'];

try {
    require_once '../../includes/helpers/entity-resolver.php';
    
    // Resolve user by identity (email or phone)
    $user = resolveEntity($identity);

    if ($user && isset($user['id']) && $user['role'] === 'client') {
        $auth_id = $user['id'];
        
        // Ensure we have a phone number for OTP
        $phone = $user['phone'] ?? null;

        if (!$phone) {
            echo json_encode(['success' => false, 'message' => 'No phone number associated with this account. Please contact support.']);
            exit;
        }

        // Generate 6-digit OTP
        $otp = sprintf("%06d", mt_rand(0, 999999));
        $expires_at = date('Y-m-d H:i:s', strtotime('+15 minutes'));

        // Revoke any existing OTPs for this user
        $pdo->prepare("UPDATE auth_tokens SET revoked = 1 WHERE auth_id = ? AND type = 'otp'")->execute([$auth_id]);

        // Save OTP (we'll store the raw OTP for simplicity in this demo, but ideally hash it)
        $stmt = $pdo->prepare("INSERT INTO auth_tokens (auth_id, token, type, expires_at) VALUES (?, ?, 'otp', ?)");
        $stmt->execute([$auth_id, $otp, $expires_at]);

        // Send SMS
        require_once '../../includes/helpers/sms-helper.php';
        $message = "Your Eventra password reset OTP is: $otp. Valid for 15 minutes.";
        $smsResult = sendSMS($phone, $message);

        if ($smsResult['success']) {
            echo json_encode([
                'success' => true,
                'message' => 'A 6-digit OTP has been sent to your registered phone number.',
                'debug_otp' => $otp // REMOVE IN PRODUCTION
            ]);
        } else {
            // If SMS fails, we still returned success but maybe log internal error
            error_log("OTP SMS failed for $identity: " . $smsResult['message']);
            echo json_encode([
                'success' => false, 
                'message' => 'Failed to send OTP. Please try again later.'
            ]);
        }
    } else {
        // Standard security practice: don't reveal if identity exists
        echo json_encode(['success' => true, 'message' => 'If this identity is registered, you will receive an OTP shortly.']);
    }
} catch (PDOException $e) {
    error_log("Forgot Password Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error.']);
}
