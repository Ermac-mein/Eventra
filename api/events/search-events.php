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
    $where_clauses = ["status = 'published'"];
    $params = [];

    // Search by event name or description
    if (!empty($query)) {
        $where_clauses[] = "(event_name LIKE ? OR description LIKE ?)";
        $search_term = "%$query%";
        $params[] = $search_term;
        $params[] = $search_term;
    }

    // Filter by state
    if (!empty($state)) {
        $where_clauses[] = "state = ?";
        $params[] = $state;
    }

    // Filter by category/event_type
    if (!empty($category)) {
        $where_clauses[] = "event_type = ?";
        $params[] = $category;
    }

    // Filter by date range
    if (!empty($date_from)) {
        $where_clauses[] = "event_date >= ?";
        $params[] = $date_from;
    }
    if (!empty($date_to)) {
        $where_clauses[] = "event_date <= ?";
        $params[] = $date_to;
    }

    // Filter by priority
    if (!empty($priority)) {
        $where_clauses[] = "priority = ?";
        $params[] = $priority;
    }

    $where_sql = implode(' AND ', $where_clauses);

    // Execute search
    $sql = "
        SELECT e.*, u.name as client_name, u.profile_pic as client_profile_pic
        FROM events e
        LEFT JOIN users u ON e.client_id = u.id
        WHERE $where_sql
        ORDER BY 
            CASE priority
                WHEN 'featured' THEN 1
                WHEN 'hot' THEN 2
                WHEN 'trending' THEN 3
                ELSE 4
            END,
            event_date ASC
        LIMIT ?
    ";

    $params[] = (int) $limit;

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $events = $stmt->fetchAll();

    echo json_encode([
        'success' => true,
        'events' => $events,
        'count' => count($events)
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>