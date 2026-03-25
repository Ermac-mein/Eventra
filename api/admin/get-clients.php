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
    $where_clause = "WHERE 1=1";

    if (!empty($search)) {
        $where_clause .= " AND (p.business_name LIKE ? OR a.email LIKE ? OR p.company LIKE ? OR p.state LIKE ?)";
        $search_param = "%$search%";
        $params = [$search_param, $search_param, $search_param, $search_param];
    }

    // Get total count
    $count_sql = "SELECT COUNT(*) FROM clients p JOIN auth_accounts a ON p.client_auth_id = a.id $where_clause";
    $count_stmt = $pdo->prepare($count_sql);
    $count_stmt->execute($params);
    $total_records = $count_stmt->fetchColumn();

    // Get clients with event count
    $sql = "SELECT p.id, p.custom_id, p.business_name as name, a.email, p.profile_pic, p.company, p.state, p.phone,
            p.nin, p.bvn, p.nin_verified, p.bvn_verified,
            p.account_name, p.account_number, p.bank_name, p.bank_code, p.subaccount_code, p.verification_status,
            p.admin_notes, p.dob, p.gender, p.address, p.city, p.country, p.job_title,
            a.is_active, a.is_online, a.last_seen,
            IF(a.is_online = 1 AND a.last_seen >= DATE_SUB(NOW(), INTERVAL 6 MINUTE), 'active', 'inactive') as status,
            p.created_at,
            (SELECT COUNT(*) FROM events WHERE client_id = p.id AND deleted_at IS NULL) as event_count
            FROM clients p
            JOIN auth_accounts a ON p.client_auth_id = a.id
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
