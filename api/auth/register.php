<?php
/**
 * Eventra — User Registration Handler
 * Handles creation of new accounts for Admins, Clients, and Users.
 */

// 0. Emergency Error Capture for 500 debugging
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    if (!(error_reporting() & $errno)) return false;
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Internal Server Error detected by handler.',
        'error' => $errstr,
        'file' => basename($errfile),
        'line' => $errline
    ]);
    exit;
});

set_exception_handler(function($e) {
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Uncaught Exception detected by handler.',
        'error' => $e->getMessage(),
        'type' => get_class($e),
        'file' => basename($e->getFile()),
        'line' => $e->getLine()
    ]);
    exit;
});

header('Content-Type: application/json');

// Check if dependencies exist before requiring
$db_path = __DIR__ . '/../../config/database.php';
$resolver_path = __DIR__ . '/../../includes/helpers/entity-resolver.php';

if (!file_exists($db_path)) {
    echo json_encode(['success' => false, 'message' => 'Configuration error: database file missing.']);
    exit;
}
require_once $db_path;

if (!file_exists($resolver_path)) {
    echo json_encode(['success' => false, 'message' => 'Configuration error: resolver helper missing.']);
    exit;
}
require_once $resolver_path;

$data = json_decode(file_get_contents("php://input"), true);

if (!$data) {
    echo json_encode(['success' => false, 'message' => 'Invalid JSON input.']);
    exit;
}

if (!isset($data['name']) || !isset($data['email']) || !isset($data['password'])) {
    echo json_encode(['success' => false, 'message' => 'All fields are required.']);
    exit;
}

$name = trim($data['name']);
$email = trim($data['email']);
$password = $data['password'];
$role = $data['role'] ?? 'client'; // Default to client registration if not specified

try {
    // 1. Prevent Cross-Entity Collision
    $registrability = canRegisterAs($email, $role);
    if (!$registrability['success']) {
        logSecurityEvent(null, $email, 'registration_failure', 'password', "Registration blocked: " . $registrability['message']);
        echo json_encode(['success' => false, 'message' => $registrability['message']]);
        exit;
    }

    // 2. Load ID Generator
    $id_gen_path = __DIR__ . '/../utils/id-generator.php';
    if (!file_exists($id_gen_path)) {
         throw new Exception("ID generator utility missing at $id_gen_path");
    }
    require_once $id_gen_path;

    // 3. Validate Password Strength (Uppercase, Digit, Special Character, Min 8 chars)
    if (!preg_match('/^(?=.*[A-Z])(?=.*[0-9])(?=.*[!@#$%^&*(),.?":{}|<>]).{8,}$/', $password)) {
        echo json_encode([
            'success' => false,
            'message' => 'Password must be at least 8 characters long and include one uppercase letter, one digit, and one special character.'
        ]);
        exit;
    }

    // 4. Hash Password
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    $pdo->beginTransaction();

    // 4. Insert into auth_accounts
    // Using email prefix + random hex as username
    $username = explode('@', $email)[0] . '_' . substr(bin2hex(random_bytes(2)), 0, 4);
    $stmt = $pdo->prepare("INSERT INTO auth_accounts (email, password, role, auth_provider, is_active, username) VALUES (?, ?, ?, 'local', 1, ?)");
    $stmt->execute([$email, $hashedPassword, $role, $username]);
    $auth_id = $pdo->lastInsertId();

    $role_id = null;
    $customId = null;

    // 5. Insert into role-specific table with custom_id
    if ($role === 'client') {
        $customId = generateClientId($pdo);
        $stmt = $pdo->prepare("INSERT INTO clients (client_auth_id, custom_id, business_name, name) VALUES (?, ?, ?, ?)");
        $stmt->execute([$auth_id, $customId, $name, $name]);
    } elseif ($role === 'admin') {
        $stmt = $pdo->prepare("INSERT INTO admins (admin_auth_id, name) VALUES (?, ?)");
        $stmt->execute([$auth_id, $name]);
        $role_id = $pdo->lastInsertId();
    } elseif ($role === 'user') {
        $customId = generateUserId($pdo);
        $stmt = $pdo->prepare("INSERT INTO users (user_auth_id, custom_id, name) VALUES (?, ?, ?)");
        $stmt->execute([$auth_id, $customId, $name]);
        $role_id = $pdo->lastInsertId();
    }

    $pdo->commit();

    logSecurityEvent($auth_id, $email, 'registration_success', 'password', "New $role registered: $name (Role ID: " . ($role === 'client' ? $customId : ($role_id ?? 'N/A')) . ")");

    // 6. Notify Admin and User using helper
    $notify_path = __DIR__ . '/../utils/notification-helper.php';
    if (file_exists($notify_path)) {
        require_once $notify_path;
        
        $admin_id = getAdminUserId();
        if ($admin_id) {
            $adminMsg = "New $role registered: $name ($email)";
            createNotification($admin_id, $adminMsg, 'user_registered', $auth_id, 'admin', $role);
        }

        createNotification($auth_id, "Welcome to Eventra, $name! Your account has been created successfully.", 'welcome', $auth_id, $role, 'admin');
    }

    $redirect = '/client/pages/clientLogin.html';
    if ($role === 'admin') {
        $redirect = '/client/pages/clientLogin.html?role=admin';
    } elseif ($role === 'user') {
        $redirect = '/public/pages/index.html';
    }

    echo json_encode([
        'success' => true,
        'message' => 'Registration successful! Please log in to continue.',
        'redirect' => $redirect
    ]);

} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    $message = 'Registration failed. Please try again.';
    $errorCode = $e->getCode();
    
    if ($errorCode == 23000) { // Integrity constraint violation
        if (strpos($e->getMessage(), 'uq_auth_email') !== false || strpos($e->getMessage(), 'Duplicate entry') !== false) {
            $message = 'This email address is already registered. Please use a different email or log in.';
        } else {
            $message = 'Registration failed: Duplicate entry or constraint violation.';
        }
    } else {
        error_log("Registration PDO Error ($errorCode): " . $e->getMessage());
    }
    
    echo json_encode(['success' => false, 'message' => $message]);

} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    error_log("Registration Critical Error: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
    
    echo json_encode([
        'success' => false, 
        'message' => 'An unexpected error occurred during registration. Details: ' . $e->getMessage(),
        'error_type' => get_class($e)
    ]);
}

