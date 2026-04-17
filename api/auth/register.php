<?php
/**
 * Eventra — User Registration Handler
 * Handles creation of new accounts (Admins, Clients, Users).
 * Returns JSON with status, message, and next_step.
 */

// ─── 1. Bootstrap: output buffering & error suppression ─────────────────────
ob_start();
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(0);

// ─── 2. CORS & Content-Type Headers (early, before any output) ──────────────
// Allow the frontend origin explicitly (replace with your domain if different)
header('Access-Control-Allow-Origin: https://eventra-website.liveblog365.com');
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-Eventra-Portal, DNT');
header('Content-Type: application/json');

// Handle preflight OPTIONS request immediately
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// ─── 3. Centralised JSON response helper ────────────────────────────────────
function sendJsonResponse(
    bool $success,
    string $message,
    int $httpCode = 200,
    array $extra = []
): void {
    // Clean any accidental output before sending JSON
    if (ob_get_length()) {
        ob_clean();
    }
    http_response_code($httpCode);
    // Ensure headers are set (in case they were overwritten)
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

// ─── 4. Session: use a dedicated pending session (isolated from main login) ──
if (session_status() === PHP_SESSION_NONE) {
    session_name('EVENTRA_PENDING_SESS');
    session_start();
}

// ─── 5. Load configuration ───────────────────────────────────────────────────
$config_path = __DIR__ . '/../../config.php';
if (file_exists($config_path)) {
    require_once $config_path;
}

// ─── 6. Database connection (inside try/catch to handle connection errors) ───
$db_path = __DIR__ . '/../../config/database.php';
if (!file_exists($db_path)) {
    error_log("Registration: database.php missing at $db_path");
    sendJsonResponse(false, 'Service configuration error. Please try again later.', 500);
}

try {
    require_once $db_path;
} catch (Throwable $e) {
    $msg = $e->getMessage();
    error_log("Registration DB load error: " . $msg);
    if (stripos($msg, 'Too many connections') !== false || strpos($msg, '1040') !== false) {
        sendJsonResponse(false, 'Service temporarily overloaded. Please wait a moment and try again.', 503, ['code' => 'DB_OVERLOAD']);
    }
    sendJsonResponse(false, 'Database connection failed. Please try again later.', 500);
}

// ─── 7. Entity resolver helper ───────────────────────────────────────────────
$resolver_path = __DIR__ . '/../../includes/helpers/entity-resolver.php';
if (!file_exists($resolver_path)) {
    error_log("Registration: entity-resolver.php missing at $resolver_path");
    sendJsonResponse(false, 'Service configuration error.', 500);
}
try {
    require_once $resolver_path;
} catch (Throwable $e) {
    error_log("Registration resolver error: " . $e->getMessage());
    sendJsonResponse(false, 'Service configuration error.', 500);
}

// ─── 8. Optional Composer autoload ──────────────────────────────────────────
$autoload_path = __DIR__ . '/../../vendor/autoload.php';
if (file_exists($autoload_path)) {
    try {
        require_once $autoload_path;
    } catch (Throwable $e) {
        error_log("Registration autoload notice: " . $e->getMessage());
        // Non‑fatal
    }
}

// ─── 9. Parse and validate input ────────────────────────────────────────────
$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true);

if (!$data || !is_array($data)) {
    sendJsonResponse(false, 'Invalid request format. Please provide JSON data.', 400);
}

$name          = trim($data['name'] ?? $data['fullName'] ?? '');
$email         = trim($data['email'] ?? '');
$password      = $data['password'] ?? '';
$business_name = trim($data['business_name'] ?? '');
$role          = $data['role'] ?? 'client';

// Basic required fields
if (empty($name) || empty($email) || empty($password)) {
    sendJsonResponse(false, 'Name, email, and password are required.', 400);
}

// Email format
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    sendJsonResponse(false, 'Please provide a valid email address.', 400);
}

// Default business name for clients
if ($role === 'client' && empty($business_name)) {
    $business_name = $name;
}

// ─── 10. Main registration logic ────────────────────────────────────────────
try {
    // 10a. Check if email already registered
    $registrability = canRegisterAs($email, $role);
    if (!$registrability['success']) {
        // Conflict – email exists
        sendJsonResponse(false, $registrability['message'], 409);
    }

    // 10b. Password strength validation
    if (!preg_match('/^(?=.*[A-Z])(?=.*[0-9])(?=.*[!@#$%^&*(),.?":{}|<>]).{8,}$/', $password)) {
        sendJsonResponse(false, 'Password must be at least 8 characters and include one uppercase letter, one number, and one special character.', 400);
    }

    // 10c. Generate OTP and hash password
    $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    // 10d. Store in pending session (DB insert occurs after OTP verification)
    $_SESSION['pending_registration'] = [
        'name'          => $name,
        'email'         => $email,
        'password'      => $hashedPassword,
        'business_name' => $business_name,
        'role'          => $role,
        'otp'           => $otp,
        'expires_at'    => time() + (15 * 60), // 15 minutes
    ];

    // Write session and close it to prevent blocking
    session_write_close();

    // 10e. Send OTP email
    $email_helper_path = __DIR__ . '/../../includes/helpers/email-helper.php';
    if (!file_exists($email_helper_path)) {
        throw new Exception("Email helper missing.");
    }
    require_once $email_helper_path;

    $mailSent = false;
    $mailError = '';

    if (!class_exists('EmailHelper')) {
        // Fallback to native mail()
        $subject = "Verify your Eventra account — OTP: {$otp}";
        $headers = "From: Eventra <noreply@eventra.com>\r\nContent-Type: text/html; charset=UTF-8";
        $body    = "<h2>Confirm your email</h2><p>Hi {$name}, your OTP is: <strong>{$otp}</strong></p>";
        $mailSent = @mail($email, $subject, $body, $headers);
        if (!$mailSent) {
            $mailError = error_get_last()['message'] ?? 'Unknown mail error';
        }
    } else {
        try {
            $mailResult = EmailHelper::sendRegistrationOTP($email, $name, $otp);
            $mailSent   = $mailResult['success'] ?? false;
            $mailError  = $mailResult['message'] ?? '';
        } catch (Throwable $e) {
            $mailError = $e->getMessage();
            error_log("EmailHelper exception: " . $mailError);
        }
    }

    // TEMPORARY BYPASS (remove in production)
    // $mailSent = true;

    if (!$mailSent) {
        // Email failed – still keep session data so user can request resend later
        error_log("Failed to send OTP to {$email}: {$mailError}");
        sendJsonResponse(false, 'We could not send the verification email. Please try again or request a new code later.', 500, [
            'email_status' => 'failed',
            'can_retry'    => true
        ]);
    }

    // Success – OTP sent, frontend should redirect to OTP verification page
    sendJsonResponse(true, 'Verification code sent! Please check your email.', 200, [
        'next_step'    => 'verify_otp',
        'email'        => $email,
        'otp_required' => true,
    ]);

} catch (PDOException $e) {
    $code = $e->getCode();
    $msg  = $e->getMessage();
    error_log("Registration PDO error [{$code}]: {$msg}");

    if ($code === '1040' || stripos($msg, 'Too many connections') !== false) {
        sendJsonResponse(false, 'Service temporarily overloaded. Please try again in a moment.', 503, ['code' => 'DB_OVERLOAD']);
    }
    sendJsonResponse(false, 'A database error occurred. Please try again later.', 500);
} catch (Throwable $e) {
    error_log("Registration critical error: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
    sendJsonResponse(false, 'An internal error occurred. Please try again later.', 500);
}