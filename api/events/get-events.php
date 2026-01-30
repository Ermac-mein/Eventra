<?php
/**
 * Get Events API
 * Retrieves events with filtering and pagination
 */
header('Content-Type: application/json');
require_once '../../config/database.php';

require_once '../../includes/middleware/auth.php';

try {
    // Optional check: if token exists, validate it. Otherwise, treat as guest.
    error_log("[Debug] get-events.php entry. Session Name: " . session_name() . " | Session ID: " . session_id());
    if (isset($_SESSION['auth_token'])) {
        error_log("[Debug] get-events.php calling checkAuth()");
        checkAuth();
    }

    $client_id = $_GET['client_id'] ?? null;
    $status = $_GET['status'] ?? null;
    $limit = $_GET['limit'] ?? 10;
    $offset = $_GET['offset'] ?? 0;
    $user_role = $_SESSION['role'] ?? 'guest';

    // Build query
    $where_clauses = [];
    $params = [];

    // Filter by client_id if provided
    if ($client_id) {
        $where_clauses[] = "client_id = ?";
        $params[] = $client_id;
    }

    // Filter by status
    if ($status) {
        $where_clauses[] = "status = ?";
        $params[] = $status;
    } else {
        // For public/users, only show published events
        if ($user_role !== 'admin' && $user_role !== 'client') {
            $where_clauses[] = "status = 'published'";
        }
    }

    $where_sql = !empty($where_clauses) ? 'WHERE ' . implode(' AND ', $where_clauses) : '';

    // Get total count
    $count_stmt = $pdo->prepare("SELECT COUNT(*) as total FROM events $where_sql");
    $count_stmt->execute($params);
    $total = $count_stmt->fetch()['total'];

    // Get events with client information
    $sql = "
        SELECT e.*, u.name as client_name, u.profile_pic as client_profile_pic
        FROM events e
        LEFT JOIN users u ON e.client_id = u.id
        $where_sql
        ORDER BY e.created_at DESC
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
    $events = $stmt->fetchAll();

    // Get statistics if client_id is provided
    $stats = null;
    if ($client_id) {
        $stats_stmt = $pdo->prepare("
            SELECT 
                COUNT(*) as total_events,
                SUM(CASE WHEN status = 'published' THEN 1 ELSE 0 END) as published_events,
                SUM(CASE WHEN status = 'scheduled' THEN 1 ELSE 0 END) as scheduled_events,
                SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END) as draft_events,
                SUM(attendee_count) as total_attendees
            FROM events
            WHERE client_id = ?
        ");
        $stats_stmt->execute([$client_id]);
        $stats = $stats_stmt->fetch();
    }

    echo json_encode([
        'success' => true,
        'events' => $events,
        'total' => $total,
        'stats' => $stats
    ]);

} catch (PDOException $e) {
    error_log("[Debug] get-events.php PDO Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    error_log("[Debug] get-events.php General Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'General error: ' . $e->getMessage()]);
}
?>