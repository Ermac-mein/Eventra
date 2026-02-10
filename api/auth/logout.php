<?php
/**
 * Logout API
 * Handles user logout, clears session, and updates status
 */
header('Content-Type: application/json');
require_once '../../config/database.php';

try {
    $user_id = $_SESSION['user_id'] ?? null;
    $role = $_SESSION['role'] ?? 'user';
    $auth_token = $_SESSION['auth_token'] ?? null;

    $table = 'users';
    if ($role === 'client')
        $table = 'clients';
    if ($role === 'admin')
        $table = 'admins';

    if ($user_id) {
        // Get user info for notification
        $stmt = $pdo->prepare("SELECT name FROM $table WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();

        // Create logout notification using helper
        require_once '../utils/notification-helper.php';
        if ($user) {
            createLogoutNotification($user_id, $user['name']);
        }

        // Update status to offline
        $stmt = $pdo->prepare("UPDATE auth_accounts SET is_active = 0 WHERE id = ?");
        $stmt->execute([$user_id]);

        // Delete auth tokens
        $stmt = $pdo->prepare("DELETE FROM auth_tokens WHERE auth_id = ?");
        $stmt->execute([$user_id]);
    }

    // Clear session
    session_unset();
    session_destroy();

    // Clear cookies
    if (isset($_COOKIE['remember_token'])) {
        setcookie('remember_token', '', time() - 3600, '/');
    }
    if (isset($_COOKIE['pending_role'])) {
        setcookie('pending_role', '', time() - 3600, '/');
    }

    echo json_encode([
        'success' => true,
        'message' => 'Logged out successfully'
    ]);
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Logout failed: ' . $e->getMessage()
    ]);
}
?>