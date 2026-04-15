<?php
/**
 * Eventra — User Registration Handler
 * Handles creation of new accounts for Admins, Clients, and Users.
 */

// 0. Emergency Error Capture for 500 debugging
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    if (!(error_reporting() & $errno)) return false;
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Internal Server Error detected by handler.',
        'error' => $errstr,
        'file' => basename($errfile),
        'line' => $errline
    ]);
    exit;
});

set_exception_handler(function($e) {
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Uncaught Exception detected by handler.',
        'error' => $e->getMessage(),
        'type' => get_class($e),
        'file' => basename($e->getFile()),
        'line' => $e->getLine()
    ]);
    exit;
});

header('Content-Type: application/json');

// Check if dependencies exist before requiring
$db_path = __DIR__ . '/../../config/database.php';
$resolver_path = __DIR__ . '/../../includes/helpers/entity-resolver.php';

if (!file_exists($db_path)) {
    echo json_encode(['success' => false, 'message' => 'Configuration error: database file missing.']);
    exit;
}
require_once $db_path;

if (!file_exists($resolver_path)) {
    echo json_encode(['success' => false, 'message' => 'Configuration error: resolver helper missing.']);
    exit;
}
require_once $resolver_path;

$data = json_decode(file_get_contents("php://input"), true);

if (!$data) {
    echo json_encode(['success' => false, 'message' => 'Invalid JSON input.']);
    exit;
}

if (!isset($data['name']) || !isset($data['email']) || !isset($data['password'])) {
    echo json_encode(['success' => false, 'message' => 'All fields are required.']);
    exit;
}

$name = trim($data['name']);
$email = trim($data['email']);
$password = $data['password'];
$role = $data['role'] ?? 'client'; // Default to client registration if not specified

// 0. Ensure session is started for deferred registration storage
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

try {
    // 1. Prevent Cross-Entity Collision
    $registrability = canRegisterAs($email, $role);
    if (!$registrability['success']) {
        logSecurityEvent(null, $email, 'registration_failure', 'password', "Registration blocked: " . $registrability['message']);
        echo json_encode(['success' => false, 'message' => $registrability['message']]);
        exit;
    }

    // 2. Validate Password Strength (Uppercase, Digit, Special Character, Min 8 chars)
    if (!preg_match('/^(?=.*[A-Z])(?=.*[0-9])(?=.*[!@#$%^&*(),.?":{}|<>]).{8,}$/', $password)) {
        echo json_encode([
            'success' => false,
            'message' => 'Password must be at least 8 characters long and include one uppercase letter, one digit, and one special character.'
        ]);
        exit;
    }

    // 3. Generate 6-digit OTP
    $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    // 4. Store registration data in session
    $_SESSION['pending_registration'] = [
        'name' => $name,
        'email' => $email,
        'password' => $hashedPassword,
        'role' => $role,
        'otp' => $otp,
        'expires_at' => time() + (15 * 60) // 15 minutes
    ];

    // 5. Send OTP via Email
    $email_helper_path = __DIR__ . '/../../includes/helpers/email-helper.php';
    if (!file_exists($email_helper_path)) {
        throw new Exception("Email helper missing at $email_helper_path");
    }
    require_once $email_helper_path;

    $mailResult = EmailHelper::sendRegistrationOTP($email, $name, $otp);

    if (!$mailResult['success']) {
        // Log the error but you might still want the user to know registration "started"
        error_log("Failed to send registration OTP to $email: " . $mailResult['message']);
        
        // In some cases, you might want to fail the request if the email can't be sent
        echo json_encode(['success' => false, 'message' => 'Failed to send verification email. Please try again.']);
        exit;
    }

    echo json_encode([
        'success' => true,
        'message' => 'Verification code sent! Please check your email to complete registration.',
        'otp_required' => true,
        'email' => $email
    ]);

} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    $message = 'Registration failed. Please try again.';
    $errorCode = $e->getCode();
    
    if ($errorCode == 23000) { // Integrity constraint violation
        if (strpos($e->getMessage(), 'uq_auth_email') !== false || strpos($e->getMessage(), 'Duplicate entry') !== false) {
            $message = 'This email address is already registered. Please use a different email or log in.';
        } else {
            $message = 'Registration failed: Duplicate entry or constraint violation.';
        }
    } else {
        error_log("Registration PDO Error ($errorCode): " . $e->getMessage());
    }
    
    echo json_encode(['success' => false, 'message' => $message]);

} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    error_log("Registration Critical Error: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
    
    echo json_encode([
        'success' => false, 
        'message' => 'An unexpected error occurred during registration. Details: ' . $e->getMessage(),
        'error_type' => get_class($e)
    ]);
}

