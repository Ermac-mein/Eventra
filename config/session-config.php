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
ini_set('session.cookie_samesite', 'Lax');

// Set session save path to a project-local directory for reliability
$session_path = __DIR__ . '/../sessions';
if (!is_dir($session_path)) {
    mkdir($session_path, 0700, true);
}
ini_set('session.save_path', $session_path);

// Default timeout (can be overridden by roles)
$timeout_duration = 1800; // 30 mins default

ini_set('session.cookie_lifetime', 0); // Session cookie (expires on browser close)
ini_set('session.gc_maxlifetime', $timeout_duration);

// CSRF Protection Initialization
if (session_status() === PHP_SESSION_NONE) {
    // Session name should be set by the caller (Router or LoginController)
    // If not set, use a fallback that detects the portal context
    if (!session_name() || session_name() === 'PHPSESSID' || session_name() === 'EVENTRA_GUEST_SESS') {
        $headers = getallheaders();
        $portal = $_SERVER['HTTP_X_EVENTRA_PORTAL'] ?? $headers['X-Eventra-Portal'] ?? $headers['x-eventra-portal'] ?? null;
        
        // If no header, try to detect from URI
        if (!$portal) {
            $uri = $_SERVER['REQUEST_URI'] ?? '';
            if (strpos($uri, '/admin/') !== false) $portal = 'admin';
            elseif (strpos($uri, '/client/') !== false) $portal = 'client';
            elseif (strpos($uri, '/api/admin/') !== false) $portal = 'admin';
            elseif (strpos($uri, '/api/client/') !== false) $portal = 'client';
            elseif (strpos($uri, '/api/clients/') !== false) $portal = 'client';
        }

        if ($portal === 'admin') {
            session_name('EVENTRA_ADMIN_SESS');
        } elseif ($portal === 'client') {
            session_name('EVENTRA_CLIENT_SESS');
        } else {
            session_name('EVENTRA_USER_SESS');
        }
    }
    session_start();
}

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$_SESSION['last_activity'] = time();
