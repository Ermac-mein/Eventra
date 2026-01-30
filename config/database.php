<?php
// Database configuration
require_once __DIR__ . '/env-loader.php';
require_once __DIR__ . '/session-config.php'; // Centralized session handling
require_once __DIR__ . '/cors-config.php'; // CORS handling for API requests



define('DB_HOST', $_ENV['DB_HOST'] ?? '127.0.0.1');
define('DB_NAME', $_ENV['DB_DATABASE'] ?? 'eventra_db');
define('DB_USER', $_ENV['DB_USERNAME'] ?? 'eventra');
define('DB_PASS', $_ENV['DB_PASSWORD'] ?? 'Eventra@@12345');

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

    die("Database connection failed. Please check error/errors.log for details.");
}
?>