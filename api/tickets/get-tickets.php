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
        // Resolve real_client_id from auth_id
        $client_res_stmt = $pdo->prepare("SELECT id FROM clients WHERE client_auth_id = ?");
        $client_res_stmt->execute([$client_id]);
        $real_client_id = $client_res_stmt->fetchColumn();

        if ($real_client_id) {
            $where_clauses[] = "e.client_id = ?";
            $params[] = $real_client_id;
        }
    }

    if ($user_id) {
        $where_clauses[] = "p.user_id = ?";
        $params[] = $user_id;
    }

    // Filter by event
    if ($event_id) {
        $where_clauses[] = "p.event_id = ?";
        $params[] = $event_id;
    }

    $where_sql = !empty($where_clauses) ? 'WHERE ' . implode(' AND ', $where_clauses) : '';

    // Get total count
    $count_sql = "
        SELECT COUNT(*) as total
        FROM tickets t
        JOIN payments p ON t.payment_id = p.id
        JOIN events e ON p.event_id = e.id
        $where_sql
    ";
    $count_stmt = $pdo->prepare($count_sql);
    $count_stmt->execute(array_slice($params, 0, count($where_clauses)));
    $total = $count_stmt->fetch()['total'] ?? 0;

    // Get tickets with user and event information
    $sql = "
        SELECT 
            t.*,
            COALESCE(u.display_name, 'User') as user_name,
            a.email as user_email,
            u.profile_pic as user_profile_pic,
            e.event_name,
            e.event_date,
            e.event_time,
            e.state as event_state,
            e.image_path as event_image,
            c.business_name as organiser_name
        FROM tickets t
        JOIN payments p ON t.payment_id = p.id
        LEFT JOIN auth_accounts a ON p.user_id = a.id
        LEFT JOIN users u ON a.id = u.user_auth_id
        JOIN events e ON p.event_id = e.id
        LEFT JOIN clients c ON e.client_id = c.id
        $where_sql
        ORDER BY p.paid_at DESC
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
                COUNT(t.id) as total_tickets,
                SUM(CASE WHEN t.status = 'paid' THEN p.amount ELSE 0 END) as total_revenue,
                SUM(CASE WHEN t.status = 'pending' THEN 1 ELSE 0 END) as pending_tickets,
                SUM(CASE WHEN t.status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_tickets
            FROM tickets t
            JOIN payments p ON t.payment_id = p.id
            JOIN events e ON p.event_id = e.id
            WHERE e.client_id = (SELECT id FROM clients WHERE client_auth_id = ?)
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
