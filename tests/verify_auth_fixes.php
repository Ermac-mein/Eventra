<?php
/**
 * Verification Script for Auth and Session Fixes
 * Tests session siloing and role resolution
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session-config.php';
require_once __DIR__ . '/../includes/middleware/auth.php';

header('Content-Type: text/plain');

echo "--- Eventra Auth Verification ---\n\n";

// Test 1: Session Name Generation
echo "Test 1: Session Name Generation\n";
$_SERVER['REQUEST_URI'] = '/admin/dashboard.php';
$adminSess = getEventraSessionName();
echo "Admin URI -> $adminSess " . ($adminSess === 'EVENTRA_ADMIN_SESS' ? "[PASS]" : "[FAIL]") . "\n";

$_SERVER['REQUEST_URI'] = '/client/events.php';
$clientSess = getEventraSessionName();
echo "Client URI -> $clientSess " . ($clientSess === 'EVENTRA_CLIENT_SESS' ? "[PASS]" : "[FAIL]") . "\n";

$_SERVER['REQUEST_URI'] = '/api/stats/get-admin-stats.php';
$apiAdminSess = getEventraSessionName();
echo "Admin API URI -> $apiAdminSess " . ($apiAdminSess === 'EVENTRA_ADMIN_SESS' ? "[PASS]" : "[FAIL]") . "\n";

// Test 2: Database Role Consistency
echo "\nTest 2: Database Role Consistency\n";
try {
    $stmt = $pdo->query("SELECT id, email, role FROM auth_accounts LIMIT 5");
    $accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "Sample Accounts Found: " . count($accounts) . "\n";
    foreach ($accounts as $acc) {
        echo " - ID {$acc['id']}: {$acc['email']} ({$acc['role']})\n";
    }
} catch (Exception $e) {
    echo "DB Error: " . $e->getMessage() . "\n";
}

// Test 3: Client ID Resolution
echo "\nTest 3: Client ID Resolution\n";
try {
    $stmt = $pdo->query("SELECT c.id as client_id, a.email FROM clients c JOIN auth_accounts a ON c.auth_id = a.id LIMIT 3");
    $clients = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($clients as $client) {
        echo " - Client ID {$client['client_id']} maps to {$client['email']}\n";
    }
} catch (Exception $e) {
    echo "DB Error: " . $e->getMessage() . "\n";
}

echo "\nVerification complete.\n";
