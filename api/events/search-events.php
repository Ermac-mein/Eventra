<?php
/**
 * Search Events API
 * Robust search functionality for events
 */
header('Content-Type: application/json');
require_once '../../config/database.php';

try {
    $query = $_GET['query'] ?? '';
    $state = $_GET['state'] ?? '';
    $category = $_GET['category'] ?? '';
    $date_from = $_GET['date_from'] ?? '';
    $date_to = $_GET['date_to'] ?? '';
    $priority = $_GET['priority'] ?? '';
    $limit = $_GET['limit'] ?? 20;

    // Build search query
    $where_clauses = ["e.status = 'published'", "e.deleted_at IS NULL"];
    $params = [];

    error_log("[Search Debug] Query: $query | Limit: $limit");

    // Search by event name or description
    if (!empty($query)) {
        $where_clauses[] = "(e.event_name LIKE ? OR e.description LIKE ?)";
        $search_term = "%$query%";
        $params[] = $search_term;
        $params[] = $search_term;
    }

    // Filter by state
    if (!empty($state)) {
        $where_clauses[] = "e.state = ?";
        $params[] = $state;
    }

    // Filter by category/event_type
    if (!empty($category)) {
        $where_clauses[] = "e.event_type = ?";
        $params[] = $category;
    }

    // Filter by date range
    if (!empty($date_from)) {
        $where_clauses[] = "e.event_date >= ?";
        $params[] = $date_from;
    }
    if (!empty($date_to)) {
        $where_clauses[] = "e.event_date <= ?";
        $params[] = $date_to;
    }

    // Filter by priority
    if (!empty($priority)) {
        $where_clauses[] = "e.priority = ?";
        $params[] = $priority;
    }

    $where_sql = implode(' AND ', $where_clauses);
    error_log("[Search Debug] SQL Where: $where_sql");

    // Execute search
    $sql = "
        SELECT e.*, u.name as client_name, u.profile_pic as client_profile_pic
        FROM events e
        LEFT JOIN clients u ON e.client_id = u.id
        WHERE $where_sql
        ORDER BY 
            CASE e.priority
                WHEN 'featured' THEN 1
                WHEN 'hot' THEN 2
                WHEN 'trending' THEN 3
                ELSE 4
            END,
            e.event_date ASC
        LIMIT ?
    ";

    $params[] = (int) $limit;

    $stmt = $pdo->prepare($sql);

    // Bind parameters
    foreach ($params as $key => $value) {
        $stmt->bindValue($key + 1, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }

    $stmt->execute();
    $events = $stmt->fetchAll();

    echo json_encode([
        'success' => true,
        'events' => $events,
        'count' => count($events)
    ]);

} catch (Throwable $e) {
    error_log("[Search Global Error] " . $e->getMessage() . "\n" . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Internal server error: ' . $e->getMessage()]);
}
?>