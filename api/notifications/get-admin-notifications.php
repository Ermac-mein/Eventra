<?php
header('Content-Type: application/json');
require_once '../../includes/middleware/auth.php';
require_once '../../config/database.php';

$user_id = checkAuth(); // Ensure user is logged in

// Check if user is admin
if ($_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied. Admin only.']);
    exit;
}

// Auto-delete notifications older than 2 days
try {
    $pdo->prepare("DELETE FROM notifications WHERE created_at < DATE_SUB(NOW(), INTERVAL 2 DAY)")->execute();
} catch (Exception $e) {
    // Non-critical, continue
}

try {
    // Fetch all notifications for admin, ordered by newest first
    $stmt = $pdo->prepare("
        SELECT 
            n.id,
            n.sender_auth_id as sender_id,
            n.message,
            n.type,
            n.is_read,
            n.created_at,
            u.email as sender_name,
            u.role as sender_role
        FROM notifications n
        LEFT JOIN auth_accounts u ON n.sender_auth_id = u.id
        WHERE n.recipient_auth_id = ?
        ORDER BY n.created_at DESC
        LIMIT 50
    ");
    $stmt->execute([$user_id]);
    $notifications = $stmt->fetchAll();

    // Get unread count
    $stmt = $pdo->prepare("SELECT COUNT(*) as unread_count FROM notifications WHERE recipient_auth_id = ? AND is_read = 0");
    $stmt->execute([$user_id]);
    $unread_result = $stmt->fetch();
    $unread_count = $unread_result['unread_count'];

    echo json_encode([
        'success' => true,
        'notifications' => $notifications,
        'unread_count' => (int) $unread_count
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>