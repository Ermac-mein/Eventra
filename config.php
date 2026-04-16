<?php
ob_start();
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(0);

require_once __DIR__ . '/config/env-loader.php';

// 1. Centralized Error Reporting (logged only)
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/php-errors.log');

$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
$host     = $_SERVER['HTTP_HOST'] ?? 'localhost';
$base_url = $protocol . $host;

if ($host !== 'localhost' && $host !== '127.0.0.1') {
    ini_set('session.cookie_path',     '/');
    ini_set('session.cookie_domain',   $host);
    ini_set('session.cookie_secure',   ($protocol === "https://") ? 1 : 0);
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_samesite', 'Lax');
}

// Centralized session management
require_once __DIR__ . '/config/session-config.php';

if (!defined('SITE_URL')) define('SITE_URL',   $base_url);
if (!defined('MEDIA_PATH')) define('MEDIA_PATH', __DIR__ . '/media/');
if (!defined('UPLOAD_URL')) define('UPLOAD_URL', SITE_URL . '/media/');
