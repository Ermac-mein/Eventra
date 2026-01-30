<?php
header('Content-Type: application/json');
require_once '../../config/database.php';

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

        // Expiration logic: 2 hours vs Remember Me (e.g., 30 days)
        $expires_in = $remember_me ? '+30 days' : '+2 hours';
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

        // Create login notification using helper
        require_once '../utils/notification-helper.php';
        createLoginNotification($user['id'], $user['name'], $user['email']);

        // Notify admin about user/client login
        $admin_id = getAdminUserId();
        if ($admin_id && $admin_id != $user['id']) {
            if ($user['role'] === 'client') {
                createClientLoginNotification($admin_id, $user['id'], $user['name'], $user['email']);
            } elseif ($user['role'] === 'user') {
                createUserLoginNotification($admin_id, $user['id'], $user['name'], $user['email'], 'user');
            }
        }

        // Set Session
        $expectedSessionName = 'EVENTRA_USER_SESS';
        if ($user['role'] === 'admin') {
            $expectedSessionName = 'EVENTRA_ADMIN_SESS';
        } elseif ($user['role'] === 'client') {
            $expectedSessionName = 'EVENTRA_CLIENT_SESS';
        }

        // If the current session name is not what we expect for this role,
        // (which happens when logging in from the shared login page),
        // we need to transition to the correct session name.
        if (session_name() !== $expectedSessionName) {
            $current_data = $_SESSION;
            session_destroy();
            session_name($expectedSessionName);
            session_start();
            $_SESSION = $current_data;
        }

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
                'profile_pic' => $user['profile_pic'],
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