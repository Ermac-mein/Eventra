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
    $where_clause = "WHERE 1=1";

    if (!empty($search)) {
        $where_clause .= " AND (p.name LIKE ? OR a.email LIKE ? OR p.phone LIKE ?)";
        $search_param = "%$search%";
        $params = [$search_param, $search_param, $search_param];
    }

    // Get total count
    $count_sql = "SELECT COUNT(*) FROM users p JOIN auth_accounts a ON p.user_auth_id = a.id $where_clause";
    $count_stmt = $pdo->prepare($count_sql);
    $count_stmt->execute($params);
    $total_records = $count_stmt->fetchColumn();

    // Get users
    $sql = "SELECT p.id, p.custom_id, p.name, a.email, p.profile_pic, p.phone, 
            p.gender, p.dob, p.address, p.city, p.state, p.country,
            a.is_active, a.is_online,
            IF(a.is_online = 1, 'active', 'inactive') as status, p.created_at, a.last_login_at, a.email_verified_at,
            (SELECT COUNT(*) FROM tickets t JOIN payments py ON t.payment_id = py.id WHERE py.user_id = p.id AND t.used = 1) as checked_in_count,
            (SELECT business_name FROM clients WHERE id = (SELECT client_id FROM events WHERE id = (SELECT event_id FROM tickets WHERE user_id = p.id LIMIT 1))) as client_name
            FROM users p
            JOIN auth_accounts a ON p.user_auth_id = a.id
            $where_clause 
            ORDER BY p.created_at DESC 
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

    // Get Global Summary Stats (for cards, ignore search/limit)
    $registered_sql = "SELECT COUNT(*) FROM users WHERE deleted_at IS NULL";
    $registered_count = $pdo->query($registered_sql)->fetchColumn();

    $active_sql = "SELECT COUNT(*) FROM users u JOIN auth_accounts a ON u.user_auth_id = a.id WHERE a.is_active = 1 AND u.deleted_at IS NULL";
    $active_count = $pdo->query($active_sql)->fetchColumn();

    $online_sql = "SELECT COUNT(*) FROM users u JOIN auth_accounts a ON u.user_auth_id = a.id WHERE a.is_online = 1 AND u.deleted_at IS NULL";
    $online_count = $pdo->query($online_sql)->fetchColumn();

    echo json_encode([
        'success' => true,
        'users' => $users,
        'total' => $total_records,
        'summary' => [
            'total_registered' => (int) $registered_count,
            'total_active' => (int) $active_count,
            'total_checked_in' => (int) $online_count // Mapping online users to "Checked-In" card as per user request
        ]
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
