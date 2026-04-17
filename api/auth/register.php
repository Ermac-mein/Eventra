<?php
/**
 * Eventra — User Registration Handler
 * Enhanced with detailed logging for troubleshooting frontend/backend issues.
 */

// ─── 1. Bootstrap & Logging Setup ────────────────────────────────────────────
ob_start();
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL); // Report all errors, but don't display
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../../logs/php-errors.log'); // Adjust path if needed

// Custom log function for structured messages
function regLog(string $level, string $message, array $context = []): void
{
    $logEntry = date('Y-m-d H:i:s') . " [REGISTER] [$level] $message";
    if (!empty($context)) {
        $logEntry .= ' | Context: ' . json_encode($context, JSON_UNESCAPED_SLASHES);
    }
    error_log($logEntry);
}

// ─── 2. CORS & Headers ──────────────────────────────────────────────────────
header('Access-Control-Allow-Origin: https://eventra-website.liveblog365.com');
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-Eventra-Portal, DNT');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// ─── 3. Session Initialisation ───────────────────────────────────────────────
if (session_status() === PHP_SESSION_NONE) {
    session_name('EVENTRA_PENDING_SESS');
    session_start();
}
$sessionId = session_id();
regLog('INFO', "Request started", ['session_id' => $sessionId, 'method' => $_SERVER['REQUEST_METHOD']]);

// ─── 4. Centralised JSON Responder (with logging) ───────────────────────────
function sendJsonResponse(
    bool $success,
    string $message,
    int $httpCode = 200,
    array $extra = []
): void {
    global $sessionId;

    // Log the response being sent
    regLog($success ? 'SUCCESS' : 'ERROR', "Response: $message", [
        'http_code' => $httpCode,
        'extra' => $extra,
        'session_id' => $sessionId
    ]);

    if (ob_get_length()) {
        ob_clean();
    }
    http_response_code($httpCode);
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: https://eventra-website.liveblog365.com');
    header('Access-Control-Allow-Credentials: true');

    $response = array_merge([
        'success' => $success,
        'message' => $message,
    ], $extra);

    echo json_encode($response);
    exit;
}

// ─── 5. Load Configuration & Dependencies ────────────────────────────────────
$config_path = __DIR__ . '/../../config.php';
if (file_exists($config_path)) {
    require_once $config_path;
}

$db_path = __DIR__ . '/../../config/database.php';
if (!file_exists($db_path)) {
    regLog('CRITICAL', "database.php missing", ['path' => $db_path]);
    sendJsonResponse(false, 'Service configuration error. Please try again later.', 500);
}
try {
    require_once $db_path;
    regLog('INFO', "Database loaded successfully");
} catch (Throwable $e) {
    regLog('CRITICAL', "DB load failed: " . $e->getMessage(), ['code' => $e->getCode()]);
    if (stripos($e->getMessage(), 'Too many connections') !== false || strpos($e->getCode(), '1040') !== false) {
        sendJsonResponse(false, 'Service temporarily overloaded. Please wait a moment.', 503, ['code' => 'DB_OVERLOAD']);
    }
    sendJsonResponse(false, 'Database connection failed.', 500);
}

$resolver_path = __DIR__ . '/../../includes/helpers/entity-resolver.php';
if (!file_exists($resolver_path)) {
    regLog('CRITICAL', "entity-resolver.php missing", ['path' => $resolver_path]);
    sendJsonResponse(false, 'Service configuration error.', 500);
}
try {
    require_once $resolver_path;
} catch (Throwable $e) {
    regLog('CRITICAL', "Resolver load failed: " . $e->getMessage());
    sendJsonResponse(false, 'Service configuration error.', 500);
}

// Optional autoload
$autoload_path = __DIR__ . '/../../vendor/autoload.php';
if (file_exists($autoload_path)) {
    try {
        require_once $autoload_path;
    } catch (Throwable $e) {
        regLog('WARNING', "Autoload failed: " . $e->getMessage());
    }
}

// ─── 6. Parse Input ──────────────────────────────────────────────────────────
$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true);

// Log sanitized input (hide password)
$logData = $data ?? [];
if (isset($logData['password'])) {
    $logData['password'] = '***REDACTED***';
}
regLog('INFO', "Received payload", ['input' => $logData]);

if (!$data || !is_array($data)) {
    regLog('ERROR', "Invalid JSON input", ['raw' => substr($rawInput, 0, 200)]);
    sendJsonResponse(false, 'Invalid request format. Please provide JSON data.', 400);
}

