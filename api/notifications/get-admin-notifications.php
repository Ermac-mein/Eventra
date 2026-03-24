<?php
header('Content-Type: application/json');
require_once '../../includes/middleware/auth.php';
require_once '../../config/database.php';

checkAuth('admin');
$admin_auth_id = getAuthId();
$role = $_SESSION['role'] ?? 'admin';

// Check if user is admin
if ($role !== 'admin') {
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
    // Get notifications
    $stmt = $pdo->prepare("
        SELECT n.*, c.business_name as client_name, c.profile_pic as client_profile_pic
        FROM notifications n
        LEFT JOIN clients c ON n.sender_auth_id = c.client_auth_id AND n.sender_role = 'client'
        WHERE n.recipient_auth_id = ?
        ORDER BY n.created_at DESC
        LIMIT 10
    ");
    $stmt->execute([$admin_auth_id]); 
    $notifications = $stmt->fetchAll();

    // Get unread count
    $stmt = $pdo->prepare("SELECT COUNT(*) as unread_count FROM notifications WHERE recipient_auth_id = ? AND is_read = 0");
    $stmt->execute([$admin_auth_id]);
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
