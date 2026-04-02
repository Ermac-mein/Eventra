<?php
// Database configuration
require_once __DIR__ . '/env-loader.php';
// Session configuration is now deferred - endpoints should handle session initialization explicitly
// require_once __DIR__ . '/session-config.php'; // DEFERRED: Moved to individual endpoints
require_once __DIR__ . '/cors-config.php'; // CORS handling for API requests



define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
define('DB_NAME', getenv('DB_DATABASE') ?: 'eventra_db');
define('DB_USER', getenv('DB_USERNAME') ?: 'eventra');
define('DB_PASS', getenv('DB_PASSWORD') ?: '');

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
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
            $error_message = 'Cannot connect to database server. Please ensure MySQL is running.';
        } elseif ($error_code == 1049) {
            $error_message = 'Database does not exist. Please create the database first.';
        } else {
            $error_message = 'Database connection error. Please check server logs for details.';
        }

        echo json_encode(['success' => false, 'message' => $error_message]);
        exit;
    }

    $show_debug = (getenv('APP_DEBUG') === 'true' || (isset($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true'));
    if ($show_debug) {
        die("Database connection failed: " . $e->getMessage());
    }
    die("Database connection failed. Please check error/errors.log for details.");
}
