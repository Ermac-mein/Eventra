<?php
/**
 * Verify Payment API
 * Verifies a Paystack payment reference server-side.
 * NEVER trust the client — always verify with Paystack directly.
 */
header('Content-Type: application/json');
require_once '../../config/database.php';
require_once '../../config/payment.php';
require_once '../../includes/middleware/auth.php';

// Must be authenticated
$auth_id = checkAuth('user');

$data = json_decode(file_get_contents('php://input'), true);
$reference = $data['reference'] ?? null;

if (!$reference) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Payment reference is required.']);
    exit;
}

// Guard against duplicate verification (idempotent)
$stmt = $pdo->prepare("SELECT id, status, amount, event_id FROM payments WHERE reference = ?");
$stmt->execute([$reference]);
$existing = $stmt->fetch();

if ($existing && $existing['status'] === 'paid') {
    echo json_encode([
        'success' => true,
        'status' => 'success',
        'message' => 'Payment already verified.',
        'amount' => (float) $existing['amount'],
        'event_id' => $existing['event_id'],
        'reference' => $reference
    ]);
    exit;
}

// Verify with Paystack
$url = "https://api.paystack.co/transaction/verify/" . rawurlencode($reference);
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 15);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: Bearer " . PAYSTACK_SECRET_KEY,
    "Cache-Control: no-cache",
]);
$response = curl_exec($ch);
$curlError = curl_error($ch);
// curl_close($ch); is deprecated in PHP 8.4+ and no longer needed.

if ($curlError || !$response) {
    http_response_code(502);
    echo json_encode(['success' => false, 'message' => 'Could not reach payment gateway. Please try again.']);
    exit;
}

$result = json_decode($response, true);

if (!$result || !$result['status'] || ($result['data']['status'] ?? '') !== 'success') {
    echo json_encode([
        'success' => false,
        'status' => $result['data']['status'] ?? 'unknown',
        'message' => 'Payment verification failed. Transaction not successful.'
    ]);
    exit;
}

$paystackData = $result['data'];
$amountPaid = $paystackData['amount'] / 100; // Convert kobo to naira

echo json_encode([
    'success' => true,
    'status' => 'success',
    'message' => 'Payment verified successfully.',
    'reference' => $reference,
    'amount' => $amountPaid,
    'currency' => $paystackData['currency'] ?? 'NGN',
    'channel' => $paystackData['channel'] ?? 'card',
    'paid_at' => $paystackData['paid_at'] ?? null,
]);
