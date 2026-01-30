<?php
/**
 * Get Tickets API
 * Retrieves tickets with user and event information
 */
header('Content-Type: application/json');
require_once '../../config/database.php';

require_once '../../includes/middleware/auth.php';

// Check authentication
$user_id = checkAuth();

try {
    $client_id = $_GET['client_id'] ?? null;
    $user_id = $_GET['user_id'] ?? null;
    $event_id = $_GET['event_id'] ?? null;
    $limit = $_GET['limit'] ?? 10;
    $offset = $_GET['offset'] ?? 0;

    // Build query
    $where_clauses = [];
    $params = [];

    // Filter by client's events
    if ($client_id) {
        $where_clauses[] = "e.client_id = ?";
        $params[] = $client_id;
    }

    // Filter by user
    if ($user_id) {
        $where_clauses[] = "t.user_id = ?";
        $params[] = $user_id;
    }

    // Filter by event
    if ($event_id) {
        $where_clauses[] = "t.event_id = ?";
        $params[] = $event_id;
    }

    $where_sql = !empty($where_clauses) ? 'WHERE ' . implode(' AND ', $where_clauses) : '';

    // Get total count
    $count_sql = "
        SELECT COUNT(*) as total
        FROM tickets t
        JOIN events e ON t.event_id = e.id
        $where_sql
    ";
    $count_stmt = $pdo->prepare($count_sql);
    $count_stmt->execute($params);
    $total = $count_stmt->fetch()['total'];

    // Get tickets with user and event information
    $sql = "
        SELECT 
            t.*,
            u.name as user_name,
            u.email as user_email,
            u.profile_pic as user_profile_pic,
            e.event_name,
            e.event_date,
            e.event_time,
            e.state as event_state,
            e.image_path as event_image
        FROM tickets t
        JOIN users u ON t.user_id = u.id
        JOIN events e ON t.event_id = e.id
        $where_sql
        ORDER BY t.purchase_date DESC
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
    $tickets = $stmt->fetchAll();

    // Get statistics if client_id is provided
    $stats = null;
    if ($client_id) {
        $stats_stmt = $pdo->prepare("
            SELECT 
                COUNT(*) as total_tickets,
                SUM(quantity) as total_quantity,
                SUM(total_price) as total_revenue
            FROM tickets t
            JOIN events e ON t.event_id = e.id
            WHERE e.client_id = ?
        ");
        $stats_stmt->execute([$client_id]);
        $stats = $stats_stmt->fetch();
    }

    echo json_encode([
        'success' => true,
        'tickets' => $tickets,
        'total' => $total,
        'stats' => $stats
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>