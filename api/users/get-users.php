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

    $where_sql = !empty($where_clauses) ? 'WHERE ' . implode(' AND ', $where_clauses) : '';

    // Get total count
    $count_stmt = $pdo->prepare("SELECT COUNT(*) as total FROM users $where_sql");
    $count_stmt->execute($params);
    $total = $count_stmt->fetch()['total'];

    // Get users
    $sql = "
        SELECT 
            id, name, email, role, google_id, profile_pic, phone, job_title,
            company, address, city, state, dob, gender, status, created_at
        FROM users
        $where_sql
        ORDER BY created_at DESC
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