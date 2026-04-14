<?php

/**
 * Legacy Auth Middleware Wrapper
 * Refactored to use the new MVC-based authentication system.
 * Use App\Core\Router and middleware instead for new code.
 */

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../config/database.php';

/**
 * Polyfill for getallheaders() - required for InfinityFree and some shared hosting
 * Returns all HTTP headers as an associative array
 */
if (!function_exists('getallheaders')) {
    function getallheaders()
    {
        $headers = [];
        
        // Check for Apache's mod_php or CGI
        if (function_exists('apache_request_headers')) {
            return apache_request_headers();
        }
        
        // Manual header collection from $_SERVER (works for CGI, FastCGI, etc.)
        foreach ($_SERVER as $name => $value) {
            if (substr($name, 0, 5) === 'HTTP_') {
                // Convert HTTP_X_FORWARDED_FOR to X-Forwarded-For
                $header = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))));
                $headers[$header] = $value;
            } elseif (in_array($name, ['CONTENT_TYPE', 'CONTENT_LENGTH', 'CONTENT_MD5'])) {
                // These don't have HTTP_ prefix but are still headers
                $header = str_replace('_', '-', ucwords(strtolower($name)));
                $headers[$header] = $value;
            }
        }
        
        return $headers;
    }
}

/**
 * Get Bearer Token from Authorization header (Robust - works on shared hosting)
 */
function getBearerToken()
{
    // Method 1: Standard (often blocked by Apache on shared hosts)
    if (!empty($_SERVER['HTTP_AUTHORIZATION'])) {
        return str_replace('Bearer ', '', trim($_SERVER['HTTP_AUTHORIZATION']));
    }
    
    // Method 2: Alternate key some hosts use
    if (!empty($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
        return str_replace('Bearer ', '', trim($_SERVER['REDIRECT_HTTP_AUTHORIZATION']));
    }
    
    // Method 3: Apache fallback via mod_rewrite (requires .htaccess rule)
    if (!empty($_SERVER['HTTP_ACCESS_TOKEN'])) {
        return trim($_SERVER['HTTP_ACCESS_TOKEN']);
    }

    // Method 4: From session (if token was stored at login)
    if (!empty($_SESSION['api_token'])) {
        return $_SESSION['api_token'];
    }

    // Traditional getallheaders fallback
    if (function_exists('getallheaders')) {
        $headers = getallheaders();
        $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? null;
        if ($authHeader && preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            return $matches[1];
        }
    }

    return null;
}

/**
 * Validate Bearer token and return auth_id
 */
function validateBearerToken($requiredRole = null)
{
    global $pdo;
    
    $token = getBearerToken();
    if (!$token) {
        return null;
    }

    try {
        $stmt = $pdo->prepare("
            SELECT at.auth_id, aa.role, aa.is_active, aa.deleted_at
            FROM auth_tokens at
            JOIN auth_accounts aa ON at.auth_id = aa.id
            WHERE at.token = ? 
                AND at.type = 'access' 
                AND at.expires_at > NOW() 
                AND at.revoked = 0
            LIMIT 1
        ");
        $stmt->execute([$token]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$result) {
            return null;
        }

        if ($result['deleted_at'] || !$result['is_active']) {
            return null;
        }

        if ($requiredRole) {
            if (is_array($requiredRole)) {
                if (!in_array($result['role'], $requiredRole)) {
                    return null;
                }
            } elseif ($result['role'] !== $requiredRole) {
                return null;
            }
        }

        // Update last_seen timestamp
        $pdo->prepare("UPDATE auth_accounts SET last_seen = NOW() WHERE id = ?")
            ->execute([$result['auth_id']]);

        return $result['auth_id'];
    } catch (Exception $e) {
        return null;
    }
}

function checkAuth($requiredRole = null)
{
    global $pdo;

    // Ensure session is started
    if (session_status() === PHP_SESSION_NONE) {
        require_once __DIR__ . '/../../config.php';
    }

    // First, try Bearer token authentication (for API requests)
    $auth_id = validateBearerToken($requiredRole);
    if ($auth_id) {
        // Set session variables for Bearer token auth
        // This ensures getAuthId() and other functions work properly
        
        // Get complete role and profile info from auth_accounts
        $stmt = $pdo->prepare("SELECT id, role FROM auth_accounts WHERE id = ?");
        $stmt->execute([$auth_id]);
        $account = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($account) {
            $role = $account['role'];
            
            // Set auth_id and role in session
            $_SESSION['auth_id'] = $auth_id;
            $_SESSION['user_role'] = $role;
            $_SESSION['role'] = $role;
            
            // Get the profile-specific ID (admin_id, client_id, or user_id)
            $profileId = null;
            if ($role === 'admin') {
                $stmt = $pdo->prepare("SELECT id FROM admins WHERE admin_auth_id = ? LIMIT 1");
                $stmt->execute([$auth_id]);
                $profileId = $stmt->fetchColumn();
                if ($profileId) {
                    $_SESSION['admin_id'] = $profileId;
                }
            } elseif ($role === 'client') {
                $stmt = $pdo->prepare("SELECT id FROM clients WHERE client_auth_id = ? LIMIT 1");
                $stmt->execute([$auth_id]);
                $profileId = $stmt->fetchColumn();
                if ($profileId) {
                    $_SESSION['client_id'] = $profileId;
                }
            } else { // user
                $stmt = $pdo->prepare("SELECT id FROM users WHERE user_auth_id = ? LIMIT 1");
                $stmt->execute([$auth_id]);
                $profileId = $stmt->fetchColumn();
                if ($profileId) {
                    $_SESSION['user_id'] = $profileId;
                }
            }
            
            // Return the profile-specific ID (or auth_id if profile lookup failed)
            return $profileId ?? $auth_id;
        }
    }

    // Fall back to session-based authentication
    $role = $_SESSION['role'] ?? null;
    $userId = $_SESSION[$role . '_id'] ?? null;

    $hasAuthorizedRole = true;
    if ($requiredRole) {
        if (is_array($requiredRole)) {
            $hasAuthorizedRole = in_array($role, $requiredRole);
        } else {
            $hasAuthorizedRole = ($role === $requiredRole);
        }
    }

    if (!$userId || !$hasAuthorizedRole) {
        // Check if it's an API request first to avoid redirect loops on AJAX calls
        if (strpos($_SERVER['REQUEST_URI'], '/api/') !== false || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false)) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Unauthorized. Please log in.']);
            exit;
        }

        $loginPath = ($requiredRole === 'client') ? '/client/login' : (($requiredRole === 'admin') ? '/admin/login' : '/user/login');
        header("Location: " . SITE_URL . $loginPath);
        exit;
    }

    return $userId;
}

