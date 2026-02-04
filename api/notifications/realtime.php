<?php
header('Content-Type: application/json');
require_once '../../includes/middleware/auth.php';
require_once '../../config/database.php';

$user_id = checkAuth(); // Ensure user is logged in

try {
    // Fetch unread notifications
    $stmt = $pdo->prepare("SELECT * FROM notifications WHERE recipient_auth_id = ? AND is_read = FALSE ORDER BY created_at DESC");
    $stmt->execute([$user_id]);
    $notifications = $stmt->fetchAll();

    // Also fetch active users/clients status if user is admin/client
    $usersStatus = [];
    if ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'client') {
        // Query auth_accounts joined with profile info
        $sql = "
            SELECT a.id, COALESCE(u.display_name, c.business_name, adm.name) as name, a.role, 
                   COALESCE(u.status, c.status, 'active') as status 
            FROM auth_accounts a
            LEFT JOIN users u ON a.id = u.auth_id
            LEFT JOIN clients c ON a.id = c.auth_id
            LEFT JOIN admins adm ON a.id = adm.auth_id
            WHERE a.id != ? AND a.is_active = 1
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$user_id]);
        $usersStatus = $stmt->fetchAll();
    }

    echo json_encode([
        'success' => true,
        'notifications' => $notifications,
        'users_status' => $usersStatus
    ]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>