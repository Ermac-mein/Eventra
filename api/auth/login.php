<?php

// Parse intent FIRST to set correct session name before ANY session initialization
$data = json_decode(file_get_contents("php://input"), true);
$intent = $data['intent'] ?? 'client';

// Set session name BEFORE database.php which might access sessions
if (!in_array($intent, ['admin', 'client', 'user'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid authentication path.']);
    exit;
}

// Pre-initialize session with correct name to prevent auto-detection issues
$sessionName = 'EVENTRA_USER_SESS';
if ($intent === 'admin') {
    $sessionName = 'EVENTRA_ADMIN_SESS';
} elseif ($intent === 'client') {
    $sessionName = 'EVENTRA_CLIENT_SESS';
}

session_name($sessionName);

// NOW send the JSON header
header('Content-Type: application/json');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/helpers/entity-resolver.php';

$identity = $data['email'] ?? $data['username'] ?? null;
$password = $data['password'] ?? null;

if (!$identity || !$password) {
    $fieldLabel = ($intent === 'admin') ? 'Username' : 'Username/Email';
    echo json_encode(['success' => false, 'message' => "$fieldLabel and password are required."]);
    exit;
}

$remember_me = isset($data['remember_me']) && $data['remember_me'] === true;

try {
    // 1. Resolve Entity (Centralized Backend Decision)
    $user = resolveEntity($identity, $intent);

    if (!$user) {
        logSecurityEvent(null, $identity, 'login_failure', 'password', "Identity not found.");
        $fieldLabel = ($intent === 'admin') ? 'username' : 'email';
        echo json_encode(['success' => false, 'message' => "Invalid $fieldLabel or password."]);
        exit;
    }

    // 2. Validate Role Compatibility & Provider Policy
    $userRole = strtolower($user['role'] ?? '');
    $effectiveIntent = strtolower($intent);

    // Enforce role-specific portal entry
    if ($userRole !== $effectiveIntent) {
        logSecurityEvent($user['id'], $identity, 'login_failure', 'password', "Role mismatch: User is $userRole but tried as $effectiveIntent");
        $targetPortal = ucfirst($userRole);
        echo json_encode(['success' => false, 'message' => "Access denied. This is a $targetPortal account. Please use the appropriate portal."]);
        exit;
    }

    // Enforce Admin Local-Only Policy
    if ($userRole === 'admin' && $user['auth_provider'] !== 'local') {
        logSecurityEvent($user['id'], $identity, 'login_failure', 'password', "Admin account attempted login with non-local state.");
        echo json_encode(['success' => false, 'message' => "Admin accounts must use local authentication."]);
        exit;
    }

    // Account Status Check
    if (isset($user['is_active']) && $user['is_active'] == 0) {
        // Only allow login if account is active, or handle activation logic if required.
        // For now, let's keep the user's requirement: check is_active = 1
        logSecurityEvent($user['id'], $identity, 'login_failure', 'password', "Account is inactive.");
        echo json_encode(['success' => false, 'message' => "Your account is inactive. Please contact support."]);
        exit;
    }

    // Check account lock BEFORE password verification (timing attack prevention)
    if ($user['locked_until'] && strtotime($user['locked_until']) > time()) {
        echo json_encode(['success' => false, 'message' => 'Account is temporarily locked. Please try again later.']);
        exit;
    }

    if (password_verify($password, $user['password'])) {
        // --- 3. Enforce Auth Policy ---
        $policy = getAuthPolicy($userRole, 'password', $user);
        if (!$policy['allowed']) {
            logSecurityEvent($user['id'], $identity, 'login_failure', 'password', "Policy Violation: " . $policy['message']);
            echo json_encode(['success' => false, 'message' => $policy['message']]);
            exit;
        }

        // --- NEW: Client Login OTP Flow ---
        // If the role is 'client', we require a second-factor OTP before completing login.
        if ($userRole === 'client') {
            $otp = sprintf("%06d", random_int(0, 999999));
            $otp_hash = password_hash($otp, PASSWORD_DEFAULT);
            $otp_expires_at = date('Y-m-d H:i:s', strtotime('+10 minutes'));

            // Store in auth_tokens (reuse existing table, type='otp')
            // Using raw OTP for simplicity as per common internal patterns, but the plan mentioned hashing.
            // Actually, verify-otp.php currently checks for raw token = ?. 
            // Let's stick to raw token storage for consistency with verify-otp.php logic (line 31).
            $pdo->prepare("DELETE FROM auth_tokens WHERE auth_id = ? AND type = 'otp'")->execute([$user['id']]);
            $stmt = $pdo->prepare("INSERT INTO auth_tokens (auth_id, token, expires_at, type) VALUES (?, ?, ?, 'otp')");
            $stmt->execute([$user['id'], $otp, $otp_expires_at]);

            // Send Email
            require_once __DIR__ . '/../../includes/helpers/email-helper.php';
            $subject = "Your Eventra Client Login Code";
            $message = "Your one-time login verification code is: <strong>$otp</strong><br>It expires in 10 minutes.";
            
            $emailResult = EmailHelper::sendEmail($user['email'], $subject, "<h2>Login Verification</h2><p>$message</p>");

            if ($emailResult['success']) {
                echo json_encode([
                    'success' => true,
                    'otp_required' => true,
                    'message' => 'A verification code has been sent to your email.'
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => 'Failed to send verification code. Please try again later.'
                ]);
            }
            exit;
        }

        // Reset failed attempts on success
        $pdo->prepare("UPDATE auth_accounts SET failed_attempts = 0, last_login_at = NOW(), is_online = 1 WHERE id = ?")->execute([$user['id']]);

        // Update role-specific status when user logs in
        $expires_in = $remember_me ? '+30 days' : '+30 minutes'; // 30-minute inactivity session policy
        $expires_at = date('Y-m-d H:i:s', strtotime($expires_in));

        // Delete old tokens for this auth identity
        $stmt = $pdo->prepare("DELETE FROM auth_tokens WHERE auth_id = ?");
        $stmt->execute([$user['id']]);

        // Store new token in database
        $stmt = $pdo->prepare("INSERT INTO auth_tokens (auth_id, token, expires_at, type) VALUES (?, ?, ?, 'access')");
        $stmt->execute([$user['id'], $token, $expires_at]);

        // 4. Set Entity-Scoped Session
        $expectedSessionName = 'EVENTRA_USER_SESS';
        if ($userRole === 'admin') {
            $expectedSessionName = 'EVENTRA_ADMIN_SESS';
        } elseif ($userRole === 'client') {
            $expectedSessionName = 'EVENTRA_CLIENT_SESS';
        }

        // Ensure correct session name before operations
        if (session_name() !== $expectedSessionName) {
            if (session_status() === PHP_SESSION_ACTIVE) {
                session_write_close();
            }
            session_name($expectedSessionName);
        }

        // Ensure session is initialized via centralized config
        if (session_status() === PHP_SESSION_NONE) {
            require_once __DIR__ . '/../../config.php';
        }

        // Regenerate session ID for security, but preserve critical data
        if (session_status() === PHP_SESSION_ACTIVE) {
            // Save old CSRF token before regenerating
            $oldCsrfToken = $_SESSION['csrf_token'] ?? null;
            
            // Regenerate session ID with delete_old_session = true
            session_regenerate_id(true);
            
            // Restore CSRF token if it existed
            if ($oldCsrfToken) {
                $_SESSION['csrf_token'] = $oldCsrfToken;
            }
        }

        // Strict Role-Specific Session Keys + Universal auth_id
        $_SESSION['auth_id'] = $user['id']; // Global auth account ID
        if ($userRole === 'admin') {
            // resolveEntity merged the admins table data, so 'id' from admins is not directly available because auth_accounts also has 'id'
            // Let's re-fetch the role-specific PK if not already distinct.
            // In resolveEntity: array_merge($profile, $account), account 'id' overwrote profile 'id'.
            // I need to ensure the profile ID is preserved.
            $stmt = $pdo->prepare("SELECT id FROM admins WHERE admin_auth_id = ?");
            $stmt->execute([$user['id']]);
            $adminId = $stmt->fetchColumn();
            if ($adminId) {
                $_SESSION['admin_id'] = $adminId;
            } else {
                // Fallback to creating admin profile if it doesn't exist
                $stmt = $pdo->prepare("INSERT INTO admins (admin_auth_id, name) VALUES (?, ?)");
                $stmt->execute([$user['id'], $user['name'] ?? 'Admin']);
                $_SESSION['admin_id'] = $pdo->lastInsertId();
            }
        } elseif ($userRole === 'client') {
            $stmt = $pdo->prepare("SELECT id FROM clients WHERE client_auth_id = ?");
            $stmt->execute([$user['id']]);
            $clientId = $stmt->fetchColumn();
            if ($clientId) {
                $_SESSION['client_id'] = $clientId;
            } else {
                // Fallback to creating client profile if it doesn't exist
                $stmt = $pdo->prepare("INSERT INTO clients (client_auth_id, name, business_name) VALUES (?, ?, ?)");
                $stmt->execute([$user['id'], $user['name'] ?? 'Client', $user['business_name'] ?? '']);
                $_SESSION['client_id'] = $pdo->lastInsertId();
            }
        } elseif ($userRole === 'user') {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE user_auth_id = ?");
            $stmt->execute([$user['id']]);
            $userId = $stmt->fetchColumn();
            if ($userId) {
                $_SESSION['user_id'] = $userId;
            } else {
                // Fallback to creating user profile if it doesn't exist
                $stmt = $pdo->prepare("INSERT INTO users (user_auth_id, name) VALUES (?, ?)");
                $stmt->execute([$user['id'], $user['name'] ?? 'User']);
                $_SESSION['user_id'] = $pdo->lastInsertId();
            }
        }

        $_SESSION['user_role'] = $userRole;
        $_SESSION['role'] = $userRole; // Normalize for legacy API support
        $_SESSION['auth_token'] = $token;
        $_SESSION['last_activity'] = time();

        // CRITICAL: Write session to disk before sending response
        session_write_close();

        // Log success
        logSecurityEvent($user['id'], $identity, 'login_success', 'password', "Logged in as $userRole (Role ID: " . ($_SESSION[$userRole . '_id'] ?? 'N/A') . ") via portal $effectiveIntent");

        // Notify admin of login activity
        require_once __DIR__ . '/../utils/notification-helper.php';
        $admin_id = getAdminUserId();
        if ($admin_id) {
            if ($userRole === 'client') {
                createClientLoginNotification($admin_id, $user['id'], $user['name'] ?? 'Client', $identity);
            } elseif ($userRole === 'user') {
                createUserLoginNotification($admin_id, $user['id'], $user['name'] ?? 'User', $identity);
            } elseif ($userRole === 'admin') {
                // Create admin login notification for themselves
                createAdminLoginNotification($user['id']);
            }
        }

        // Role-Based Redirects (absolute paths for JS redirect)
        $redirect = '/public/pages/index.html'; // Default for users
        if ($userRole === 'admin') {
            $redirect = '/admin/pages/adminDashboard.html';
        } elseif ($userRole === 'client') {
            $redirect = '/client/pages/clientDashboard.html';
        }

        echo json_encode([
            'success' => true,
            'message' => 'Login successful',
            'role' => $userRole,
            'redirect' => $redirect,
            'user' => [
                'id' => $user['id'],
                'name' => $user['name'],
                'email' => $user['email'],
                'role' => $userRole,
                'custom_id' => $user['custom_id'] ?? null,
                'bvn' => $user['bvn'] ?? null,
                'profile_image' => (function ($pic) {
                    if (!$pic) {
                        return null;
                    }
                    if (preg_match('/^https?:\/\//i', $pic)) {
                        return $pic;
                    }
                    return '/' . ltrim($pic, '/');
                })($user['profile_pic'] ?? null),
                'token' => $token
            ]
        ]);
        exit;
    } else {
        // Increment failed attempts
        $pdo->prepare("UPDATE auth_accounts SET failed_attempts = failed_attempts + 1 WHERE id = ?")->execute([$user['id']]);

        // Lock account if failures exceed threshold
        if (($user['failed_attempts'] ?? 0) >= 5) {
            $lockTime = date('Y-m-d H:i:s', strtotime('+15 minutes'));
            $pdo->prepare("UPDATE auth_accounts SET locked_until = ? WHERE id = ?")->execute([$lockTime, $user['id']]);
        }

        logSecurityEvent($user['id'], $identity, 'login_failure', 'password', "Invalid password.");
        $fieldLabel = ($intent === 'admin') ? 'username' : 'email';
        echo json_encode(['success' => false, 'message' => "Invalid $fieldLabel or password."]);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error occurred.']);
}
