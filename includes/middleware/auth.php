<?php
/**
 * Legacy Auth Middleware Wrapper
 * Refactored to use the new MVC-based authentication system.
 * Use App\Core\Router and middleware instead for new code.
 */

require_once __DIR__ . '/../../config/database.php';

function checkAuth($requiredRole = null) {
    // Delegate to the new Router's logic or a dedicated service if we had one.
    // For now, we manually check the role-specific session.
    
    if (session_status() === PHP_SESSION_NONE) {
        require_once __DIR__ . '/../../config/session-config.php';
    }

    $role = $_SESSION['role'] ?? null;
    $userId = $_SESSION[$role . '_id'] ?? null;

    if (!$userId || ($requiredRole && $role !== $requiredRole)) {
        // Check if it's an API request first to avoid redirect loops on AJAX calls
        if (strpos($_SERVER['REQUEST_URI'], '/api/') !== false || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false)) {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
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
function getAuthId() {
    if (session_status() === PHP_SESSION_NONE) {
        require_once __DIR__ . '/../../config/session-config.php';
    }
    return $_SESSION['auth_id'] ?? null;
}

function adminMiddleware() { return checkAuth('admin'); }
function clientMiddleware() { return checkAuth('client'); }
function userMiddleware() { return checkAuth('user'); }

/**
 * Optional Auth Check
 * Returns the user ID if a session exists, or null otherwise.
 * Does not redirect or terminate the script.
 */
function checkAuthOptional() {
    if (session_status() === PHP_SESSION_NONE) {
        require_once __DIR__ . '/../../config/session-config.php';
    }

    $role = $_SESSION['role'] ?? null;
    $userId = $_SESSION[$role . '_id'] ?? null;

    return $userId;
}
