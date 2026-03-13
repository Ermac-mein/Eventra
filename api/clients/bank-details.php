<?php
/**
 * Bank Details API — Organizer Paystack Subaccount Onboarding
 *
 * POST: bank_code, account_number, bank_name (display label)

 */
header('Content-Type: application/json');
require_once '../../config/database.php';
require_once '../../config/payment.php';
require_once '../../includes/middleware/auth.php';
require 'vendor/autoload.php';

$client_auth_id = clientMiddleware();

// ── GET: Resolve Account Only ───────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $bank_code      = trim($_GET['bank_code']      ?? '');
    $account_number = trim($_GET['account_number'] ?? '');

    if (empty($bank_code) || empty($account_number)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'bank_code and account_number are required.']);
        exit;
    }

    $resolveRes = paystackRequest('GET', "/bank/resolve?account_number={$account_number}&bank_code={$bank_code}");

    if (!$resolveRes['ok'] || !($resolveRes['body']['status'] ?? false)) {
        $errMsg = $resolveRes['body']['message'] ?? $resolveRes['error'] ?? 'Account resolution failed.';
        echo json_encode(['success' => false, 'message' => $errMsg]);
        exit;
    }

    echo json_encode([
        'success'      => true,
        'account_name' => $resolveRes['body']['data']['account_name'] ?? 'Unknown',
    ]);
    exit;
}

// ── Helper: call Paystack ────────────────────────────────────────────────────
function paystackRequest(string $method, string $path, array $payload = []): array
{
    $secretKey = defined('PAYSTACK_SECRET_KEY') ? PAYSTACK_SECRET_KEY : '';
    
    // Masked key for logging: show first 4 and last 4
    $maskedKey = !empty($secretKey) 
        ? substr($secretKey, 0, 4) . '...' . substr($secretKey, -4) 
        : 'MISSING';
    
    $url = 'https://api.paystack.co' . $path;
    $ch  = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $secretKey,
        'Content-Type: application/json',
        'Cache-Control: no-cache',
    ]);

    error_log("[Paystack API] [{$method}] {$path} - Key prefix: {$maskedKey}");

    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    } elseif ($method === 'PUT') {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    }

    $response  = curl_exec($ch);
    $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);

    if ($curlError || !$response) {
        error_log("[Paystack API] [Error] Curl: " . ($curlError ?: 'Empty response'));
        return ['ok' => false, 'code' => $httpCode, 'body' => null, 'error' => $curlError ?: 'Empty response'];
    }

    $result = json_decode($response, true);
    if ($httpCode === 401) {
        error_log("[Paystack API] [Error] 401 Unauthorized - Check your PAYSTACK_SECRET_KEY");
    }

    return ['ok' => ($httpCode >= 200 && $httpCode < 300), 'code' => $httpCode, 'body' => $result, 'error' => null];
}

try {
    $pdo->beginTransaction();

    // ── Fetch existing client data ───────────────────────────────────────────
    $stmt = $pdo->prepare("
        SELECT id, business_name, email, nin_verified, bvn_verified, subaccount_code, verification_status
        FROM clients WHERE client_auth_id = ?
    ");
    $stmt->execute([$client_auth_id]);
    $client = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$client) {
        $pdo->rollBack();
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Client profile not found.']);
        exit;
    }

    // LOCKING: If subaccount exists and is verified, require admin approval to change
    if ($client['subaccount_code'] && $client['verification_status'] === 'verified') {
        // Allow the same bank details but block changes
        $stmt_check = $pdo->prepare("SELECT account_number, bank_code FROM clients WHERE client_auth_id = ?");
        $stmt_check->execute([$client_auth_id]);
        $existing = $stmt_check->fetch();
        
        if ($existing['account_number'] !== $account_number || $existing['bank_code'] !== $bank_code) {
            $pdo->rollBack();
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Bank details are locked. Please contact support to change your verified account.']);
            exit;
        }
    }

    // ── Input ────────────────────────────────────────────────────────────────
    $data = json_decode(file_get_contents('php://input'), true) ?? [];
    $bank_code      = trim($data['bank_code']      ?? $_POST['bank_code']      ?? '');
    $account_number = trim($data['account_number'] ?? $_POST['account_number'] ?? '');
    $bank_name      = trim($data['bank_name']      ?? $_POST['bank_name']      ?? '');

    if (empty($bank_code) || empty($account_number)) {
        $pdo->rollBack();
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Bank details missing in update request.']);
        exit;
    }

    // ── Step 1: Resolve account name ────────────────────────────────────────
    $resolveRes = paystackRequest('GET', "/bank/resolve?account_number={$account_number}&bank_code={$bank_code}");

    if (!$resolveRes['ok'] || !($resolveRes['body']['status'] ?? false)) {
        $pdo->rollBack();
        $errMsg = $resolveRes['body']['message'] ?? $resolveRes['error'] ?? 'Account resolution failed.';
        echo json_encode(['success' => false, 'message' => "Could not verify account: {$errMsg}"]);
        exit;
    }

    $account_name = $resolveRes['body']['data']['account_name'] ?? 'Unknown';

    $subRes = ensureSubaccount(
        $pdo, 
        $client_auth_id, 
        $bank_code, 
        $account_number, 
        $account_name, 
        $email, 
        $existingSubCode
    );

    if (!$subRes['success']) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => $subRes['message']]);
        exit;
    }

    $subaccount_code = $subRes['subaccount_code'];
    $subaccount_id   = null; // handled inside ensureSubaccount now

    // IMPORTANT: Any profile/bank change resets status to pending for admin review
    $verification_status = 'pending';

    // ── Step 4: Save to DB ──────────────────────────────────────────────────
    $updateStmt = $pdo->prepare("
        UPDATE clients
        SET bank_code           = ?,
            account_number      = ?,
            account_name        = ?,
            bank_name           = ?,
            subaccount_code     = ?,
            subaccount_id       = ?,
            verification_status = ?,
            updated_at          = NOW()
        WHERE client_auth_id = ?
    ");
    $updateStmt->execute([
        $bank_code,
        $account_number,
        $account_name,
        $bank_name ?: $bank_code,
        $subaccount_code,
        $subaccount_id,
        $verification_status,
        $client_auth_id,
    ]);

    // ── Fetch updated client to return ──────────────────────────────────────
    $fetchStmt = $pdo->prepare("
        SELECT c.*, a.email AS auth_email
        FROM clients c
        JOIN auth_accounts a ON c.client_auth_id = a.id
        WHERE c.client_auth_id = ?
    ");
    $fetchStmt->execute([$client_auth_id]);
    $updated = $fetchStmt->fetch(PDO::FETCH_ASSOC);
    unset($updated['password']);

    $pdo->commit();

    echo json_encode([
        'success'             => true,
        'message'             => 'Bank details saved successfully.',
        'account_name'        => $account_name,
        'subaccount_code'     => $subaccount_code,
        'verification_status' => $verification_status,
        'user'                => $updated,
    ]);

} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('[bank-details.php] DB error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error. Please try again.']);
}
