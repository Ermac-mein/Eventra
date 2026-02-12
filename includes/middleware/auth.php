<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../helpers/entity-resolver.php';

function checkAuth($requiredRole = null)
{
    global $pdo;

    // 1. Ensure a session is started
    if (session_status() === PHP_SESSION_NONE) {
        require_once __DIR__ . '/../../config/session-config.php';
        // session-config.php starts the session based on path/referer
    }

    // 2. Session Recovery: If current session is empty, try other known session names
    if (empty($_SESSION['user_id']) || empty($_SESSION['auth_token'])) {
        $currentName = session_name();
        $possibleNames = ['EVENTRA_CLIENT_SESS', 'EVENTRA_ADMIN_SESS', 'EVENTRA_USER_SESS'];
        $sessionMatched = false;

        foreach ($possibleNames as $name) {
            if ($name === $currentName)
                continue;
            if (isset($_COOKIE[$name])) {
                // Save and close current empty session
                session_write_close();

                // Try to open the other session
                session_name($name);
                session_start();

                if (!empty($_SESSION['user_id']) && !empty($_SESSION['auth_token'])) {
                    $sessionMatched = true;
                    break; // Successfully switched to a populated session
                }

                // Still empty, close and continue trying
                session_write_close();
            }
        }

        // If no alternative session found, restart the original (or default) session
        if (session_status() === PHP_SESSION_NONE) {
            session_name($currentName);
            session_start();
        }
    }

    $token = $_SESSION['auth_token'] ?? null;
    $user_id = $_SESSION['user_id'] ?? null;

    if (!$token || !$user_id) {
        $referer = $_SERVER['HTTP_REFERER'] ?? 'None';
        error_log("[Auth Debug] Missing session data. Redirecting to login. User ID: " . ($user_id ?? 'None') . " | Token: " . ($token ? 'Present' : 'None') . " | Session Name: " . session_name() . " | Session ID: " . session_id() . " | Referer: $referer");
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Unauthorized. Please login.']);
        exit;
    }

    try {
        // Check token validity
        $stmt = $pdo->prepare("SELECT * FROM auth_tokens WHERE token = ? AND auth_id = ?");
        $stmt->execute([$token, $user_id]);
        $authToken = $stmt->fetch();

        if (!$authToken) {
            error_log("[Auth Debug] Token not found in database. User ID: $user_id | Token: $token");
            invalidateSession($user_id, $token);
            exit;
        }

        if (strtotime($authToken['expires_at']) < time()) {
            error_log("[Auth Debug] Token expired. User ID: $user_id | Expires: " . $authToken['expires_at']);
            invalidateSession($user_id, $token);
            exit;
        }

        // 1. Real-time Role Validation - Query auth_accounts instead of users
        $stmt = $pdo->prepare("SELECT role, is_active FROM auth_accounts WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();

        if (!$user) {
            error_log("[Auth Debug] User account not found in database. User ID: $user_id");
            invalidateSession($user_id, $token);
            exit;
        }

        if ($user['is_active'] != 1) {
            error_log("[Auth Debug] User account is inactive. User ID: $user_id");
            logSecurityEvent($user_id, null, 'unauthorized_access', 'session', "User inactive during auth check.");
            invalidateSession($user_id, $token);
            exit;
        }

        // 2. Session Integrity: Ensure session role matches database role (Case-Insensitive)
        if (strtolower($_SESSION['role']) !== strtolower($user['role'])) {
            error_log("[Auth Debug] Role mismatch detected! Session: {$_SESSION['role']}, DB: {$user['role']}. User ID: $user_id");
            logSecurityEvent($user_id, null, 'role_mismatch', 'session', "Detected role mismatch: Session({$_SESSION['role']}) != DB({$user['role']})");
            invalidateSession($user_id, $token);
            exit;
        }

        // Check if role matches if required
        if ($requiredRole && $user['role'] !== $requiredRole) {
            error_log("[Auth Debug] Insufficient permissions. User Role: {$user['role']}, Required: $requiredRole. User ID: $user_id");
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