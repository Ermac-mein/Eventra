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

// Application base URL
define('APP_URL', $_ENV['APP_URL'] ?? 'http://localhost:8000');

