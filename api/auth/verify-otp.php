<?php
header('Content-Type: application/json');
require_once '../../config/database.php';

$data = json_decode(file_get_contents("php://input"), true);

if ((!isset($data['identity']) && !isset($data['email'])) || !isset($data['otp'])) {
    echo json_encode(['success' => false, 'message' => 'Identity and OTP are required.']);
    exit;
}

$identity = $data['identity'] ?? $data['email'];
$otp = $data['otp'];

try {
    require_once '../../includes/helpers/entity-resolver.php';
    
    // Resolve user by identity (email or phone)
    $user = resolveEntity($identity, 'client');
    $auth_id = $user['id'] ?? null;

    if (!$auth_id) {
        echo json_encode(['success' => false, 'message' => 'Invalid request.']);
        exit;
    }

    // Verify OTP
    $stmt = $pdo->prepare("
        SELECT id FROM auth_tokens 
        WHERE auth_id = ? AND token = ? AND type = 'otp' 
        AND revoked = 0 AND expires_at > NOW()
        ORDER BY created_at DESC LIMIT 1
    ");
    $stmt->execute([$auth_id, $otp]);
    $token_row = $stmt->fetch();

    if ($token_row) {
        // OTP is valid. Revoke it.
        $pdo->prepare("UPDATE auth_tokens SET revoked = 1 WHERE id = ?")->execute([$token_row['id']]);

        // Generate a temporary reset token
        $reset_token = bin2hex(random_bytes(32));
        $expires_at = date('Y-m-d H:i:s', strtotime('+30 minutes'));

        $stmt = $pdo->prepare("INSERT INTO auth_tokens (auth_id, token, type, expires_at) VALUES (?, ?, 'reset_password', ?)");
        $stmt->execute([$auth_id, $reset_token, $expires_at]);

        echo json_encode([
            'success' => true,
            'message' => 'OTP verified successfully.',
            'reset_token' => $reset_token
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid or expired OTP.']);
    }
} catch (PDOException $e) {
    error_log("Verify OTP Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error.']);
}
