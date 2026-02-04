<?php
/**
 * Get All Users API for Admin
 * Retrieves all registered users with role 'user'
 */
header('Content-Type: application/json');
require_once '../../config/database.php';
require_once '../../includes/middleware/auth.php';

// Check if admin is logged in
checkAuth('admin');

try {
    $limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 10;
    $offset = isset($_GET['offset']) ? (int) $_GET['offset'] : 0;
    $search = $_GET['search'] ?? '';

    $params = [];
    $where_clause = "WHERE a.role = 'user'";

    if (!empty($search)) {
        $where_clause .= " AND (p.display_name LIKE ? OR a.email LIKE ? OR p.phone LIKE ?)";
        $search_param = "%$search%";
        $params = [$search_param, $search_param, $search_param];
    }

    // Get total count
    $count_sql = "SELECT COUNT(*) FROM auth_accounts a JOIN users p ON a.id = p.auth_id $where_clause";
    $count_stmt = $pdo->prepare($count_sql);
    $count_stmt->execute($params);
    $total_records = $count_stmt->fetchColumn();

    // Get users
    $sql = "SELECT a.id, p.display_name as name, a.email, p.profile_pic, p.phone, 
            IF(a.is_active = 1, 'active', 'inactive') as status, a.created_at,
            (SELECT business_name FROM clients WHERE id = (SELECT client_id FROM events WHERE id = (SELECT event_id FROM tickets WHERE user_id = p.id LIMIT 1))) as client_name
            FROM auth_accounts a
            JOIN users p ON a.id = p.auth_id
            $where_clause 
            ORDER BY a.created_at DESC 
            LIMIT ? OFFSET ?";

    $stmt = $pdo->prepare($sql);

    $param_idx = 1;
    foreach ($params as $p) {
        $stmt->bindValue($param_idx++, $p);
    }
    $stmt->bindValue($param_idx++, $limit, PDO::PARAM_INT);
    $stmt->bindValue($param_idx++, $offset, PDO::PARAM_INT);

    $stmt->execute();
    $users = $stmt->fetchAll();

    echo json_encode([
        'success' => true,
        'users' => $users,
        'total' => $total_records
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>