$name = trim($data['name'] ?? $data['fullName'] ?? '');
$email = trim($data['email'] ?? '');
$password = $data['password'] ?? '';
$business_name = trim($data['business_name'] ?? '');
$role = $data['role'] ?? 'client';

if (empty($name) || empty($email) || empty($password)) {
    regLog('ERROR', "Missing required fields", ['name' => $name, 'email' => $email, 'has_password' => !empty($password)]);
    sendJsonResponse(false, 'Name, email, and password are required.', 400);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    regLog('ERROR', "Invalid email format", ['email' => $email]);
    sendJsonResponse(false, 'Please provide a valid email address.', 400);
}

if ($role === 'client' && empty($business_name)) {
    $business_name = $name;
}

// ─── 7. Main Registration Logic ──────────────────────────────────────────────
try {
    // 7a. Check email already registered
    $registrability = canRegisterAs($email, $role);
    if (!$registrability['success']) {
        regLog('WARNING', "Email already registered", ['email' => $email, 'role' => $role]);
        sendJsonResponse(false, $registrability['message'], 409);
    }

    // 7b. Password strength
    if (!preg_match('/^(?=.*[A-Z])(?=.*[0-9])(?=.*[!@#$%^&*(),.?":{}|<>]).{8,}$/', $password)) {
        regLog('ERROR', "Weak password", ['email' => $email]);
        sendJsonResponse(false, 'Password must be at least 8 characters with uppercase, number, and special character.', 400);
    }

    // 7c. Generate OTP and hash
    $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    // 7d. Store in session
    $_SESSION['pending_registration'] = [
        'name' => $name,
        'email' => $email,
        'password' => $hashedPassword,
        'business_name' => $business_name,
        'role' => $role,
        'otp' => $otp,
        'expires_at' => time() + (15 * 60),
    ];

    session_write_close();
    regLog('INFO', "Session data stored", ['session_id' => $sessionId, 'email' => $email]);

    // 7e. Send OTP email
    $email_helper_path = __DIR__ . '/../../includes/helpers/email-helper.php';
    if (!file_exists($email_helper_path)) {
        regLog('ERROR', "Email helper missing", ['path' => $email_helper_path]);
        throw new Exception("Email helper missing.");
    }
    require_once $email_helper_path;

    $mailSent = false;
    $mailError = '';

    if (!class_exists('EmailHelper')) {
        $subject = "Verify your Eventra account — OTP: {$otp}";
        $headers = "From: Eventra <noreply@eventra.com>\r\nContent-Type: text/html; charset=UTF-8";
        $body = "<h2>Confirm your email</h2><p>Hi {$name}, your OTP is: <strong>{$otp}</strong></p>";
        $mailSent = @mail($email, $subject, $body, $headers);
        $mailError = $mailSent ? '' : (error_get_last()['message'] ?? 'Unknown mail error');
        regLog($mailSent ? 'INFO' : 'ERROR', "Native mail() result", ['success' => $mailSent, 'error' => $mailError]);
    } else {
        try {
            $mailResult = EmailHelper::sendRegistrationOTP($email, $name, $otp);
            $mailSent = $mailResult['success'] ?? false;
            $mailError = $mailResult['message'] ?? '';
            regLog($mailSent ? 'INFO' : 'ERROR', "EmailHelper result", ['success' => $mailSent, 'message' => $mailError]);
        } catch (Throwable $e) {
            $mailError = $e->getMessage();
            regLog('ERROR', "EmailHelper exception: " . $mailError);
        }
    }

    // TEMPORARY BYPASS (remove in production)
    // $mailSent = true;

    if (!$mailSent) {
        sendJsonResponse(false, 'We could not send the verification email. Please try again.', 500, [
            'email_status' => 'failed',
            'can_retry' => true
        ]);
    }

    // Success
    sendJsonResponse(true, 'Verification code sent! Please check your email.', 200, [
        'next_step' => 'verify_otp',
        'email' => $email,
        'otp_required' => true,
    ]);

} catch (PDOException $e) {
    regLog('CRITICAL', "PDOException: " . $e->getMessage(), [
        'code' => $e->getCode(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
    if ($e->getCode() === '1040' || stripos($e->getMessage(), 'Too many connections') !== false) {
        sendJsonResponse(false, 'Service temporarily overloaded.', 503, ['code' => 'DB_OVERLOAD']);
    }
    sendJsonResponse(false, 'A database error occurred.', 500);
} catch (Throwable $e) {
    regLog('CRITICAL', "Throwable: " . $e->getMessage(), [
        'class' => get_class($e),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
    sendJsonResponse(false, 'An internal error occurred.', 500);
}