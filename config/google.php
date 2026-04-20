<?php
// Google API configuration (Sign-in, Maps)
require_once __DIR__ . '/env-loader.php';

$redirect_uri = $_ENV['GOOGLE_REDIRECT_URI'] ?? '';

// Dynamically override localhost or empty redirect URI if we are on a live domain
if (empty($redirect_uri) || strpos($redirect_uri, 'localhost') !== false) {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || 
                 (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ||
                 (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)) ? "https://" : "http://";
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $redirect_uri = $protocol . $host . '/api/auth/google-signin.php';
}

return [
    'client_id' => $_ENV['GOOGLE_CLIENT_ID'] ?? '',
    'client_secret' => $_ENV['GOOGLE_CLIENT_SECRET'] ?? '',
    'redirect_uri' => $redirect_uri,
    'origin' => $protocol . $host,
    'maps_api_key' => $_ENV['GOOGLE_MAPS_API_KEY'] ?? ''
];