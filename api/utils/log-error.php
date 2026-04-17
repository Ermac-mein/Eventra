<?php
/**
 * Frontend Error Logging Endpoint
 * Receives JS errors and logs them to the centralized PHP error log.
 */

// Enable error logging for this script itself
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../../logs/php-errors.log');
error_reporting(E_ALL);

// Ensure we return JSON
header('Content-Type: application/json');

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Get the raw POST data
$json = file_get_contents('php://input');
$data = json_decode($json, true);

if (!$data) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON']);
    exit;
}

// Extract error details
$message    = $data['message'] ?? 'Unknown JS Error';
$url        = $data['url'] ?? 'Unknown URL';
$line       = $data['line'] ?? 'N/A';
$column     = $data['column'] ?? 'N/A';
$stack      = $data['stack'] ?? 'No stack trace';
$userAgent  = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown User Agent';
$remoteAddr = $_SERVER['REMOTE_ADDR'] ?? 'Unknown IP';

// We can also try to get the user ID from the session if available
session_start();
$userId = $_SESSION['user_id'] ?? $_SESSION['client_id'] ?? $_SESSION['admin_id'] ?? 'Guest';
$role   = $_SESSION['role'] ?? 'Unknown Role';

// Format the log message
$logEntry = sprintf(
    "[FRONTEND ERROR] [%s] [User: %s (%s)] [IP: %s]\n" .
    "Message: %s\n" .
    "Source: %s (Line: %s, Col: %s)\n" .
    "User Agent: %s\n" .
    "Stack Trace:\n%s\n" .
    "--------------------------------------------------\n",
    date('Y-m-d H:i:s'),
    $userId,
    $role,
    $remoteAddr,
    $message,
    $url,
    $line,
    $column,
    $userAgent,
    $stack
);

// Write to the error log
error_log($logEntry);

echo json_encode(['success' => true]);
