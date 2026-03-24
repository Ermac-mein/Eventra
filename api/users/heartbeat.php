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
    $pdo->prepare("UPDATE users SET last_seen = NOW(), is_online = 1 WHERE id = ?")
        ->execute([$auth_id]);

    echo json_encode(['success' => true, 'ts' => time()]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false]);
}
