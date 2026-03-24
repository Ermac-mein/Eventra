<?php
/**
 * Verify OTP API
 * Verifies the provided OTP — single-use, time-sensitive enforcement
 */
header('Content-Type: application/json');
require_once '../../config/database.php';
require_once '../../includes/middleware/auth.php';

// Use standardized auth middleware
$auth_id = checkAuth('user');

// Use auth_id directly as user_id
$user_id = $auth_id;

$data = json_decode(file_get_contents("php://input"), true);
$otp = $data['otp'] ?? '';
$payment_reference = $data['payment_reference'] ?? '';

if (empty($otp) || empty($payment_reference)) {
    echo json_encode(['success' => false, 'message' => 'OTP and payment reference are required.']);
    exit;
}

try {
    // 1. Fetch the latest valid OTP for this user and reference
    $stmt = $pdo->prepare("SELECT * FROM payment_otps WHERE user_id = ? AND payment_reference = ? ORDER BY created_at DESC LIMIT 1");
    $stmt->execute([$user_id, $payment_reference]);
    $record = $stmt->fetch();

    if (!$record) {
        echo json_encode(['success' => false, 'message' => 'No OTP found for this transaction. Please request a new one.']);
        exit;
    }

    // 2. Single-use enforcement: reject already-verified OTPs
    if (!empty($record['verified_at'])) {
        echo json_encode(['success' => false, 'message' => 'This OTP has already been used. Please request a new one for a new transaction.']);
        exit;
    }

    // 3. Check expiration (5-minute limit)
    if (strtotime($record['expires_at']) < time()) {
        echo json_encode(['success' => false, 'message' => 'OTP has expired. Please request a new one.', 'expired' => true]);
        exit;
    }

    // 4. Check attempt count (max 5 attempts before lock)
    if ($record['attempts'] >= 5) {
        echo json_encode(['success' => false, 'message' => 'Too many failed attempts. This OTP is locked. Please request a new one.']);
        exit;
    }

    // 5. Verify OTP hash
    if (password_verify($otp, $record['otp_hash'])) {
        // Mark as verified (single-use: set verified_at)
        $stmt = $pdo->prepare("UPDATE payment_otps SET verified_at = NOW(), attempts = attempts + 1 WHERE id = ?");
        $stmt->execute([$record['id']]);

        // Store verification in session for cross-request validation
        $_SESSION['otp_verified_ref'] = $payment_reference;
        $_SESSION['otp_verified_at'] = time();

        echo json_encode(['success' => true, 'message' => 'OTP verified successfully. You may now complete your payment.']);
    } else {
        // Increment failed attempts
        $stmt = $pdo->prepare("UPDATE payment_otps SET attempts = attempts + 1 WHERE id = ?");
        $stmt->execute([$record['id']]);

        $remaining = max(0, 4 - $record['attempts']);
        echo json_encode([
            'success' => false,
            'message' => 'Invalid OTP code.',
            'remaining_attempts' => $remaining
        ]);
    }

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
