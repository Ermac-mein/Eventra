<?php
/**
 * Get Notifications API
 * Retrieves notifications for a user
 */
header('Content-Type: application/json');
require_once '../../config/database.php';

require_once '../../includes/middleware/auth.php';

// Check authentication
$user_id = checkAuth();
$limit = $_GET['limit'] ?? 20;
$offset = $_GET['offset'] ?? 0;
$is_read = $_GET['is_read'] ?? null;

try {
    // Build query
    $where_clauses = ["recipient_id = ?"];
    $params = [$user_id];

    if ($is_read !== null) {
        $where_clauses[] = "is_read = ?";
        $params[] = (int) $is_read;
    }

    $where_sql = implode(' AND ', $where_clauses);

    // Get notifications with sender information
    $sql = "
        SELECT 
            n.*,
            u.name as sender_name,
            u.profile_pic as sender_profile_pic
        FROM notifications n
        LEFT JOIN users u ON n.sender_id = u.id
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
    $count_stmt = $pdo->prepare("SELECT COUNT(*) as unread FROM notifications WHERE recipient_id = ? AND is_read = 0");
    $count_stmt->execute([$user_id]);
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
?>