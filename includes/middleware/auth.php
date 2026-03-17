<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../helpers/entity-resolver.php';

function checkAuth($requiredRole = null)
{
    global $pdo;

    // 1. Ensure a session is started using centralized configuration
    if (session_status() === PHP_SESSION_NONE) {
        require_once __DIR__ . '/../../config/session-config.php';
    }

    // 2. Strict Session Validation: No session recovery across roles allowed.
    // Also enforcing the 12-hour inactivity rule for admin stability.
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > 43200) {
        logSecurityEvent($_SESSION['user_id'] ?? null, null, 'session_expired', 'session', 'Inactivity timeout exceeded (12 hours).');
        invalidateSession($_SESSION['user_id'] ?? null, $_SESSION['auth_token'] ?? null);
        exit;
    }
    $_SESSION['last_activity'] = time();

    // 2.5 Security: Regenerate session ID periodically (every 15 minutes)
    if (!isset($_SESSION['created'])) {
        $_SESSION['created'] = time();
    } else if (time() - $_SESSION['created'] > 900) {
        // Update session ID but keep the data
        session_regenerate_id(true);
        $_SESSION['created'] = time();
    }

    $token = $_SESSION['auth_token'] ?? null;
    $sessionRole = $_SESSION['user_role'] ?? null;

    // 2. Token Recovery: If session is missing, attempt to recover from Authorization header (Structural Fix)
    if (!$token) {
        $headers = array_change_key_case(getallheaders(), CASE_LOWER);
        $authHeader = $headers['authorization'] ?? '';
        if (!$authHeader && isset($_SERVER['HTTP_AUTHORIZATION'])) {
            $authHeader = $_SERVER['HTTP_AUTHORIZATION'];
        }

        if (preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
            $token = $matches[1];

            // Verify token and reconstruct session role/id if possible
            $stmt = $pdo->prepare("SELECT a.id, a.role FROM auth_accounts a JOIN auth_tokens t ON a.id = t.auth_id WHERE t.token = ? AND t.expires_at > NOW()");
            $stmt->execute([$token]);
            $recovery = $stmt->fetch();

            if ($recovery) {
                $_SESSION['auth_token'] = $token;
                $_SESSION['user_role'] = $recovery['role'];
                $_SESSION['role'] = $recovery['role']; // Normalize
                $sessionRole = $recovery['role'];

                // Set role-specific IDs
                if ($sessionRole === 'admin')
                    $_SESSION['admin_id'] = $recovery['id'];
                elseif ($sessionRole === 'client')
                    $_SESSION['client_id'] = $recovery['id'];
                else
                    $_SESSION['user_id'] = $recovery['id'];

                $_SESSION['last_activity'] = time();
            }
        }
    }

    // Resolve user_id based on role
    $user_id = null;
    if ($sessionRole === 'admin') {
        $user_id = $_SESSION['admin_id'] ?? null;
    } elseif ($sessionRole === 'client') {
        $user_id = $_SESSION['client_id'] ?? null;
    } else {
        $user_id = $_SESSION['user_id'] ?? null;
    }

    if (!$token || !$user_id || !$sessionRole) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Unauthorized. Please login.']);
        exit;
    }

    // 3. Role-specific Namespace Validation
    $expectedSessionName = getEventraSessionName();
    if (session_name() !== $expectedSessionName) {
        error_log("[Auth Security] Session name mismatch. Expected: $expectedSessionName, Actual: " . session_name());
        invalidateSession($user_id, $token);
        exit;
    }

    try {
        // 4. Token & Identity Validation
        $stmt = $pdo->prepare("SELECT a.*, t.expires_at as token_expires_at 
                               FROM auth_accounts a 
                               JOIN auth_tokens t ON a.id = t.auth_id 
                               WHERE t.token = ? AND a.id = ? AND a.deleted_at IS NULL");
        $stmt->execute([$token, $user_id]);
        $identity = $stmt->fetch();

        if (!$identity) {
            error_log("[Auth Security] Identity or token invalid. User ID: $user_id");
            invalidateSession($user_id, $token);
            exit;
        }

        // 5. Account Status & Lock Checks
        if ($identity['is_active'] != 1) {
            logSecurityEvent($user_id, null, 'unauthorized_access', 'session', "Account inactive.");
            invalidateSession($user_id, $token);
            exit;
        }

        if ($identity['locked_until'] && strtotime($identity['locked_until']) > time()) {
            logSecurityEvent($user_id, null, 'unauthorized_access', 'session', "Account locked until " . $identity['locked_until']);
            invalidateSession($user_id, $token);
            exit;
        }

        if (strtotime($identity['token_expires_at']) < time()) {
            invalidateSession($user_id, $token);
            exit;
        }

        // 6. Strict Role Match (Database vs Session)
        if (strtolower($sessionRole) !== strtolower($identity['role'])) {
            logSecurityEvent($user_id, null, 'role_mismatch', 'session', "Session Role($sessionRole) != DB Role({$identity['role']})");
            invalidateSession($user_id, $token);
            exit;
        }

        // 7. Authorization: Required Role Check
        if ($requiredRole && strtolower($identity['role']) !== strtolower($requiredRole)) {
            logSecurityEvent($user_id, null, 'unauthorized_access', 'session', "Insufficient permissions. Required: $requiredRole");
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Forbidden. Insufficient permissions.']);
            exit;
        }

        // 8. Admin Specific Provider Rule
        if (strtolower($identity['role']) === 'admin' && $identity['auth_provider'] !== 'local') {
            logSecurityEvent($user_id, null, 'policy_violation', 'session', "Admin accessed with non-local provider.");
            invalidateSession($user_id, $token);
            exit;
        }

        // Update last seen
        $stmt = $pdo->prepare("UPDATE auth_accounts SET last_seen = NOW(), is_online = 1 WHERE id = ?");
        $stmt->execute([$user_id]);

        return $user_id;
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Internal server error during auth check.']);
        exit;
    }
}

