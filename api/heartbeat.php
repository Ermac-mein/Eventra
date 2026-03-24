<?php
/**
 * Universal Heartbeat API
 * Called every 60 seconds from authenticated pages to:
 *  1. Keep the session alive and update last_seen
 *  2. Clean up stale is_online flags for inactive users
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/middleware/auth.php';

$auth_id = checkAuth(); // Accepts any role

try {
    $role = $_SESSION['role'] ?? $_SESSION['user_role'] ?? 'user';
    $table = '';
    if ($role === 'admin') $table = 'admins';
    elseif ($role === 'client') $table = 'clients';
    elseif ($role === 'user') $table = 'users';

    if ($table && $role !== 'admin') {
        $id_col = ($role === 'admin') ? 'admin_auth_id' : (($role === 'client') ? 'client_auth_id' : 'user_auth_id');
        // 1. Update this user's online status
        $pdo->prepare("UPDATE $table SET last_seen = NOW(), is_online = 1 WHERE $id_col = ?")
            ->execute([$auth_id]);

        // 2. Clear stale online flags for this table
        $pdo->exec(
            "UPDATE $table SET is_online = 0 
             WHERE is_online = 1 
               AND (last_seen IS NULL OR last_seen < DATE_SUB(NOW(), INTERVAL 6 MINUTE))"
        );
    }

    echo json_encode(['success' => true, 'ts' => time()]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false]);
}
