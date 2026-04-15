<?php

header('Content-Type: application/json');
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/helpers/entity-resolver.php';

$data = json_decode(file_get_contents("php://input"), true);

if (!isset($data['name']) || !isset($data['email']) || !isset($data['password'])) {
    echo json_encode(['success' => false, 'message' => 'All fields are required.']);
    exit;
}

$name = $data['name'];
$email = $data['email'];
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
    require_once __DIR__ . '/../utils/id-generator.php';

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

    // 5. Notify Admin and User using helper
    require_once __DIR__ . '/../utils/notification-helper.php';

    $admin_id = getAdminUserId();
    if ($admin_id) {
        $adminMsg = "New $role registered: $name ($email)";
        createNotification($admin_id, $adminMsg, 'user_registered', $auth_id, 'admin', $role);
    }

    createNotification($auth_id, "Welcome to Eventra, $name! Your account has been created successfully.", 'welcome', $auth_id, $role, 'admin');

    // AUTO-LOGIN DISABLED: Users must log in manually to trigger OTP verification if required.
    $redirect = '/client/pages/clientLogin.html';
    if ($role === 'admin') {
        $redirect = '/client/pages/clientLogin.html?role=admin';
    } elseif ($role === 'user') {
        $redirect = '/public/pages/index.html'; // Or user login page
    }

    // Re-resolve user for consistent return object
    $fullUser = resolveEntity($email, $role);

    echo json_encode([
        'success' => true,
        'message' => 'Registration successful! Please log in to continue.',
        'redirect' => $redirect
    ]);
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    $message = 'Registration failed. Please try again.';
    if ($e->getCode() == 23000) { // Integrity constraint violation
        if (strpos($e->getMessage(), 'uq_auth_email') !== false) {
            $message = 'This email address is already registered. Please use a different email or log in.';
        }
    }
    
    error_log("Registration error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $message]);
}
