<?php

header('Content-Type: application/json');
require_once __DIR__ . '/../../config/database.php';

$data = json_decode(file_get_contents("php://input"), true);

if ((!isset($data['identity']) && !isset($data['email'])) || !isset($data['otp'])) {
    echo json_encode(['success' => false, 'message' => 'Identity and OTP are required.']);
    exit;
}

$identity = $data['identity'] ?? $data['email'];
$otp = $data['otp'] ?? null;
$intent = $data['intent'] ?? 'password_reset';

try {
    // 0. Ensure standardized session initialization
    if ($intent === 'registration_verify') {
        session_name('EVENTRA_CLIENT_SESS');
    }
    require_once __DIR__ . '/../../config/session-config.php';
    require_once __DIR__ . '/../../includes/helpers/entity-resolver.php';
    $pdo = getPDO(); // Singleton

    // 1. Handle Registration Verification Intent
    if ($intent === 'registration_verify') {
        if (!isset($_SESSION['pending_registration'])) {
            echo json_encode(['success' => false, 'message' => 'Verification context expired or missing. Please try signing up again.']);
            exit;
        }

        $pending = $_SESSION['pending_registration'];
        
        // Basic safety check for email mismatch
        if ($pending['email'] !== $identity) {
             echo json_encode(['success' => false, 'message' => 'Email mismatch.']);
             exit;
        }

        // Verify OTP
        if ($pending['otp'] !== $otp) {
            echo json_encode(['success' => false, 'message' => 'Invalid verification code.']);
            exit;
        }

        // Check expiry
        if (time() > $pending['expires_at']) {
            unset($_SESSION['pending_registration']);
            echo json_encode(['success' => false, 'message' => 'Verification code expired. Please try signing up again.']);
            exit;
        }

        // OTP is valid! Persist records.
        $pdo->beginTransaction();
        try {
            // Load necessary utils
            require_once __DIR__ . '/../utils/id-generator.php';
            require_once __DIR__ . '/../utils/notification-helper.php';

            $email = $pending['email'];
            $hashedPassword = $pending['password'];
            $role = $pending['role'];
            $name = $pending['name'];

            // Create auth_account
            $username = explode('@', $email)[0] . '_' . substr(bin2hex(random_bytes(2)), 0, 4);
            $stmt = $pdo->prepare("INSERT INTO auth_accounts (email, password, role, auth_provider, is_active, username, email_verified_at) VALUES (?, ?, ?, 'local', 1, ?, NOW())");
            $stmt->execute([$email, $hashedPassword, $role, $username]);
            $auth_id = $pdo->lastInsertId();

            $role_id = null;
            $customId = null;

            // Insert into role-specific table
            if ($role === 'client') {
                $customId = generateClientId($pdo);
                $business_name = $pending['business_name'] ?? $name;
                $stmt = $pdo->prepare("INSERT INTO clients (client_auth_id, custom_id, business_name, name) VALUES (?, ?, ?, ?)");
                $stmt->execute([$auth_id, $customId, $business_name, $name]);
            } elseif ($role === 'admin') {
                $stmt = $pdo->prepare("INSERT INTO admins (admin_auth_id, name) VALUES (?, ?)");
                $stmt->execute([$auth_id, $name]);
                $role_id = $pdo->lastInsertId();
            } elseif ($role === 'user') {
                $customId = generateUserId($pdo);
                $stmt = $pdo->prepare("INSERT INTO users (user_auth_id, custom_id, name) VALUES (?, ?, ?)");
                $stmt->execute([$auth_id, $customId, $name]);
                $role_id = $pdo->lastInsertId();
            }

            $pdo->commit();

            // Clear session
            unset($_SESSION['pending_registration']);

            logSecurityEvent($auth_id, $email, 'registration_success', 'password', "New $role registered via OTP: $name");

            // Complete Login Flow
            $_SESSION['auth_id'] = $auth_id;
            $_SESSION['user_role'] = $role;
            $_SESSION['role'] = $role;
            
            // Set role-specific session ID
            if ($role === 'client') {
                $stmt = $pdo->prepare("SELECT id FROM clients WHERE client_auth_id = ?");
                $stmt->execute([$auth_id]);
                $_SESSION['client_id'] = $stmt->fetchColumn();
                $dashboard = '/client/pages/clientDashboard.html';
            } elseif ($role === 'admin') {
                $stmt = $pdo->prepare("SELECT id FROM admins WHERE admin_auth_id = ?");
                $stmt->execute([$auth_id]);
                $_SESSION['admin_id'] = $stmt->fetchColumn();
                $dashboard = '/admin/pages/adminDashboard.html';
            } else {
                $stmt = $pdo->prepare("SELECT id FROM users WHERE user_auth_id = ?");
                $stmt->execute([$auth_id]);
                $_SESSION['user_id'] = $stmt->fetchColumn();
                $dashboard = '/public/pages/index.html';
            }

            // Generate access token
            $token = bin2hex(random_bytes(32));
            $expires_at_token = date('Y-m-d H:i:s', strtotime('+2 hours'));
            $pdo->prepare("INSERT INTO auth_tokens (auth_id, token, expires_at, type) VALUES (?, ?, ?, 'access')")->execute([$auth_id, $token, $expires_at_token]);
            $_SESSION['auth_token'] = $token;

            echo json_encode([
                'success' => true,
                'message' => 'Verification successful! Logged in.',
                'redirect' => $dashboard,
                'user' => [
                    'id' => $auth_id,
                    'name' => $name,
                    'role' => $role,
                    'token' => $token
                ]
            ]);
            exit;

        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    // ── LEGACY FLOWS (Login/Password Reset) ───────────────────────────
    // Resolve user by identity (email or phone)
    $user = resolveEntity($identity, 'client');
    $auth_id = $user['id'] ?? null;

    if (!$auth_id) {
        echo json_encode(['success' => false, 'message' => 'Invalid request.']);
        exit;
    }

    // Verify OTP for existing accounts
    $stmt = $pdo->prepare("
        SELECT id FROM auth_tokens 
        WHERE auth_id = ? AND token = ? AND type = 'otp' 
        AND revoked = 0 AND expires_at > NOW()
        ORDER BY created_at DESC LIMIT 1
    ");
    $stmt->execute([$auth_id, $otp]);
    $token_row = $stmt->fetch();

    if ($token_row) {
        // OTP is valid. Revoke it.
        $pdo->prepare("UPDATE auth_tokens SET revoked = 1 WHERE id = ?")->execute([$token_row['id']]);

        if ($intent === 'client_login') {
            // ... (rest of legacy login logic)
            // Reset failed attempts
            $pdo->prepare("UPDATE auth_accounts SET failed_attempts = 0, last_login_at = NOW(), is_online = 1 WHERE id = ?")->execute([$auth_id]);
            
            // Set session name if needed (optional here as it's already started)
            // session_name('EVENTRA_CLIENT_SESS'); 

            // Generate access token
            $token = bin2hex(random_bytes(32));
            $expires_at = date('Y-m-d H:i:s', strtotime('+2 hours'));
            $pdo->prepare("INSERT INTO auth_tokens (auth_id, token, expires_at, type) VALUES (?, ?, ?, 'access')")->execute([$auth_id, $token, $expires_at]);

            // Set role-specific PK
            $stmt = $pdo->prepare("SELECT id, name, business_name FROM clients WHERE client_auth_id = ?");
            $stmt->execute([$auth_id]);
            $client = $stmt->fetch();
            
            $_SESSION['auth_id'] = $auth_id;
            $_SESSION['client_id'] = $client['id'];
            $_SESSION['user_role'] = 'client';
            $_SESSION['role'] = 'client';
            $_SESSION['auth_token'] = $token;

            echo json_encode([
                'success' => true,
                'message' => 'Login verified.',
                'redirect' => '/client/pages/clientDashboard.html',
                'user' => [
                    'id' => $auth_id,
                    'name' => $client['name'],
                    'role' => 'client',
                    'token' => $token
                ]
            ]);
        } else {
            // ── Legacy Password Reset Flow ──────────────────────────────────────
            $reset_token = bin2hex(random_bytes(32));
            $expires_at  = date('Y-m-d H:i:s', strtotime('+30 minutes'));

            $stmt = $pdo->prepare("INSERT INTO auth_tokens (auth_id, token, type, expires_at) VALUES (?, ?, 'reset_password', ?)");
            $stmt->execute([$auth_id, $reset_token, $expires_at]);

            echo json_encode([
                'success' => true,
                'message' => 'OTP verified successfully.',
                'reset_token' => $reset_token
            ]);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid or expired OTP.']);
    }
} catch (PDOException $e) {
    error_log("Verify OTP Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
} catch (Throwable $e) {
    error_log("Verify OTP Critical Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Internal server error.']);
}
