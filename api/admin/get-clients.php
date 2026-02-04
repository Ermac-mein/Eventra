<?php
/**
 * Get All Clients API for Admin
 * Retrieves all registered users with role 'client'
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
    $where_clause = "WHERE a.role = 'client'";

    if (!empty($search)) {
        $where_clause .= " AND (p.business_name LIKE ? OR a.email LIKE ? OR p.company LIKE ? OR p.state LIKE ?)";
        $search_param = "%$search%";
        $params = [$search_param, $search_param, $search_param, $search_param];
    }

    // Get total count
    $count_sql = "SELECT COUNT(*) FROM auth_accounts a JOIN clients p ON a.id = p.auth_id $where_clause";
    $count_stmt = $pdo->prepare($count_sql);
    $count_stmt->execute($params);
    $total_records = $count_stmt->fetchColumn();

    // Get clients with event count and referral stats
    $sql = "SELECT a.id, p.business_name as name, a.email, p.profile_pic, p.company, p.state, p.phone, 
            IF(a.is_active = 1, 'active', 'inactive') as status, a.created_at,
            (SELECT COUNT(*) FROM events WHERE client_id = p.id) as event_count,
            (SELECT COUNT(*) FROM tickets WHERE referred_by_id = p.id) as referred_tickets_count,
            (SELECT COUNT(DISTINCT user_id) FROM tickets WHERE referred_by_id = p.id) as referred_users_count
            FROM auth_accounts a
            JOIN clients p ON a.id = p.auth_id
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
    $clients = $stmt->fetchAll();

    echo json_encode([
        'success' => true,
        'clients' => $clients,
        'total' => $total_records
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>