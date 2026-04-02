<?php
/**
 * Environment Variable Loader
 * Loads .env file and populates $_ENV superglobal
 */

function loadEnv($path = __DIR__ . '/../.env')
{
    if (!file_exists($path)) {
        return; // Skip if .env doesn't exist (e.g. in production)
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    foreach ($lines as $line) {
        // Skip comments
        if (strpos(trim($line), '#') === 0) {
            continue;
        }

        // Parse key=value pairs
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);

            // Remove quotes if present
            $value = trim($value, '"\'');

            // Set in $_ENV and putenv
            $_ENV[$key] = $value;
            putenv("$key=$value");
        }
    }
}

// Handle system environment variables (for production like Render)
$envKeys = [
    'DB_HOST', 'DB_DATABASE', 'DB_USERNAME', 'DB_PASSWORD', 'DB_PORT',
    'APP_URL', 'APP_ENV', 'APP_DEBUG',
    'PAYSTACK_PUBLIC_KEY', 'PAYSTACK_SECRET_KEY',
    'MAIL_HOST', 'MAIL_PORT', 'MAIL_USERNAME', 'MAIL_PASSWORD', 'MAIL_ENCRYPTION', 'MAIL_FROM_ADDRESS', 'MAIL_FROM_NAME',
    'GOOGLE_CLIENT_ID', 'GOOGLE_CLIENT_SECRET', 'GOOGLE_REDIRECT_URI', 'GOOGLE_MAPS_API_KEY',
    'TERMII_API_KEY', 'TERMII_SECRET_KEY', 'TERMII_SENDER_ID',
    'CRON_SECRET', 'UPLOAD_MAX_SIZE'
];

foreach ($envKeys as $key) {
    if (!isset($_ENV[$key]) || $_ENV[$key] === '') {
        $val = getenv($key);
        if ($val !== false) {
            $_ENV[$key] = $val;
        }
    }
}

// Auto-load when this file is included
loadEnv();
