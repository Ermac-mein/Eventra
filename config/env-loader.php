<?php
/**
 * Environment Variable Loader
 * Loads .env file and populates $_ENV superglobal
 */

function loadEnv($path = __DIR__ . '/../.env')
{
    if (!file_exists($path)) {
        throw new Exception(".env file not found at: $path");
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

// Auto-load when this file is included
loadEnv();
?>