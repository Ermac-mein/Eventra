<?php
header('Content-Type: application/json');
require_once '../../config/database.php';
session_start();

$data = json_decode(file_get_contents("php://input"), true);

// In a real scenario, you'd verify the Google ID token here using a library like google-api-php-client
if (!isset($data['google_id']) || !isset($data['email'])) {
    echo json_encode(['success' => false, 'message' => 'Google information is missing.']);
    exit;
}

$google_id = $data['google_id'];
$email = $data['email'];
$name = $data['name'] ?? 'Google User';
$profile_pic = $data['profile_pic'] ?? null;

// Read selected role from cookie (My Idea implementation)
$selected_role = $_COOKIE['pending_role'] ?? 'user';
if (!in_array($selected_role, ['client', 'user'])) {
    $selected_role = 'user';
}

try {
    // Check if user exists
    $stmt = $pdo->prepare("SELECT * FROM users WHERE google_id = ? OR email = ?");
    $stmt->execute([$google_id, $email]);
    $user = $stmt->fetch();

    if ($user) {
        // Update user if needed
        if (!$user['google_id']) {
            $stmt = $pdo->prepare("UPDATE users SET google_id = ?, profile_pic = ? WHERE id = ?");
            $stmt->execute([$google_id, $profile_pic, $user['id']]);
        }
    } else {
        // Create new user with selected role
        $stmt = $pdo->prepare("INSERT INTO users (name, email, google_id, profile_pic, role) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$name, $email, $google_id, $profile_pic, $selected_role]);

        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$pdo->lastInsertId()]);
        $user = $stmt->fetch();
    }

    // Set Token
    $token = bin2hex(random_bytes(32));
    $expires_at = date('Y-m-d H:i:s', strtotime('+10 minutes'));

    // Delete old tokens for this user (Ensure only one active session/token)
    $stmt = $pdo->prepare("DELETE FROM auth_tokens WHERE user_id = ?");
    $stmt->execute([$user['id']]);

    $stmt = $pdo->prepare("INSERT INTO auth_tokens (user_id, token, expires_at) VALUES (?, ?, ?)");
    $stmt->execute([$user['id'], $token, $expires_at]);

    $stmt = $pdo->prepare("UPDATE users SET status = 'active' WHERE id = ?");
    $stmt->execute([$user['id']]);

    $_SESSION['user_id'] = $user['id'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['auth_token'] = $token;

    // Clear the pending role cookie
    setcookie('pending_role', '', time() - 3600, "/");

    // Higher level Redirection logic (dynamic relative paths)
    $redirect = ($user['role'] === 'client') ? '../../client/pages/dashboard.html' : '../../public/pages/index.html';

    // Check if specifically going to admin
    if ($user['role'] === 'admin') {
        $redirect = '../../admin/pages/dashboard.html';
    }

    echo json_encode([
        'success' => true,
        'message' => 'Signed in with Google',
        'redirect' => $redirect,
        'user' => [
            'id' => $user['id'],
            'name' => $user['name'],
            'email' => $user['email'],
            'role' => $user['role'],
            'token' => $token
        ]
    ]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>