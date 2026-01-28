<?php
header('Content-Type: application/json');
require_once '../../config/database.php';
session_start();

$data = json_decode(file_get_contents("php://input"), true);

if (!isset($data['email']) || !isset($data['password'])) {
    echo json_encode(['success' => false, 'message' => 'Email and password are required.']);
    exit;
}

$email = $data['email'];
$password = $data['password'];
$remember_me = isset($data['remember_me']) && $data['remember_me'] === true;

try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        // Generate alphanumeric access token (Security Feature for session tracking)
        $token = bin2hex(random_bytes(32));

        // Expiration logic: 10 mins vs Remember Me (e.g., 30 days)
        $expires_in = $remember_me ? '+30 days' : '+10 minutes';
        $expires_at = date('Y-m-d H:i:s', strtotime($expires_in));

        // Delete old tokens for this user (Ensure only one active session/token)
        $stmt = $pdo->prepare("DELETE FROM auth_tokens WHERE user_id = ?");
        $stmt->execute([$user['id']]);

        // Store new token in database
        $stmt = $pdo->prepare("INSERT INTO auth_tokens (user_id, token, expires_at) VALUES (?, ?, ?)");
        $stmt->execute([$user['id'], $token, $expires_at]);

        // Update user status
        $stmt = $pdo->prepare("UPDATE users SET status = 'active' WHERE id = ?");
        $stmt->execute([$user['id']]);

        // Create login notification for admin
        // Get admin user ID
        $stmt = $pdo->prepare("SELECT id FROM users WHERE role = 'admin' LIMIT 1");
        $stmt->execute();
        $admin = $stmt->fetch();

        if ($admin) {
            $admin_id = $admin['id'];

            // Create notification message based on role
            if ($user['role'] === 'admin') {
                $message = "Admin logged in";
            } elseif ($user['role'] === 'client') {
                $message = "Client '{$user['name']}' logged in";
            } else {
                $message = "User '{$user['name']}' logged in";
            }

            // Insert notification
            $stmt = $pdo->prepare("INSERT INTO notifications (recipient_id, sender_id, message, type) VALUES (?, ?, ?, ?)");
            $stmt->execute([$admin_id, $user['id'], $message, 'info']);
        }

        // Set Session
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['auth_token'] = $token;

        if ($remember_me) {
            setcookie('remember_token', $token, time() + (86400 * 30), "/");
        }

        echo json_encode([
            'success' => true,
            'message' => 'Login successful',
            'user' => [
                'id' => $user['id'],
                'name' => $user['name'],
                'email' => $user['email'],
                'role' => $user['role'],
                'token' => $token
            ]
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid email or password.']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>