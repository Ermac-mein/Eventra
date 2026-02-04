<?php
/**
 * Get Users API
 * Retrieves users with filtering and pagination
 */
header('Content-Type: application/json');
require_once '../../config/database.php';

require_once '../../includes/middleware/auth.php';

// Check authentication
$user_id = checkAuth();

try {
    $role = $_GET['role'] ?? null;
    $status = $_GET['status'] ?? null;
    $limit = $_GET['limit'] ?? 10;
    $offset = $_GET['offset'] ?? 0;

    // Build query
    $where_clauses = [];
    $params = [];

    if ($role) {
        $where_clauses[] = "a.role = ?";
        $params[] = $role;
    }

    if ($status) {
        $where_clauses[] = "a.status = ?";
        $params[] = $status;
    }

    $client_id = $_GET['client_id'] ?? null;
    $where_sql = !empty($where_clauses) ? 'WHERE ' . implode(' AND ', $where_clauses) : '';

    if ($client_id) {
        // If client_id is provided, only get users who bought tickets for this client's events
        $where_sql = ($where_sql ? $where_sql . ' AND ' : 'WHERE ') . "u.id IN (SELECT DISTINCT user_id FROM tickets t INNER JOIN events e ON t.event_id = e.id WHERE e.client_id = ?)";
        $params[] = $client_id;
    }

    // Get total count
    $count_stmt = $pdo->prepare("SELECT COUNT(*) as total FROM auth_accounts a LEFT JOIN users u ON a.id = u.auth_id $where_sql");
    $count_stmt->execute($params);
    $total = $count_stmt->fetch()['total'];

    // Get users
    $sql = "
        SELECT 
            a.id, 
            COALESCE(u.display_name, c.business_name, a.email) as name, 
            a.email, 
            a.role, 
            COALESCE(u.profile_pic, c.profile_pic) as profile_pic, 
            COALESCE(u.phone, c.phone) as phone,
            c.address, c.city, c.state, u.dob, u.gender, 
            a.is_active as status, 
            a.created_at
        FROM auth_accounts a
        LEFT JOIN users u ON a.id = u.auth_id
        LEFT JOIN clients c ON a.id = c.auth_id
        $where_sql
        ORDER BY a.created_at DESC
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
    $users = $stmt->fetchAll();

    // Get statistics
    $stats_stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_users,
            SUM(CASE WHEN role = 'user' THEN 1 ELSE 0 END) as total_regular_users,
            SUM(CASE WHEN role = 'client' THEN 1 ELSE 0 END) as total_clients,
            SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active_users
        FROM auth_accounts
    ");
    $stats_stmt->execute();
    $stats = $stats_stmt->fetch();

    echo json_encode([
        'success' => true,
        'users' => $users,
        'total' => $total,
        'stats' => $stats
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>