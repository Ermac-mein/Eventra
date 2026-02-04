<?php
/**
 * Entity Resolver Helper
 * Handles unified identity resolution and security policies for different entity types.
 */

/**
 * Entity Resolver Helper
 * Handles unified identity resolution and security policies for different entity types.
 */

/**
 * Entity Resolver Helper
 * Handles unified identity resolution and security policies for different entity types.
 */

function resolveEntity($email)
{
    global $pdo;

    // First check the auth_accounts table
    $stmt = $pdo->prepare("SELECT * FROM auth_accounts WHERE email = ? AND is_active = 1");
    $stmt->execute([$email]);
    $auth = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$auth) {
        // Fallback: Check if the email exists in the clients table (users might log in with their profile email)
        $stmt = $pdo->prepare("SELECT auth_id FROM clients WHERE email = ?");
        $stmt->execute([$email]);
        $client_auth_id = $stmt->fetchColumn();

        if ($client_auth_id) {
            // Found in clients table, now fetch the auth account using the retrieved auth_id
            $stmt = $pdo->prepare("SELECT * FROM auth_accounts WHERE id = ? AND is_active = 1");
            $stmt->execute([$client_auth_id]);
            $auth = $stmt->fetch(PDO::FETCH_ASSOC);
        }

        if (!$auth) {
            return false;
        }
    }

    // Role-based retrieval for full profile
    $role = $auth['role'];
    $table = '';
    if ($role === 'admin')
        $table = 'admins';
    elseif ($role === 'client')
        $table = 'clients';
    elseif ($role === 'user')
        $table = 'users';

    if ($table) {
        // Correct name column mapping
        $name_col = ($role === 'client') ? 'business_name' : (($role === 'user') ? 'display_name' : 'name');

        $stmt = $pdo->prepare("SELECT a.*, p.$name_col as display_name, p.profile_pic FROM auth_accounts a LEFT JOIN $table p ON a.id = p.auth_id WHERE a.id = ?");
        $stmt->execute([$auth['id']]);
        $fullUser = $stmt->fetch(PDO::FETCH_ASSOC);

        // For convenience in registration/login logic that expects certain keys
        if ($fullUser) {
            $fullUser['password'] = $fullUser['password_hash']; // Alias for compatibility
            // Map table-specific name fields to a generic 'name'
            $fullUser['name'] = $fullUser['display_name'] ?? ucfirst($role);
            return $fullUser;
        }
    }

    return $auth;
}

/**
 * Resolves an entity by their Google ID.
 * Since google_id is not in auth_accounts anymore (it's auth_provider = 'google'),
 * we might need to rely on email or add google_id to auth_accounts.
 * Looking at the provided schema, auth_accounts has auth_provider but no provider_id.
 * If the user intended to use email as the link:
 */
function resolveEntityByGoogleId($googleId, $email = null)
{
    if (!$email)
        return false;
    return resolveEntity($email);
}

/**
 * Generates a custom ID for Clients and Users.
 * NOT NEEDED anymore as we use BIGINT UNSIGNED AUTO_INCREMENT in the new schema.
 */

/**
 * Checks if an email can be registered for a specific role.
 */
function canRegisterAs($email, $targetRole)
{
    global $pdo;
    $stmt = $pdo->prepare("SELECT role FROM auth_accounts WHERE email = ?");
    $stmt->execute([$email]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$existing) {
        return ['success' => true];
    }

    return [
        'success' => false,
        'message' => "This identity is already bound to the " . ucfirst($existing['role']) . " role and cannot be reused for other roles."
    ];
}

/**
 * Returns authentication policy for a given role and method.
 */
function getAuthPolicy($role, $method, $user = null)
{
    if ($role === 'admin') {
        if ($method === 'google') {
            return [
                'allowed' => false,
                'message' => 'Admin accounts are restricted to secure password-based authentication.'
            ];
        }
        return ['allowed' => true];
    }

    // New schema logic
    if ($role === 'user' || $role === 'client') {
        return ['allowed' => true];
    }

    return ['allowed' => false, 'message' => 'Invalid role or authentication method.'];
}

/**
 * Logs security events for auditing and abuse detection.
 */
function logSecurityEvent($authId, $email, $eventType, $authMethod, $details = null)
{
    global $pdo;
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';

    try {
        $stmt = $pdo->prepare("INSERT INTO auth_logs (auth_id, email, event_type, auth_method, ip_address, user_agent, details) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$authId, $email, $eventType, $authMethod, $ip, $ua, $details]);
    } catch (PDOException $e) {
        error_log("Failed to log security event: " . $e->getMessage());
    }
}
