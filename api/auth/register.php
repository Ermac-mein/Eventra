<?php
header('Content-Type: application/json');
require_once '../../config/database.php';
require_once '../../includes/helpers/entity-resolver.php';

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

    // 2. Hash Password
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    $pdo->beginTransaction();

    // 3. Insert into auth_accounts
    // Using email prefix + random hex as username
    $username = explode('@', $email)[0] . '_' . substr(bin2hex(random_bytes(2)), 0, 4);
    $stmt = $pdo->prepare("INSERT INTO auth_accounts (email, password_hash, role, auth_provider, is_active, username) VALUES (?, ?, ?, 'local', 1, ?)");
    $stmt->execute([$email, $hashedPassword, $role, $username]);
    $auth_id = $pdo->lastInsertId();

    // 4. Insert into role-specific table (e.g., clients or admins)
    if ($role === 'client') {
        $stmt = $pdo->prepare("INSERT INTO clients (client_auth_id, business_name, name, email, password) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$auth_id, $name, $name, $email, $hashedPassword]);
    } elseif ($role === 'admin') {
        $stmt = $pdo->prepare("INSERT INTO admins (admin_auth_id, name, password) VALUES (?, ?, ?)");
        $stmt->execute([$auth_id, $name, $hashedPassword]);
    }

    $pdo->commit();

    logSecurityEvent($auth_id, $email, 'registration_success', 'password', "New client registered: $name");

    // 5. Notify Admin and User using helper
    require_once '../../includes/helpers/notification-helper.php'; // Adjusted path

    $admin_id = getAdminUserId();
    if ($admin_id) {
        $adminMsg = "New client registered: $name ($email)";
        createNotification($admin_id, $adminMsg, 'user_registered', $auth_id);
    }

    // Welcome notification for the user
    createNotification($auth_id, "Welcome to Eventra, $name! Your account has been created successfully.", 'welcome', $auth_id);

    echo json_encode([
        'success' => true,
        'message' => 'Registration successful! You can now log in.'
    ]);

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Registration error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error during registration: ' . $e->getMessage()]);
}