/**
 * Returns the global authentication account ID (auth_accounts.id)
 *
 * @return int|null
 */
function getAuthId()
{
    if (session_status() === PHP_SESSION_NONE) {
        require_once __DIR__ . '/../../config/session-config.php';
    }
    return $_SESSION['auth_id'] ?? null;
}

function adminMiddleware()
{
    return checkAuth('admin');
}
function clientMiddleware()
{
    return checkAuth('client');
}
function userMiddleware()
{
    return checkAuth('user');
}

/**
 * Optional Auth Check
 * Returns the user ID if a session exists, or null otherwise.
 * Does not redirect or terminate the script.
 */
function checkAuthOptional()
{
    global $pdo;
    
    // Ensure session is started
    if (session_status() === PHP_SESSION_NONE) {
        require_once __DIR__ . '/../../config.php';
    }

    // Try Bearer token first (same logic as checkAuth but non-terminating)
    $auth_id = validateBearerToken();
    if ($auth_id) {
        // Sync session if token is valid
        $stmt = $pdo->prepare("SELECT role FROM auth_accounts WHERE id = ?");
        $stmt->execute([$auth_id]);
        $role = $stmt->fetchColumn();
        
        if ($role) {
            $_SESSION['auth_id'] = $auth_id;
            $_SESSION['role'] = $role;
            $_SESSION['user_role'] = $role;
            
            // Get profile ID
            $profileTable = ($role === 'client') ? 'clients' : (($role === 'admin') ? 'admins' : 'users');
            $authCol = ($role === 'client') ? 'client_auth_id' : (($role === 'admin') ? 'admin_auth_id' : 'user_auth_id');
            
            $stmt = $pdo->prepare("SELECT id FROM $profileTable WHERE $authCol = ? LIMIT 1");
            $stmt->execute([$auth_id]);
            $profileId = $stmt->fetchColumn();
            
            if ($profileId) {
                $_SESSION[$role . '_id'] = $profileId;
                return $profileId;
            }
        }
        return $auth_id;
    }

    // Fall back to existing session
    $role = $_SESSION['role'] ?? null;
    $userId = $_SESSION[$role . '_id'] ?? null;

    return $userId;
}
