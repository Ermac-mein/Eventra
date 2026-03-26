<?php

/**
 * Heartbeat API
 * Called every 60 seconds from authenticated pages to maintain online status.
 */

header('Content-Type: application/json');
require_once '../../config/database.php';
require_once '../../includes/middleware/auth.php';

$auth_id = checkAuth(); // any role

try {
    // Update auth_accounts for real-time status tracking
    $pdo->prepare("UPDATE auth_accounts SET last_seen = NOW(), is_online = 1 WHERE id = ?")->execute([$auth_id]);

    // Role-specific status sync - maintain online status
    $role = $_SESSION['role'] ?? $_SESSION['user_role'] ?? 'user';
    if ($role === 'admin') {
        $pdo->prepare("UPDATE admins SET status = 'online' WHERE admin_auth_id = ?")->execute([$auth_id]);
    } elseif ($role === 'client') {
        $pdo->prepare("UPDATE clients SET status = 'online' WHERE client_auth_id = ?")->execute([$auth_id]);
    } elseif ($role === 'user') {
        $pdo->prepare("UPDATE users SET status = 'online' WHERE user_auth_id = ?")->execute([$auth_id]);
    }

    echo json_encode(['success' => true, 'ts' => time()]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false]);
}
