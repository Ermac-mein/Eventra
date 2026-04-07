<?php

/**
 * Legacy Auth Middleware Wrapper
 * Refactored to use the new MVC-based authentication system.
 * Use App\Core\Router and middleware instead for new code.
 */

require_once __DIR__ . '/../../config/database.php';

/**
 * Get Bearer Token from Authorization header
 */
function getBearerToken()
{
    $headers = getallheaders();
    if (isset($headers['Authorization'])) {
        if (preg_match('/Bearer\s+(.*)$/i', $headers['Authorization'], $matches)) {
            return $matches[1];
        }
    }
    return null;
}

/**
 * Validate Bearer token and return auth_id
 */
function validateBearerToken($requiredRole = null)
{
    global $pdo;
    
    $token = getBearerToken();
    if (!$token) {
        return null;
    }

    try {
        $stmt = $pdo->prepare("
            SELECT at.auth_id, aa.role, aa.is_active, aa.deleted_at
            FROM auth_tokens at
            JOIN auth_accounts aa ON at.auth_id = aa.id
            WHERE at.token = ? 
                AND at.type = 'access' 
                AND at.expires_at > NOW() 
                AND at.revoked = 0
            LIMIT 1
        ");
        $stmt->execute([$token]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$result) {
            return null;
        }

        if ($result['deleted_at'] || !$result['is_active']) {
            return null;
        }

        if ($requiredRole && $result['role'] !== $requiredRole) {
            return null;
        }

        // Update last_seen timestamp
        $pdo->prepare("UPDATE auth_accounts SET last_seen = NOW() WHERE id = ?")
            ->execute([$result['auth_id']]);

        return $result['auth_id'];
    } catch (Exception $e) {
        return null;
    }
}

function checkAuth($requiredRole = null)
{
    global $pdo;

    // First, try Bearer token authentication (for API requests)
    $auth_id = validateBearerToken($requiredRole);
    if ($auth_id) {
        // Set session variables for Bearer token auth
        // This ensures getAuthId() and other functions work properly
        if (session_status() === PHP_SESSION_NONE) {
            require_once __DIR__ . '/../../config/session-config.php';
        }
        
        // Get role from auth_accounts
        $stmt = $pdo->prepare("SELECT role FROM auth_accounts WHERE id = ?");
        $stmt->execute([$auth_id]);
        $roleResult = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($roleResult) {
            $_SESSION['auth_id'] = $auth_id;
            $_SESSION['user_role'] = $roleResult['role'];
            $_SESSION['role'] = $roleResult['role'];
        }
        
        return $auth_id;
    }

    // Fall back to session-based authentication
    if (session_status() === PHP_SESSION_NONE) {
        require_once __DIR__ . '/../../config/session-config.php';
    }

    $role = $_SESSION['role'] ?? null;
    $userId = $_SESSION[$role . '_id'] ?? null;

    if (!$userId || ($requiredRole && $role !== $requiredRole)) {
        // Check if it's an API request first to avoid redirect loops on AJAX calls
        if (strpos($_SERVER['REQUEST_URI'], '/api/') !== false || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false)) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Unauthorized. Please log in.']);
            exit;
        }

        $loginPath = ($requiredRole === 'client') ? '/client/login' : (($requiredRole === 'admin') ? '/admin/login' : '/user/login');
        header("Location: $loginPath");
        exit;
    }

    return $userId;
}

/**
 * Returns the global authentication account ID (auth_accounts.id)
 *
 * @return int|null
 */
function getAuthId()
{
    if (session_status() === PHP_SESSION_NONE) {
        require_once __DIR__ . '/../../config/session-config.php';
    }
    return $_SESSION['auth_id'] ?? null;
}

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

/**
 * Optional Auth Check
 * Returns the user ID if a session exists, or null otherwise.
 * Does not redirect or terminate the script.
 */
function checkAuthOptional()
{
    if (session_status() === PHP_SESSION_NONE) {
        require_once __DIR__ . '/../../config/session-config.php';
    }

    $role = $_SESSION['role'] ?? null;
    $userId = $_SESSION[$role . '_id'] ?? null;

    return $userId;
}
