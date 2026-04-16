<?php
/**
 * Eventra — User Registration Handler
 * Handles creation of new accounts for Admins, Clients, and Users.
 */

// Surgical Error Logging & Performance
require_once __DIR__ . '/../../config.php'; // Centralized config & error reporting

// Do NOT set EVENTRA_CLIENT_SESS here. Use a temporary session for pending registration.
if (session_status() === PHP_SESSION_NONE) {
    session_name('EVENTRA_PENDING_SESS');
    session_start();
}


header('Content-Type: application/json');

// 2. Load Dependencies
$db_path = __DIR__ . '/../../config/database.php';
$resolver_path = __DIR__ . '/../../includes/helpers/entity-resolver.php';

if (!file_exists($db_path)) {
    error_log("Registration failed: Database configuration file missing at $db_path");
    ob_clean(); echo json_encode(['success' => false, 'message' => 'Configuration error: database file missing.']);
    exit;
}
require_once $db_path;

if (!file_exists($resolver_path)) {
    error_log("Registration failed: Resolver helper missing at $resolver_path");
    ob_clean(); echo json_encode(['success' => false, 'message' => 'Configuration error: resolver helper missing.']);
    exit;
}
require_once $resolver_path;
if (!file_exists(__DIR__ . '/../../vendor/autoload.php')) {
    error_log("Registration failed: vendor/autoload.php missing.");
    ob_clean(); echo json_encode(['success' => false, 'message' => 'System configuration error: dependencies missing.']);
    exit;
}
require_once __DIR__ . '/../../vendor/autoload.php';


// 3. Capture and Validate Input
$data = json_decode(file_get_contents("php://input"), true);

if (!$data) {
    ob_clean(); echo json_encode(['success' => false, 'message' => 'Invalid JSON input.']);
    exit;
}

$name = trim($data['name'] ?? $data['fullName'] ?? '');
$email = trim($data['email'] ?? '');
$password = $data['password'] ?? '';
$business_name = trim($data['business_name'] ?? '');
$role = $data['role'] ?? 'client';

if (empty($name) || empty($email) || empty($password)) {
    ob_clean(); echo json_encode(['success' => false, 'message' => 'Name, Email, and Password are required.']);
    exit;
}

if ($role === 'client' && empty($business_name)) {
    // If business_name is missing for a client, we'll default it to the person's name 
    // to maintain compatibility if the frontend hasn't been updated yet.
    $business_name = $name;
}

try {
    // 4. Pre-Registration Validation
    // Check if email already exists
    $registrability = canRegisterAs($email, $role);
    if (!$registrability['success']) {
        ob_clean(); echo json_encode(['success' => false, 'message' => $registrability['message']]);
        exit;
    }

    // Validate Password Strength
    if (!preg_match('/^(?=.*[A-Z])(?=.*[0-9])(?=.*[!@#$%^&*(),.?":{}|<>]).{8,}$/', $password)) {
        echo json_encode([
            'success' => false,
            'message' => 'Password must be at least 8 characters long and include one uppercase letter, one digit, and one special character.'
        ]);
        exit;
    }

    // 5. Deferred Registration Logic (No DB Insert)
    $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    // Clear any existing pending registration
    unset($_SESSION['pending_registration']);

    // Store registration data in session
    $_SESSION['pending_registration'] = [
        'name' => $name,
        'email' => $email,
        'password' => $hashedPassword,
        'business_name' => $business_name,
        'role' => $role,
        'otp' => $otp,
        'expires_at' => time() + (15 * 60) // 15 minutes
    ];

    // 6. Send OTP
    $email_helper_path = __DIR__ . '/../../includes/helpers/email-helper.php';
    if (!file_exists($email_helper_path)) {
        throw new Exception("Email helper missing at $email_helper_path");
    }
    require_once $email_helper_path;

    if (!class_exists('EmailHelper')) {
        error_log("EmailHelper class missing. Falling back to native mail() for $email.");
        // Fallback to native mail() or log OTP for manual testing if needed
        $subject = "Verify your Eventra account — OTP: $otp";
        $headers = "From: Eventra <noreply@eventra.com>\r\nContent-Type: text/html; charset=UTF-8";
        $message = "<h2>Confirm your email</h2><p>Hi $name, your OTP is: <strong>$otp</strong></p>";
        @mail($email, $subject, $message, $headers);
        
        // If we choose to allow registration to proceed even if email fails in dev
        $mailResult = ['success' => true, 'message' => 'Fallback mail sent'];
    } else {
        try {
            $mailResult = EmailHelper::sendRegistrationOTP($email, $name, $otp);
        } catch (Throwable $mailEx) {
            error_log("EmailHelper execution failed: " . $mailEx->getMessage());
            $mailResult = ['success' => false, 'message' => $mailEx->getMessage()];
        }
    }

    if (!$mailResult['success']) {
        error_log("Failed to send OTP to $email: " . $mailResult['message']);
        echo json_encode(['success' => false, 'message' => 'Failed to send verification email. Please try again or contact support.']);
        exit;
    }

    ob_clean(); echo json_encode([
        'success' => true,
        'message' => 'Verification code sent! Please check your email to complete registration.',
        'otp_required' => true,
        'email' => $email
    ]);

} catch (Throwable $e) {
    error_log("Registration Critical Error: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'An internal server error occurred. Please try again later.',
        'error_type' => get_class($e)
    ]);
}


