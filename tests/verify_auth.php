<?php
require_once 'config/database.php';
require_once 'includes/helpers/entity-resolver.php';

function test_admin_login()
{
    global $pdo;
    echo "Testing Admin Login...\n";
    $email = 'admin123@gmail.com';
    $password = 'admin@@12345'; // Derived from earlier research

    $user = resolveEntity($email);
    if (!$user) {
        echo "FAILED: Admin user not found in database.\n";
        return;
    }

    if (password_verify($password, $user['password'])) {
        echo "SUCCESS: Admin login verified.\n";
        echo "Admin Name: " . $user['name'] . "\n";
    } else {
        echo "FAILED: Admin password verification failed.\n";
    }
}

function test_client_registration_and_login()
{
    global $pdo;
    echo "\nTesting Client Registration...\n";
    $email = 'test_client_' . time() . '@example.com';
    $password = 'Password123!';
    $name = 'Test Client';

    // Mock register.php logic
    try {
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("INSERT INTO auth_accounts (email, password_hash, role, auth_provider, is_active) VALUES (?, ?, 'client', 'local', 1)");
        $stmt->execute([$email, $hashedPassword]);
        $auth_id = $pdo->lastInsertId();

        $stmt = $pdo->prepare("INSERT INTO clients (auth_id, business_name, name, email, password) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$auth_id, $name, $name, $email, $hashedPassword]);

        $pdo->commit();
        echo "SUCCESS: Client registered.\n";
    } catch (Exception $e) {
        $pdo->rollBack();
        echo "FAILED: Client registration failed: " . $e->getMessage() . "\n";
        return;
    }

    echo "Testing Client Login...\n";
    $user = resolveEntity($email);
    if ($user && password_verify($password, $user['password'])) {
        echo "SUCCESS: Client login verified.\n";
        echo "Client Name: " . $user['name'] . "\n";
    } else {
        echo "FAILED: Client login verification failed.\n";
    }
}

test_admin_login();
test_client_registration_and_login();
