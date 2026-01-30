<?php
/**
 * Get All Tickets API for Admin
 * Retrieves all ticket purchases with event and user information
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
    $where_clause = "";

    if (!empty($search)) {
        $where_clause = "WHERE e.event_name LIKE ? OR u.name LIKE ? OR t.ticket_code LIKE ?";
        $search_param = "%$search%";
        $params = [$search_param, $search_param, $search_param];
    }

    // Get total count
    $count_sql = "SELECT COUNT(*) FROM tickets t 
                  JOIN events e ON t.event_id = e.id 
                  JOIN users u ON t.user_id = u.id 
                  $where_clause";
    $count_stmt = $pdo->prepare($count_sql);
    $count_stmt->execute($params);
    $total_records = $count_stmt->fetchColumn();

    // Get tickets
    $sql = "SELECT t.*, e.event_name, e.image_path as event_image, u.name as user_name, e.tag as category 
            FROM tickets t 
            JOIN events e ON t.event_id = e.id 
            JOIN users u ON t.user_id = u.id 
            $where_clause 
            ORDER BY t.created_at DESC 
            LIMIT ? OFFSET ?";

    $stmt = $pdo->prepare($sql);

    $param_idx = 1;
    foreach ($params as $p) {
        $stmt->bindValue($param_idx++, $p);
    }
    $stmt->bindValue($param_idx++, $limit, PDO::PARAM_INT);
    $stmt->bindValue($param_idx++, $offset, PDO::PARAM_INT);

    $stmt->execute();
    $tickets = $stmt->fetchAll();

    echo json_encode([
        'success' => true,
        'tickets' => $tickets,
        'total' => $total_records
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>