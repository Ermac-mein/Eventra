<?php
// Application configuration
require_once __DIR__ . '/env-loader.php';

// Secret key used to sign QR code tokens (prevents forgery)
// Must be set in .env via QR_SECRET variable
$qr_secret = $_ENV['QR_SECRET'] ?? getenv('QR_SECRET');
if (empty($qr_secret)) {
    error_log('[CONFIG] WARNING: QR_SECRET not set in environment. QR codes may not verify correctly.');
    $qr_secret = 'CHANGE_ME_IN_PRODUCTION_' . random_bytes(16);
}
define('QR_SECRET', $qr_secret);

// Application base URL (Dynamic detection if not set)
if (!isset($_ENV['APP_URL']) || empty($_ENV['APP_URL']) || strpos($_ENV['APP_URL'], 'localhost') !== false) {
    if (defined('SITE_URL')) {
        define('APP_URL', SITE_URL);
    } else {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || 
                     (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ||
                     (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)) ? "https://" : "http://";
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        define('APP_URL', $protocol . $host);
    }
} else {
    define('APP_URL', $_ENV['APP_URL']);
}
