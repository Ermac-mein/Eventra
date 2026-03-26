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

try {
    $auth_id = getAuthId(); // Get global auth account ID
    if ($auth_id) {
        // 1. Update this user's online status in the central auth table
        $pdo->prepare("UPDATE auth_accounts SET last_seen = NOW(), is_online = 1 WHERE id = ?")
            ->execute([$auth_id]);

        // 2. Role-specific status sync (for the dashboard active count)
        $role = $_SESSION['role'] ?? $_SESSION['user_role'] ?? 'user';
        if ($role === 'user') {
            $pdo->prepare("UPDATE users SET status = 'online' WHERE user_auth_id = ? AND status != 'online'")
                ->execute([$auth_id]);
        } elseif ($role === 'client') {
            $pdo->prepare("UPDATE clients SET status = 'online' WHERE client_auth_id = ? AND status != 'online'")
                ->execute([$auth_id]);
        }

        // 3. Clear stale online flags for all users in the central auth table
        // (Heartbeat is universal, so we clean up the source of truth)
        $pdo->exec(
            "UPDATE auth_accounts SET is_online = 0 
             WHERE is_online = 1 
               AND (last_seen IS NULL OR last_seen < DATE_SUB(NOW(), INTERVAL 6 MINUTE))"
        );
    }

    echo json_encode(['success' => true, 'ts' => time()]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false]);
}
