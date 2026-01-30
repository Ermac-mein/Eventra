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
    $where_clause = "WHERE u.role = 'client'";

    if (!empty($search)) {
        $where_clause .= " AND (u.name LIKE ? OR u.email LIKE ? OR u.company LIKE ? OR u.state LIKE ?)";
        $search_param = "%$search%";
        $params = [$search_param, $search_param, $search_param, $search_param];
    }

    // Get total count
    $count_sql = "SELECT COUNT(*) FROM users u $where_clause";
    $count_stmt = $pdo->prepare($count_sql);
    $count_stmt->execute($params);
    $total_records = $count_stmt->fetchColumn();

    // Get clients with event count
    $sql = "SELECT u.id, u.name, u.email, u.profile_pic, u.company, u.job_title, u.state, u.phone, u.status, u.created_at,
            (SELECT COUNT(*) FROM events WHERE client_id = u.id) as event_count
            FROM users u
            $where_clause 
            ORDER BY u.created_at DESC 
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