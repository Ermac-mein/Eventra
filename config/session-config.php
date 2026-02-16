<?php
// Enable strict error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Prevent multiple session starts
if (session_status() === PHP_SESSION_ACTIVE) {
    return;
}

// Configure session settings BEFORE starting the session
ini_set('session.use_cookies', '1');
ini_set('session.use_only_cookies', '1');
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Lax'); // Important for Brave browser
ini_set('session.cookie_lifetime', '1800'); // 30 minutes
ini_set('session.gc_maxlifetime', '1800'); // 30 minutes

// For localhost development, ensure cookies work properly
$currentHost = $_SERVER['HTTP_HOST'] ?? '';
$isLocal = (strpos($currentHost, 'localhost') !== false || strpos($currentHost, '127.0.0.1') !== false);

if ($isLocal) {
    ini_set('session.cookie_domain', '');
    ini_set('session.cookie_path', '/');
    ini_set('session.cookie_secure', '0'); // Not using HTTPS on localhost
} else {
    // Production settings
    ini_set('session.cookie_secure', '1'); // Require HTTPS in production
}

// Set session save path to a project-local directory for reliability
$session_path = __DIR__ . '/../sessions';
if (!is_dir($session_path)) {
    mkdir($session_path, 0777, true);
}
ini_set('session.save_path', $session_path);

function getEventraSessionName()
{
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    $referer = $_SERVER['HTTP_REFERER'] ?? '';
    $portalHeader = $_SERVER['HTTP_X_EVENTRA_PORTAL'] ?? '';

    // Priority 1: Explicit Portal Header (Most Reliable for API calls)
    if ($portalHeader === 'admin')
        return 'EVENTRA_ADMIN_SESS';
    if ($portalHeader === 'client')
        return 'EVENTRA_CLIENT_SESS';

    // Priority 2: Direct Path Detection
    if (strpos($uri, '/admin/') !== false || strpos($uri, '/api/stats/get-admin') !== false) {
        return 'EVENTRA_ADMIN_SESS';
    }

    if (strpos($uri, '/client/') !== false || strpos($uri, '/api/stats/get-client') !== false) {
        return 'EVENTRA_CLIENT_SESS';
    }

    // Priority 2: API Context Handling
    if (strpos($uri, '/api/') !== false) {
        // Check Referer for portal context
        if (strpos($referer, '/admin/') !== false)
            return 'EVENTRA_ADMIN_SESS';
        if (strpos($referer, '/client/') !== false)
            return 'EVENTRA_CLIENT_SESS';

        // Check for specific API file patterns
        if (strpos($uri, 'get-admin') !== false)
            return 'EVENTRA_ADMIN_SESS';
        if (strpos($uri, 'get-client') !== false)
            return 'EVENTRA_CLIENT_SESS';

        // If it's a generic API (like /api/events/)
        if (isset($_COOKIE['EVENTRA_CLIENT_SESS']))
            return 'EVENTRA_CLIENT_SESS';
        if (isset($_COOKIE['EVENTRA_ADMIN_SESS']))
            return 'EVENTRA_ADMIN_SESS';
    }

    // Default for users/public
    return 'EVENTRA_USER_SESS';
}

// Start the session with a role-specific name
$sessionName = getEventraSessionName();
session_name($sessionName);
session_start();

// Debug logging
$debug_log = __DIR__ . '/../error/session_debug.log';
$log_dir = dirname($debug_log);
if (!is_dir($log_dir))
    mkdir($log_dir, 0777, true);

$uri = $_SERVER['REQUEST_URI'] ?? '';
$referer = $_SERVER['HTTP_REFERER'] ?? '';
$user_id = $_SESSION['user_id'] ?? 'None';
$role = $_SESSION['role'] ?? 'None';

$debug_msg = "[" . date('Y-m-d H:i:s') . "] URI: $uri | Referer: $referer | Session Name: $sessionName | ID: " . session_id() . " | UserID: $user_id | Role: $role" . PHP_EOL;
file_put_contents($debug_log, $debug_msg, FILE_APPEND);

// Regenerate session ID periodically to prevent session fixation
if (!isset($_SESSION['created'])) {
    $_SESSION['created'] = time();
} elseif (time() - $_SESSION['created'] > 900) {
    // Regenerate session ID every 15 minutes (half of session lifetime)
    session_regenerate_id(true);
    $_SESSION['created'] = time();
}
?>