<?php
// Google API configuration (Sign-in, Maps)
require_once __DIR__ . '/env-loader.php';

return [
    'client_id' => $_ENV['GOOGLE_CLIENT_ID'] ?? '',
    'client_secret' => $_ENV['GOOGLE_CLIENT_SECRET'] ?? '',
    'redirect_uri' => $_ENV['GOOGLE_REDIRECT_URI'] ?? '',
    'maps_api_key' => $_ENV['GOOGLE_MAPS_API_KEY'] ?? ''
];