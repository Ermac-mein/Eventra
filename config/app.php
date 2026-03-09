<?php
// Application configuration
require_once __DIR__ . '/env-loader.php';

// Secret key used to sign QR code tokens (prevents forgery)
// Falls back to CRON_SECRET if QR_SECRET is not separately set
define('QR_SECRET', $_ENV['QR_SECRET'] ?? $_ENV['CRON_SECRET'] ?? 'eventra_qr_default_secret_change_in_production');

// Application base URL
define('APP_URL', $_ENV['APP_URL'] ?? 'http://localhost:8000');