/**
 * Optional Authentication check.
 * Returns user_id if authenticated, null otherwise.
 * Does not exit or set HTTP 401.
 */
function checkAuthOptional()
{
    global $pdo;

    if (session_status() === PHP_SESSION_NONE) {
        require_once __DIR__ . '/../../config/session-config.php';
    }

    $token = $_SESSION['auth_token'] ?? null;
    $sessionRole = $_SESSION['user_role'] ?? null;

    // Token Recovery from Header
    if (!$token) {
        $headers = array_change_key_case(getallheaders(), CASE_LOWER);
        $authHeader = $headers['authorization'] ?? '';
        if (!$authHeader && isset($_SERVER['HTTP_AUTHORIZATION'])) {
            $authHeader = $_SERVER['HTTP_AUTHORIZATION'];
        }

        if (preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
            $token = $matches[1];
        }
    }

    if (!$token)
        return null;

    // Resolve user_id based on role if exists in session
    $user_id = null;
    if ($sessionRole === 'admin')
        $user_id = $_SESSION['admin_id'] ?? null;
    elseif ($sessionRole === 'client')
        $user_id = $_SESSION['client_id'] ?? null;
    else
        $user_id = $_SESSION['user_id'] ?? null;

    try {
        if ($user_id) {
            $stmt = $pdo->prepare("SELECT a.id, a.is_active FROM auth_accounts a JOIN auth_tokens t ON a.id = t.auth_id WHERE t.token = ? AND a.id = ? AND t.expires_at > NOW() AND a.deleted_at IS NULL");
            $stmt->execute([$token, $user_id]);
        } else {
            // Recover from token alone if session ID is missing
            $stmt = $pdo->prepare("SELECT a.id, a.is_active FROM auth_accounts a JOIN auth_tokens t ON a.id = t.auth_id WHERE t.token = ? AND t.expires_at > NOW() AND a.deleted_at IS NULL");
            $stmt->execute([$token]);
        }

        $identity = $stmt->fetch();
        if ($identity && $identity['is_active'] == 1) {
            return $identity['id'];
        }
    } catch (PDOException $e) {
    }

    return null;
}

/**
 * Role-Specific Middleware Wrappers
 */
function adminMiddleware()
{
    return checkAuth('admin');
}
function clientMiddleware()
{
    return checkAuth('client');
}
function userMiddleware()
{
    return checkAuth('user');
}

function invalidateSession($user_id, $token)
{
    global $pdo;

    if ($user_id && $token) {
        $pdo->prepare("DELETE FROM auth_tokens WHERE token = ?")
            ->execute([$token]);

        // ── Reset online status so dashboard doesn't show stale "active" ──
        $pdo->prepare("UPDATE auth_accounts SET is_online = 0, last_seen = NOW() WHERE id = ?")
            ->execute([$user_id]);
    }

    if (session_status() === PHP_SESSION_ACTIVE) {
        $_SESSION = [];
        session_destroy();
    }

    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Session invalid or expired.']);
}
