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

    // ALWAYS filter for regular users only (exclude clients and admins)
    $where_clauses[] = "a.role = 'user'";
    $where_clauses[] = "a.deleted_at IS NULL";
    if ($client_id) {
        // Resolve real_client_id (PK of clients table) from auth_id
        $client_res_stmt = $pdo->prepare("SELECT id FROM clients WHERE client_auth_id = ?");
        $client_res_stmt->execute([$client_id]);
        $real_client_id = $client_res_stmt->fetchColumn();

        if ($real_client_id) {
            // If client_id is provided, only get users who bought tickets for this client's events
            $where_clauses[] = "a.id IN (SELECT DISTINCT p.user_id FROM tickets t JOIN payments p ON t.payment_id = p.id JOIN events e ON p.event_id = e.id WHERE e.client_id = ?)";
            $params[] = $real_client_id;
        }
    }

    $where_sql = 'WHERE ' . implode(' AND ', $where_clauses);

    // Get total count
    $count_stmt = $pdo->prepare("SELECT COUNT(*) as total FROM auth_accounts a LEFT JOIN users u ON a.id = u.user_auth_id $where_sql");
    $count_stmt->execute($params);
    $total = $count_stmt->fetch()['total'];

    // Get users
    $limit = (int) $limit;
    $offset = (int) $offset;
    $sql = "
        SELECT 
            a.id, 
            COALESCE(u.display_name, c.business_name, a.email) as name, 
            a.email, 
            a.role, 
            COALESCE(u.profile_pic, c.profile_pic) as profile_pic, 
            COALESCE(u.phone, c.phone) as phone,
            COALESCE(u.address, c.address) as address, 
            COALESCE(u.city, c.city) as city, 
            COALESCE(u.state, c.state) as state, 
            COALESCE(u.country, c.country) as country,
            COALESCE(u.dob, c.dob) as dob, 
            COALESCE(u.gender, c.gender) as gender, 
            a.is_active as status, 
            a.created_at
        FROM auth_accounts a
        LEFT JOIN users u ON a.id = u.user_auth_id
        LEFT JOIN clients c ON a.id = c.client_auth_id
        $where_sql
        ORDER BY a.created_at DESC
        LIMIT $limit OFFSET $offset
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $users = $stmt->fetchAll();

    // Get statistics
    $stats = [];
    if (isset($real_client_id) && $real_client_id) {
        // Stats specific to the client's subset of users
        $stats_stmt = $pdo->prepare("
            SELECT 
                SUM(CASE WHEN a.is_active = 1 THEN 1 ELSE 0 END) as active_users,
                COUNT(DISTINCT p.user_id) as engaged_users,
                COUNT(DISTINCT a.id) as registered_users
            FROM auth_accounts a
            JOIN payments p ON a.id = p.user_id
            JOIN events e ON p.event_id = e.id
            WHERE a.role = 'user' AND e.client_id = ?
        ");
        $stats_stmt->execute([$real_client_id]);
        $stats = $stats_stmt->fetch();

        // If query returns nulls when there are no users at all, set to 0.
        $stats['active_users'] = $stats['active_users'] ?? 0;
        $stats['engaged_users'] = $stats['engaged_users'] ?? 0;
        $stats['registered_users'] = $stats['registered_users'] ?? 0;

    } else {
        // Global stats for admin
        $stats_stmt = $pdo->prepare("
            SELECT 
                SUM(CASE WHEN role = 'user' AND is_active = 1 THEN 1 ELSE 0 END) as active_users,
                (SELECT COUNT(DISTINCT user_id) FROM payments) as engaged_users,
                SUM(CASE WHEN role = 'user' THEN 1 ELSE 0 END) as registered_users
            FROM auth_accounts
        ");
        $stats_stmt->execute();
        $stats = $stats_stmt->fetch();
    }

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
