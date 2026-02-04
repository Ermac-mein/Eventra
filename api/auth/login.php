<?php
header('Content-Type: application/json');
require_once '../../config/database.php';
require_once '../../includes/helpers/entity-resolver.php';

$data = json_decode(file_get_contents("php://input"), true);

if (!isset($data['email']) || !isset($data['password'])) {
    echo json_encode(['success' => false, 'message' => 'Email and password are required.']);
    exit;
}

$email = $data['email'];
$password = $data['password'];
$intent = $data['intent'] ?? 'client'; // Default to client if not specified
$remember_me = isset($data['remember_me']) && $data['remember_me'] === true;

if (!in_array($intent, ['admin', 'client'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid authentication path.']);
    exit;
}

try {
    // 1. Resolve Entity (Centralized Backend Decision)
    $user = resolveEntity($email);

    if (!$user) {
        logSecurityEvent(null, $email, 'login_failure', 'password', "Identity not found.");
        echo json_encode(['success' => false, 'message' => 'Invalid email or password.']);
        exit;
    }

    // 2. Validate Role Compatibility with Flow
    // If no intent or intent is 'user', but user has a higher role, block it.
    // The homepage login is for 'user' only.
    $effectiveIntent = $intent;
    if ($effectiveIntent === 'user' && in_array($userRole, ['admin', 'client'])) {
        logSecurityEvent($user['id'], $email, 'login_failure', 'password', "Role blocked: $userRole tried to login via user flow");
        echo json_encode(['success' => false, 'message' => "This account is a " . ucfirst($userRole) . " account. Please use the appropriate portal to login."]);
        exit;
    }

    if ($userRole !== $effectiveIntent && $effectiveIntent !== 'user') {
        logSecurityEvent($user['id'], $email, 'login_failure', 'password', "Role mismatch: User is $userRole but tried as $effectiveIntent");
        echo json_encode(['success' => false, 'message' => "Access denied. Use the " . ucfirst($effectiveIntent) . " portal."]);
        exit;
    }

    if (password_verify($password, $user['password_hash'])) {
        // 3. Enforce Auth Policy
        $policy = getAuthPolicy($user['role'], 'password', $user);
        if (!$policy['allowed']) {
            logSecurityEvent($user['id'], $email, 'login_failure', 'password', "Policy Violation: " . $policy['message']);
            echo json_encode(['success' => false, 'message' => $policy['message']]);
            exit;
        }

        // Generate alphanumeric access token
        $token = bin2hex(random_bytes(32));

        // Expiration logic
        $expires_in = $remember_me ? '+30 days' : '+2 hours';
        $expires_at = date('Y-m-d H:i:s', strtotime($expires_in));

        // Delete old tokens for this auth identity
        $stmt = $pdo->prepare("DELETE FROM auth_tokens WHERE auth_id = ?");
        $stmt->execute([$user['id']]);

        // Store new token in database
        $stmt = $pdo->prepare("INSERT INTO auth_tokens (auth_id, token, expires_at) VALUES (?, ?, ?)");
        $stmt->execute([$user['id'], $token, $expires_at]);

        // Update user status (if we had a status column in auth_accounts, but it's in specific tables or we use is_active)
        // For now, assume is_active in auth_accounts is the main one.

        // Log success
        logSecurityEvent($user['id'], $email, 'login_success', 'password', "Logged in as " . $user['role']);

        // 3. Set Entity-Scoped Session
        $userRole = strtolower($user['role']);
        $expectedSessionName = 'EVENTRA_USER_SESS';
        if ($userRole === 'admin') {
            $expectedSessionName = 'EVENTRA_ADMIN_SESS';
        } elseif ($userRole === 'client') {
            $expectedSessionName = 'EVENTRA_CLIENT_SESS';
        }

        if (session_name() !== $expectedSessionName) {
            session_write_close();
            session_name($expectedSessionName);
            session_start();
            session_regenerate_id(true);
            $_SESSION = [];
        }

        $_SESSION['user_id'] = $user['id']; // This is now auth_id
        $_SESSION['role'] = $userRole;
        $_SESSION['auth_token'] = $token;

        // Create login notification for Admin
        if ($userRole === 'admin') {
            require_once '../../api/utils/notification-helper.php';
            createAdminLoginNotification($user['id']);
        }

        $redirect = ($userRole === 'admin') ? 'admin/pages/adminDashboard.html' :
            (($userRole === 'client') ? 'client/pages/clientDashboard.html' : 'public/pages/index.html');

        echo json_encode([
            'success' => true,
            'message' => 'Login successful',
            'redirect' => $redirect,
            'user' => [
                'id' => $user['id'],
                'name' => $user['name'],
                'email' => $user['email'],
                'role' => $userRole,
                'profile_pic' => $user['profile_pic'] ?? null,
                'token' => $token
            ]
        ]);
    } else {
        logSecurityEvent($user['id'], $email, 'login_failure', 'password', "Invalid credentials.");
        echo json_encode(['success' => false, 'message' => 'Invalid email or password.']);
    }
} catch (PDOException $e) {
    error_log("[Auth Debug] Database error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>