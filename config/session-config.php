<?php
// Enable strict error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Prevent multiple session starts
if (session_status() === PHP_SESSION_ACTIVE) {
    return;
}

// Configure session settings BEFORE starting the session
ini_set('session.use_cookies', '1');
ini_set('session.use_only_cookies', '1');
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Lax'); // Important for Brave browser
ini_set('session.cookie_lifetime', '0'); // Session cookie (expires when browser closes)
ini_set('session.gc_maxlifetime', '86400'); // 24 hours server-side session lifetime

// For localhost development, ensure cookies work properly
if (isset($_SERVER['HTTP_HOST']) && strpos($_SERVER['HTTP_HOST'], 'localhost') !== false) {
    ini_set('session.cookie_domain', '');
    ini_set('session.cookie_path', '/');
    ini_set('session.cookie_secure', '0'); // Not using HTTPS on localhost
} else {
    // Production settings
    ini_set('session.cookie_secure', '1'); // Require HTTPS in production
}

// Set session save path to ensure it's writable
$session_path = sys_get_temp_dir() . '/eventra_sessions';
if (!is_dir($session_path)) {
    mkdir($session_path, 0777, true);
}
ini_set('session.save_path', $session_path);

// Function to determine session name based on URL path or Referer
function getEventraSessionName()
{
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    $referer = $_SERVER['HTTP_REFERER'] ?? '';

    // Priority 1: Check if the URI itself identifies the role
    if (strpos($uri, '/admin/') !== false || strpos($uri, '/api/admin/') !== false) {
        return 'EVENTRA_ADMIN_SESS';
    }

    if (strpos($uri, '/client/') !== false || strpos($uri, '/api/clients/') !== false) {
        return 'EVENTRA_CLIENT_SESS';
    }

    // Priority 2: For other API calls, check the Referer to use the caller's session
    if (strpos($uri, '/api/') !== false) {
        if (strpos($referer, '/admin/') !== false) {
            return 'EVENTRA_ADMIN_SESS';
        }
        if (strpos($referer, '/client/') !== false) {
            return 'EVENTRA_CLIENT_SESS';
        }
    }

    // Default for users/public
    return 'EVENTRA_USER_SESS';
}

// Start the session with a role-specific name
session_name(getEventraSessionName());
session_start();

// Regenerate session ID periodically to prevent session fixation
if (!isset($_SESSION['created'])) {
    $_SESSION['created'] = time();
} elseif (time() - $_SESSION['created'] > 1800) {
    // Regenerate session ID every 30 minutes
    session_regenerate_id(true);
    $_SESSION['created'] = time();
}

// Debug logging in development/local mode
if (isset($_ENV['APP_ENV']) && in_array($_ENV['APP_ENV'], ['development', 'local'])) {
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    $referer = $_SERVER['HTTP_REFERER'] ?? '';
    $debug_msg = "[Session Debug] URI: " . $uri . " | Referer: " . basename($referer) . " | Session Name: " . session_name() . " | Session ID: " . session_id();
    error_log($debug_msg);
}
?>