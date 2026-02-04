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
$role = 'client'; // Strictly client-only registration

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
    $stmt = $pdo->prepare("INSERT INTO auth_accounts (email, password_hash, role, auth_provider) VALUES (?, ?, ?, 'local')");
    $stmt->execute([$email, $hashedPassword, $role]);
    $auth_id = $pdo->lastInsertId();

    // 4. Insert into clients
    $stmt = $pdo->prepare("INSERT INTO clients (auth_id, business_name, name, email) VALUES (?, ?, ?, ?)");
    $stmt->execute([$auth_id, $name, $name, $email]);

    $pdo->commit();

    logSecurityEvent($auth_id, $email, 'registration_success', 'password', "New client registered: $name");

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
?>