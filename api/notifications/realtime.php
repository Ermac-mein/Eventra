<?php
header('Content-Type: application/json');
require_once '../../includes/middleware/auth.php';
require_once '../../config/database.php';

$user_id = checkAuth(); // Ensure user is logged in

try {
    // Fetch unread notifications
    $stmt = $pdo->prepare("SELECT * FROM notifications WHERE recipient_id = ? AND is_read = FALSE ORDER BY created_at DESC");
    $stmt->execute([$user_id]);
    $notifications = $stmt->fetchAll();

    // Also fetch active users/clients status if user is admin/client
    $usersStatus = [];
    if ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'client') {
        $stmt = $pdo->prepare("SELECT id, name, role, status FROM users WHERE id != ?");
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