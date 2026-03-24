<?php
/**
 * Get Notifications API
 * Retrieves notifications for a user
 */
header('Content-Type: application/json');
require_once '../../config/database.php';

require_once '../../includes/middleware/auth.php';

    // Check authentication
    checkAuth();
    $auth_id = getAuthId();
    $role = $_SESSION['role'] ?? $_SESSION['user_role'] ?? 'user';

    $limit = $_GET['limit'] ?? 20;
    $offset = $_GET['offset'] ?? 0;
    $is_read = $_GET['is_read'] ?? null;
    
try {
    // Auto-delete notifications older than 30 days
    $cleanup_stmt = $pdo->prepare("DELETE FROM notifications WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)");
    $cleanup_stmt->execute();
    
    // Build query
    $where_clauses = ["recipient_auth_id = ?", "recipient_role = ?"];
    $params = [$auth_id, $role];

    if ($is_read !== null) {
        $where_clauses[] = "is_read = ?";
        $params[] = (int) $is_read;
    }

    $where_sql = implode(' AND ', $where_clauses);

    // Get notifications with sender information based on sender_role
    $sql = "
        SELECT 
            n.*,
            CASE 
                WHEN n.sender_role = 'admin' THEN ad.name
                WHEN n.sender_role = 'client' THEN c.business_name
                ELSE u.name
            END as sender_name,
            CASE 
                WHEN n.sender_role = 'admin' THEN ad.profile_pic
                WHEN n.sender_role = 'client' THEN c.profile_pic
                ELSE u.profile_pic
            END as sender_profile_pic
        FROM notifications n
        LEFT JOIN admins ad ON n.sender_auth_id = ad.admin_auth_id AND n.sender_role = 'admin'
        LEFT JOIN clients c ON n.sender_auth_id = c.client_auth_id AND n.sender_role = 'client'
        LEFT JOIN users u ON n.sender_auth_id = u.user_auth_id AND (n.sender_role = 'user' OR n.sender_role IS NULL)
        WHERE $where_sql
        ORDER BY n.created_at DESC
        LIMIT ? OFFSET ?
    ";

    $params[] = (int) $limit;
    $params[] = (int) $offset;

    $stmt = $pdo->prepare($sql);

    // Bind positionally but ensure integers for LIMIT/OFFSET
    foreach ($params as $key => $value) {
        $stmt->bindValue($key + 1, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }

    $stmt->execute();
    $notifications = $stmt->fetchAll();

    // Get unread count
    $count_stmt = $pdo->prepare("SELECT COUNT(*) as unread FROM notifications WHERE recipient_auth_id = ? AND recipient_role = ? AND is_read = 0");
    $count_stmt->execute([$auth_id, $role]);
    $unread_count = $count_stmt->fetch()['unread'];

    echo json_encode([
        'success' => true,
        'notifications' => $notifications,
        'unread_count' => $unread_count
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
