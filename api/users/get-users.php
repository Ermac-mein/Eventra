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
        $where_clauses[] = "role = ?";
        $params[] = $role;
    }

    if ($status) {
        $where_clauses[] = "status = ?";
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
    $count_stmt = $pdo->prepare("SELECT COUNT(*) as total FROM users $where_sql");
    $count_stmt->execute($params);
    $total = $count_stmt->fetch()['total'];

    // Get users
    $sql = "
        SELECT 
            u.id, u.name, u.email, u.role, u.google_id, u.profile_pic, u.phone, u.job_title,
            u.company, u.address, u.city, u.state, u.dob, u.gender, u.status, u.created_at,
            (SELECT name FROM users WHERE id = (SELECT client_id FROM events WHERE id = (SELECT event_id FROM tickets WHERE user_id = u.id LIMIT 1))) as client_name
        FROM users u
        $where_sql
        ORDER BY u.created_at DESC
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
            SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_users
        FROM users
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