<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../helpers/entity-resolver.php';

function checkAuth($requiredRole = null)
{
    global $pdo;
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $token = $_SESSION['auth_token'] ?? null;
    $user_id = $_SESSION['user_id'] ?? null;

    if (!$token || !$user_id) {
        error_log("[Auth Debug] Missing session data. Redirecting to login. User ID: " . ($user_id ?? 'None'));
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Unauthorized. Please login.']);
        exit;
    }

    try {
        // Check token validity
        $stmt = $pdo->prepare("SELECT * FROM auth_tokens WHERE token = ? AND auth_id = ?");
        $stmt->execute([$token, $user_id]);
        $authToken = $stmt->fetch();

        if (!$authToken || strtotime($authToken['expires_at']) < time()) {
            // Token expired or invalid
            error_log("[Auth Debug] Token expired or invalid. User ID: $user_id");
            invalidateSession($user_id, $token);
            exit;
        }

        // 1. Real-time Role Validation - Query auth_accounts instead of users
        $stmt = $pdo->prepare("SELECT role, is_active FROM auth_accounts WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();

        if (!$user || $user['is_active'] != 1) {
            logSecurityEvent($user_id, null, 'unauthorized_access', 'session', "User inactive or not found during auth check.");
            error_log("[Auth Debug] User inactive or not found. User ID: $user_id");
            invalidateSession($user_id, $token);
            exit;
        }

        // 2. Session Integrity: Ensure session role matches database role (Case-Insensitive)
        if (strtolower($_SESSION['role']) !== strtolower($user['role'])) {
            logSecurityEvent($user_id, null, 'role_mismatch', 'session', "Detected role mismatch: Session({$_SESSION['role']}) != DB({$user['role']})");
            error_log("[Auth Debug] Role mismatch detected! Session: {$_SESSION['role']}, DB: {$user['role']}. User ID: $user_id");
            invalidateSession($user_id, $token);
            exit;
        }

        // Check if role matches if required
        if ($requiredRole && $user['role'] !== $requiredRole) {
            logSecurityEvent($user_id, null, 'unauthorized_access', 'session', "Insufficient permissions for role: {$user['role']}. Required: $requiredRole");
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Forbidden. Insufficient permissions.']);
            exit;
        }

        // Update last activity
        $stmt = $pdo->prepare("UPDATE auth_tokens SET last_activity = CURRENT_TIMESTAMP WHERE token = ?");
        $stmt->execute([$token]);

        // Session sliding
        if (strtotime($authToken['expires_at']) - time() < 1800) {
            $new_expiry = date('Y-m-d H:i:s', strtotime('+2 hours'));
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

function invalidateSession($user_id, $token)
{
    global $pdo;

    // Log the invalidation event
    logSecurityEvent($user_id, null, 'logout', 'system', "Session invalidated due to security check or logout.");

    $stmt = $pdo->prepare("DELETE FROM auth_tokens WHERE token = ?");
    $stmt->execute([$token]);

    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }

    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Session invalid or role changed. Please login again.']);
}
?>