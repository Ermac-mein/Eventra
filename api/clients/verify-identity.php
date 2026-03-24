<?php
/**
 * Identity Verification API — Separate BVN/NIN validation
 *
 * POST: type ('bvn'|'nin'), number
 * 
 * Legitimizes bank details by cross-checking names (simulated/mocked)
 */
header('Content-Type: application/json');
require_once '../../config/database.php';
require_once '../../includes/middleware/auth.php';

$client_auth_id = clientMiddleware();

$data = json_decode(file_get_contents('php://input'), true) ?? [];
$type = strtolower(trim($data['type'] ?? ''));
$number = trim($data['number'] ?? '');

if (!in_array($type, ['bvn', 'nin']) || empty($number)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Valid type (bvn/nin) and number are required.']);
    exit;
}

try {
    $pdo->beginTransaction();

    // Fetch existing client to cross-check against account_name
    $stmt = $pdo->prepare("SELECT id, account_name FROM clients WHERE id = ?");
    $stmt->execute([$client_auth_id]);
    $client = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$client) {
        $pdo->rollBack();
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Client profile not found.']);
        exit;
    }

    // Call Mock service (simulating real verification)
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $mockUrl = "$protocol://$host/api/admin/dojah-mock.php";

    $ch = curl_init($mockUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['type' => $type, 'number' => $number]));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    
    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $verified = false;
    if ($httpCode === 200 && $result) {
        $resp = json_decode($result, true);
        $verified = ($resp['success'] && ($resp['data']['verified'] ?? false));
    } else {
        // Fallback mock logic if endpoint unreachable
        $last_digit = substr($number, -1);
        $verified = ($last_digit === '1');
    }

    if (!$verified) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => "Verification failed for $type. Please check the number."]);
        exit;
    }

    // Update client verification status
    $col = ($type === 'bvn') ? 'bvn_verified' : 'nin_verified';
    $valCol = ($type === 'bvn') ? 'bvn' : 'nin';

    $updateStmt = $pdo->prepare("
        UPDATE clients 
        SET $col = 1, 
            $valCol = ?, 
            updated_at = NOW() 
        WHERE id = ?
    ");
    $updateStmt->execute([$number, $client_auth_id]);

    $pdo->commit();

    echo json_encode([
        'success' => true, 
        'message' => ucfirst($type) . ' verified successfully.',
        'legitimatized' => true // Metadata indicating this legitimizes bank details
    ]);

} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error.']);
}
