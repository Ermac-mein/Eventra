<?php
header('Content-Type: application/json');
require_once '../../config/database.php';
require_once '../../includes/helpers/entity-resolver.php';

$data = json_decode(file_get_contents("php://input"), true);

if (!isset($data['google_id']) || !isset($data['email'])) {
    echo json_encode(['success' => false, 'message' => 'Google information is missing.']);
    exit;
}

$google_id = $data['google_id'];
$email = $data['email'];
$name = $data['name'] ?? 'Google User';
$profile_pic = $data['profile_pic'] ?? null;

// Implicit Intent Resolution (from dedicated login pages)
$intent = $data['intent'] ?? 'user';
if (!in_array($intent, ['client', 'user', 'admin'])) {
    $intent = 'user';
}

try {
    // 1. Resolve Entity by email (since schema doesn't have google_id anymore)
    $user = resolveEntity($email);

    if ($user) {
        // 2. Validate Role Compatibility with Intent
        if (strtolower($user['role']) !== $intent) {
            logSecurityEvent($user['id'], $email, 'login_failure', 'google', "Role mismatch: Found as " . $user['role'] . " but intent was $intent");
            echo json_encode(['success' => false, 'message' => "This identity is already bound to a " . ucfirst($user['role']) . " account and cannot be used as $intent."]);
            exit;
        }

        // 3. Enforce Auth Policy
        $policy = getAuthPolicy($user['role'], 'google', $user);
        if (!$policy['allowed']) {
            logSecurityEvent($user['id'], $email, 'login_failure', 'google', "Policy Violation: " . $policy['message']);
            echo json_encode(['success' => false, 'message' => $policy['message']]);
            exit;
        }
    } else {
        // 4. Registration Flow
        if ($intent === 'admin') {
            logSecurityEvent(null, $email, 'login_failure', 'google', "Attempted admin registration via Google.");
            echo json_encode(['success' => false, 'message' => 'Admin accounts cannot be created via Google Sign-In.']);
            exit;
        }

        $registrability = canRegisterAs($email, $intent);
        if (!$registrability['success']) {
            logSecurityEvent(null, $email, 'login_failure', 'google', "Registration blocked: " . $registrability['message']);
            echo json_encode(['success' => false, 'message' => $registrability['message']]);
            exit;
        }

        $pdo->beginTransaction();

        // Create new auth_account
        $stmt = $pdo->prepare("INSERT INTO auth_accounts (email, role, auth_provider) VALUES (?, ?, 'google')");
        $stmt->execute([$email, $intent]);
        $auth_id = $pdo->lastInsertId();

        if ($intent === 'client') {
            $stmt = $pdo->prepare("INSERT INTO clients (auth_id, business_name, profile_pic) VALUES (?, ?, ?)");
            $stmt->execute([$auth_id, $name, $profile_pic]);
        } else {
            // Default to 'user' role
            $stmt = $pdo->prepare("INSERT INTO users (auth_id, display_name, profile_pic) VALUES (?, ?, ?)");
            $stmt->execute([$auth_id, $name, $profile_pic]);
        }

        $pdo->commit();
        $user = resolveEntity($email);
    }

    // Set Token
    $token = bin2hex(random_bytes(32));
    $expires_at = date('Y-m-d H:i:s', strtotime('+2 hours'));

    // Delete old tokens
    $stmt = $pdo->prepare("DELETE FROM auth_tokens WHERE auth_id = ?");
    $stmt->execute([$user['id']]);

    $stmt = $pdo->prepare("INSERT INTO auth_tokens (auth_id, token, expires_at) VALUES (?, ?, ?)");
    $stmt->execute([$user['id'], $token, $expires_at]);

    // Log success
    logSecurityEvent($user['id'], $email, 'login_success', 'google', "Logged in as " . $user['role']);

    // 4. Set Entity-Scoped Session
    $expectedSessionName = 'EVENTRA_USER_SESS';
    if ($user['role'] === 'admin') {
        $expectedSessionName = 'EVENTRA_ADMIN_SESS';
    } elseif ($user['role'] === 'client') {
        $expectedSessionName = 'EVENTRA_CLIENT_SESS';
    }

    if (session_name() !== $expectedSessionName) {
        session_write_close();
        session_name($expectedSessionName);
        session_start();
        $_SESSION = [];
    }

    $_SESSION['user_id'] = $user['id'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['auth_token'] = $token;

    // Redirection logic
    if ($user['role'] === 'client') {
        $redirect = 'client/pages/clientDashboard.html';
    } elseif ($user['role'] === 'admin') {
        $redirect = 'admin/pages/adminDashboard.html';
    } else {
        $redirect = 'public/pages/index.html';
    }

    echo json_encode([
        'success' => true,
        'message' => 'Signed in with Google',
        'redirect' => $redirect,
        'user' => [
            'id' => $user['id'],
            'name' => $user['name'],
            'email' => $user['email'],
            'role' => $user['role'],
            'profile_pic' => $user['profile_pic'] ?? null,
            'token' => $token
        ]
    ]);
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>