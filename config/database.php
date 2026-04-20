<?php
// Centralized Error Reporting
if (!headers_sent()) {
    ob_start();
}
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/php-errors.log');
error_reporting(E_ALL);

// Database configuration
require_once __DIR__ . '/env-loader.php';
// Session configuration is deferred - endpoints should handle session initialization explicitly
// require_once __DIR__ . '/session-config.php'; // DEFERRED: Moved to individual endpoints
require_once __DIR__ . '/cors-config.php'; // CORS handling for API requests



/**
 * Helper to get environment variable from multiple sources
 */
if (!function_exists('get_env_var')) {
    function get_env_var($key, $default = null) {
        if (isset($_ENV[$key])) return $_ENV[$key];
        if (isset($_SERVER[$key])) return $_SERVER[$key];
        $val = getenv($key);
        return ($val !== false) ? $val : $default;
    }
}

// Database configuration constants
if (!defined('DB_HOST')) define('DB_HOST', get_env_var('DB_HOST', '127.0.0.1'));
if (!defined('DB_PORT')) define('DB_PORT', get_env_var('DB_PORT', '3306'));
if (!defined('DB_NAME')) define('DB_NAME', get_env_var('DB_DATABASE', 'eventra_db'));
if (!defined('DB_USER')) define('DB_USER', get_env_var('DB_USERNAME', 'eventra'));
if (!defined('DB_PASS')) define('DB_PASS', get_env_var('DB_PASSWORD', ''));

/**
 * Singleton Database Connection Provider
 */
function getPDO() {
    static $instance = null;
    
    if ($instance !== null) {
        return $instance;
    }

    try {
        $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4";
        $instance = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_PERSISTENT => false,
            PDO::ATTR_TIMEOUT => 3 // Fail fast to avoid hanging connections
        ]);

        return $instance;

    } catch (PDOException $e) {
        error_log("[" . date('Y-m-d H:i:s') . "] Database connection failed: " . $e->getMessage());

        $sqlstate = null;
        if (is_array($e->errorInfo) && isset($e->errorInfo[0])) {
            $sqlstate = $e->errorInfo[0];
        }

        $isOverload = (
            $e->getCode() == 1040 ||
            $sqlstate === '08004' ||
            strpos($e->getMessage(), '1040') !== false
        );

        if (isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], '/api/') !== false) {
            if (function_exists('ob_get_length') && ob_get_length()) { ob_clean(); }
            if (!headers_sent()) header('Content-Type: application/json');
            http_response_code(503);
            echo json_encode([
                'success' => false,
                'message' => 'Service temporarily unavailable. Please try again.',
                'code' => $isOverload ? 'DB_OVERLOAD' : 'DB_ERROR'
            ]);
            exit;
        }

        die("Database connection failed. Please check error logs.");
    }
}

// Global variable assignment
$pdo = getPDO();

// Ensure the PDO is released at the end of the request
register_shutdown_function(function() {
    global $pdo;
    $pdo = null;
});
