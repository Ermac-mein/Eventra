<?php
/**
 * Verification Script for Strict Authentication System
 * This script tests the core logic of the authentication system.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers/entity-resolver.php';

function test($description, $callback)
{
    echo "Testing: $description... ";
    try {
        if ($callback()) {
            echo "\033[32mPASSED\033[0m\n";
        } else {
            echo "\033[31mFAILED\033[0m\n";
        }
    } catch (Exception $e) {
        echo "\033[31mERROR: " . $e->getMessage() . "\033[0m\n";
    }
}

echo "--- Authentication System Verification ---\n";

// 1. Role and Method Polices
test("Admin MUST use password", function () {
    $policy = getAuthPolicy('admin', 'password');
    return $policy['allowed'] === true;
});

test("Admin MUST NOT use Google", function () {
    $policy = getAuthPolicy('admin', 'google');
    return $policy['allowed'] === false && strpos($policy['message'], 'restricted') !== false;
});

test("User MUST use Google", function () {
    $policy = getAuthPolicy('user', 'google');
    return $policy['allowed'] === true;
});

test("User MUST NOT use password", function () {
    $policy = getAuthPolicy('user', 'password');
    return $policy['allowed'] === false && strpos($policy['message'], 'Google Sign-In only') !== false;
});

// 2. Identity Binding
test("Client identity binding (Password)", function () {
    $user = ['role' => 'client', 'auth_method' => 'password'];
    $policy = getAuthPolicy('client', 'password', $user);
    return $policy['allowed'] === true;
});

test("Client identity binding rejection (Google if bound to Password)", function () {
    $user = ['role' => 'client', 'auth_method' => 'password'];
    $policy = getAuthPolicy('client', 'google', $user);
    return $policy['allowed'] === false && strpos($policy['message'], 'bound to Password') !== false;
});

// 3. Registration Rules
test("Block registration for existing identity", function () {
    global $pdo;
    // We assume the admin email exists from seed.sql
    $res = canRegisterAs('admin123@gmail.com', 'user');
    return $res['success'] === false && strpos($res['message'], 'already bound') !== false;
});

// 4. Internal ID generation
test("Internal ID format", function () {
    $id = generateInternalId();
    return preg_match('/^ACC-\d{8}-[A-F0-9]{6}$/', $id);
});

echo "--- Verification Complete ---\n";
