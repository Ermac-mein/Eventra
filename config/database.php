<?php
// Database configuration
require_once __DIR__ . '/env-loader.php';
// Session configuration is now deferred - endpoints should handle session initialization explicitly
// require_once __DIR__ . '/session-config.php'; // DEFERRED: Moved to individual endpoints
require_once __DIR__ . '/cors-config.php'; // CORS handling for API requests



/**
 * Helper to get environment variable from multiple sources
 */
function get_env_var($key, $default = null) {
    if (isset($_ENV[$key])) return $_ENV[$key];
    if (isset($_SERVER[$key])) return $_SERVER[$key];
    $val = getenv($key);
    return ($val !== false) ? $val : $default;
}

// Database configuration
define('DB_HOST', get_env_var('DB_HOST', '127.0.0.1'));
define('DB_PORT', get_env_var('DB_PORT', '3306'));
define('DB_NAME', get_env_var('DB_DATABASE', 'eventra_db'));
define('DB_USER', get_env_var('DB_USERNAME', 'eventra'));
define('DB_PASS', get_env_var('DB_PASSWORD', ''));

try {
    $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    $pdo = new PDO($dsn, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Log the error to error/errors.log
    $log_dir = dirname(__DIR__) . '/error';
    if (!is_dir($log_dir)) {
        mkdir($log_dir, 0777, true);
    }
    $log_file = $log_dir . '/errors.log';
    $error_msg = "[" . date('Y-m-d H:i:s') . "] Database connection failed: " . $e->getMessage() . PHP_EOL;
    file_put_contents($log_file, $error_msg, FILE_APPEND);

    // If it's an API request, return JSON
    if (isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], '/api/') !== false) {
        header('Content-Type: application/json');
        http_response_code(500);

        // Provide more specific error message based on error type
        $error_code = $e->getCode();
        $error_message = 'Database connection failed';

        if ($error_code == 1045) {
            $error_message = 'Database authentication failed. Please verify database credentials.';
        } elseif ($error_code == 2002) {
            $error_message = 'Cannot connect to database server at ' . DB_HOST . ':' . DB_PORT . '. Please ensure MySQL is running and the host is correct.';
        } elseif ($error_code == 1049) {
            $error_message = 'Database "' . DB_NAME . '" does not exist. Please create the database first.';
        } else {
            $error_message = 'Database connection error (' . $error_code . '): ' . $e->getMessage();
        }

        echo json_encode(['success' => false, 'message' => $error_message]);
        exit;
    }

    $show_debug = (get_env_var('APP_DEBUG') === 'true');
    if ($show_debug) {
        $masked_pass = strlen(DB_PASS) > 2 ? substr(DB_PASS, 0, 2) . '****' : '****';
        die("Database connection failed for " . DB_USER . "@" . DB_HOST . " (Port: " . DB_PORT . ", DB: " . DB_NAME . "): " . $e->getMessage());
    }
    die("Database connection failed. Please check error/errors.log for details.");
}
