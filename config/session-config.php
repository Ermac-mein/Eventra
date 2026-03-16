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
ini_set('session.cookie_samesite', 'Lax'); // Changed from Strict to Lax for better CSRF protection and redirect compatibility

$sessionName = getEventraSessionName();
$is_admin_session = ($sessionName === 'EVENTRA_ADMIN_SESS');
$timeout_duration = $is_admin_session ? 43200 : 1800; // 12 hours for admin, 30 mins for others

ini_set('session.cookie_lifetime', $timeout_duration);
ini_set('session.gc_maxlifetime', $timeout_duration);

// For localhost development, ensure cookies work properly
$currentHost = $_SERVER['HTTP_HOST'] ?? '';
$isLocal = (strpos($currentHost, 'localhost') !== false || strpos($currentHost, '127.0.0.1') !== false);

if ($isLocal) {
    ini_set('session.cookie_domain', '');
    ini_set('session.cookie_path', '/');
    ini_set('session.cookie_secure', '0');
} else {
    // Production settings
    ini_set('session.cookie_secure', '1'); // Require HTTPS in production
}

// Set session save path to a project-local directory for reliability
$session_path = __DIR__ . '/../sessions';
if (!is_dir($session_path)) {
    mkdir($session_path, 0700, true); // More restrictive permissions
}
ini_set('session.save_path', $session_path);

/**
 * Robustly determine the correct session name based on the target portal.
 * This prevents cross-role session leakage.
 */
function getEventraSessionName()
{
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    $referer = $_SERVER['HTTP_REFERER'] ?? '';

    // Polyfill for getallheaders in non-Apache/CLI environments
    if (!function_exists('getallheaders')) {
        function getallheaders()
        {
            $headers = [];
            foreach ($_SERVER as $name => $value) {
                if (substr($name, 0, 5) == 'HTTP_') {
                    $headerName = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))));
                    $headers[$headerName] = $value;
                }
            }
            return $headers;
        }
    }

    // Normalize headers for case-insensitivity
    $headers = array_change_key_case(getallheaders(), CASE_LOWER);
    $portalHeader = $headers['x-eventra-portal'] ?? '';

    // Priority 1: Explicit Portal Header (Trusted source for internal requests)
    if ($portalHeader === 'admin')
        return 'EVENTRA_ADMIN_SESS';
    if ($portalHeader === 'client')
        return 'EVENTRA_CLIENT_SESS';
    if ($portalHeader === 'user')
        return 'EVENTRA_USER_SESS';

    // Priority 2: Direct Path Detection (Most common for direct browser access)
    if (strpos($uri, '/admin/') !== false || strpos($referer, '/admin/') !== false) {
        return 'EVENTRA_ADMIN_SESS';
    }
    if (strpos($uri, '/client/') !== false || strpos($referer, '/client/') !== false) {
        return 'EVENTRA_CLIENT_SESS';
    }

    // Priority 3: API Context Handling
    if (strpos($uri, '/api/') !== false) {
        // Stats and role-specific endpoints
        if (strpos($uri, '/api/admin/') !== false || strpos($uri, '/admin-') !== false)
            return 'EVENTRA_ADMIN_SESS';
        if (strpos($uri, '/api/clients/') !== false || strpos($uri, '/client-') !== false)
            return 'EVENTRA_CLIENT_SESS';
    }

    // Default for users/public
    return 'EVENTRA_USER_SESS';
}

// Start the session with a role-specific name
$sessionName = getEventraSessionName();
session_name($sessionName);

// Set cookie params to match the role-specific session and path if needed
$cookieParams = session_get_cookie_params();
session_set_cookie_params([
    'lifetime' => $timeout_duration,
    'path' => $cookieParams['path'],
    'domain' => $cookieParams['domain'],
    'secure' => $cookieParams['secure'],
    'httponly' => true,
    'samesite' => 'Lax'
]);

session_start();

// Rolling Session Logic: Refresh cookie lifetime on every activity
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), $_COOKIE[session_name()], time() + $timeout_duration, $cookieParams['path'], $cookieParams['domain'], $cookieParams['secure'], true);
}

// Enforce inactivity timeout at the core session level
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $timeout_duration) {
    // Session expired due to inactivity
    session_unset();
    session_destroy();
    session_start(); // Restart a fresh, empty session explicitly
}
$_SESSION['last_activity'] = time();
