<?php
require_once __DIR__ . '/../../config/database.php';

function checkAuth($requiredRole = null)
{
    global $pdo;
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $token = $_SESSION['auth_token'] ?? null;
    $user_id = $_SESSION['user_id'] ?? null;

    if (!$token || !$user_id) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Unauthorized. Please login.']);
        exit;
    }

    try {
        // Check token validity and inactivity (10 minutes)
        $stmt = $pdo->prepare("SELECT * FROM auth_tokens WHERE token = ? AND user_id = ?");
        $stmt->execute([$token, $user_id]);
        $authToken = $stmt->fetch();

        if (!$authToken || strtotime($authToken['expires_at']) < time()) {
            // Token expired or invalid
            $stmt = $pdo->prepare("UPDATE users SET status = 'offline' WHERE id = ?");
            $stmt->execute([$user_id]);

            $stmt = $pdo->prepare("DELETE FROM auth_tokens WHERE token = ?");
            $stmt->execute([$token]);

            session_destroy();
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Session expired. Please login again.']);
            exit;
        }

        // Check if role matches if required
        if ($requiredRole && $_SESSION['role'] !== $requiredRole) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Forbidden. Insufficient permissions.']);
            exit;
        }

        // Update last activity and extend expiration by 10 minutes ONLY IF it's a short session
        // If it was "Remember Me", it will have a much longer expires_at which we should respect.
        $stmt = $pdo->prepare("UPDATE auth_tokens SET last_activity = CURRENT_TIMESTAMP WHERE token = ?");
        $stmt->execute([$token]);

        // If the remaining time is less than 10 minutes, extend it (for session sliding)
        if (strtotime($authToken['expires_at']) - time() < 600) {
            $new_expiry = date('Y-m-d H:i:s', strtotime('+10 minutes'));
            $stmt = $pdo->prepare("UPDATE auth_tokens SET expires_at = ? WHERE token = ?");
            $stmt->execute([$new_expiry, $token]);
        }

        return $user_id;
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Auth check failed: ' . $e->getMessage()]);
        exit;
    }
}
?>