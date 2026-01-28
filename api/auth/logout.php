<?php
header('Content-Type: application/json');
require_once '../../config/database.php';
session_start();

try {
    // Get user info before clearing session
    $user_id = $_SESSION['user_id'] ?? null;
    $role = $_SESSION['role'] ?? null;

    if (!$user_id) {
        echo json_encode(['success' => false, 'message' => 'No active session found.']);
        exit;
    }

    // Get user name for notification
    $stmt = $pdo->prepare("SELECT name FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    $user_name = $user['name'] ?? 'Unknown User';

    // Update user status to offline
    $stmt = $pdo->prepare("UPDATE users SET status = 'offline' WHERE id = ?");
    $stmt->execute([$user_id]);

    // Delete auth tokens
    $stmt = $pdo->prepare("DELETE FROM auth_tokens WHERE user_id = ?");
    $stmt->execute([$user_id]);

    // Create logout notification for admin
    // Get admin user ID
    $stmt = $pdo->prepare("SELECT id FROM users WHERE role = 'admin' LIMIT 1");
    $stmt->execute();
    $admin = $stmt->fetch();

    if ($admin) {
        $admin_id = $admin['id'];

        // Create notification message based on role
        if ($role === 'admin') {
            $message = "Admin logged out";
        } elseif ($role === 'client') {
            $message = "Client '$user_name' logged out";
        } else {
            $message = "User '$user_name' logged out";
        }

        // Insert notification
        $stmt = $pdo->prepare("INSERT INTO notifications (recipient_id, sender_id, message, type) VALUES (?, ?, ?, ?)");
        $stmt->execute([$admin_id, $user_id, $message, 'info']);
    }

    // Clear session
    session_unset();
    session_destroy();

    // Clear remember_token cookie if exists
    if (isset($_COOKIE['remember_token'])) {
        setcookie('remember_token', '', time() - 3600, '/');
    }

    echo json_encode([
        'success' => true,
        'message' => 'Logout successful'
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error during logout: ' . $e->getMessage()
    ]);
}
?>