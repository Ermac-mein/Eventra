<?php
/**
 * CORS Configuration for API Endpoints
 * Handles Cross-Origin Resource Sharing for localhost development
 */

// Allow requests from localhost (for development) and production domains
$allowed_origins = [
    'http://localhost:8000',
    'http://127.0.0.1:8000',
    'http://localhost:8001',
    'http://127.0.0.1:8001',
];

// Add origins from environment variables if present (comma-separated list)
$env_origins = getenv('ALLOWED_ORIGINS') ?: ($_ENV['ALLOWED_ORIGINS'] ?? '');
if (!empty($env_origins)) {
    $additional_origins = array_map('trim', explode(',', $env_origins));
    $allowed_origins = array_merge($allowed_origins, $additional_origins);
}

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';

if (in_array($origin, $allowed_origins)) {
    header("Access-Control-Allow-Origin: $origin");
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-Eventra-Portal');
    header('Access-Control-Max-Age: 86400'); // Cache preflight for 24 hours
}

// Handle preflight OPTIONS requests
if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}
