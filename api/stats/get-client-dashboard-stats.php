<?php
/**
 * Get Client Dashboard Stats API
 * Provides statistics for client dashboard
 */
header('Content-Type: application/json');
require_once '../../config/database.php';

// Check authentication
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'client') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized - Client access required']);
    exit;
}

$client_id = $_SESSION['user_id'];

try {
    // Get upcoming events count (published only)
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total 
        FROM events 
        WHERE client_id = ? AND status = 'published' AND event_date >= CURDATE()
    ");
    $stmt->execute([$client_id]);
    $upcoming_events = $stmt->fetch()['total'];

    // Get total tickets sold for client's events
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(t.quantity), 0) as total 
        FROM tickets t
        INNER JOIN events e ON t.event_id = e.id
        WHERE e.client_id = ?
    ");
    $stmt->execute([$client_id]);
    $total_tickets = $stmt->fetch()['total'];

    // Get unique users who bought tickets for client's events
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT t.user_id) as total
        FROM tickets t
        INNER JOIN events e ON t.event_id = e.id
        WHERE e.client_id = ?
    ");
    $stmt->execute([$client_id]);
    $total_users = $stmt->fetch()['total'];

    // Get media uploads count for this client
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total 
        FROM media 
        WHERE client_id = ?
    ");
    $stmt->execute([$client_id]);
    $media_uploads = $stmt->fetch()['total'];

    // Get total revenue for client's events
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(t.total_price), 0) as revenue
        FROM tickets t
        INNER JOIN events e ON t.event_id = e.id
        WHERE e.client_id = ?
    ");
    $stmt->execute([$client_id]);
    $total_revenue = $stmt->fetch()['revenue'];

    // Get upcoming published events with details
    $stmt = $pdo->prepare("
        SELECT 
            e.*,
            COUNT(t.id) as ticket_count,
            COALESCE(SUM(t.total_price), 0) as event_revenue
        FROM events e
        LEFT JOIN tickets t ON e.id = t.event_id
        WHERE e.client_id = ? AND e.status = 'published' AND e.event_date >= CURDATE()
        GROUP BY e.id
        ORDER BY e.event_date ASC
        LIMIT 5
    ");
    $stmt->execute([$client_id]);
    $upcoming_events_list = $stmt->fetchAll();

    // Get recent ticket sales
    $stmt = $pdo->prepare("
        SELECT 
            t.*,
            u.name as user_name,
            u.email as user_email,
            u.profile_pic as user_profile_pic,
            e.event_name
        FROM tickets t
        INNER JOIN events e ON t.event_id = e.id
        INNER JOIN users u ON t.user_id = u.id
        WHERE e.client_id = ?
        ORDER BY t.purchase_date DESC
        LIMIT 10
    ");
    $stmt->execute([$client_id]);
    $recent_sales = $stmt->fetchAll();

    echo json_encode([
        'success' => true,
        'stats' => [
            'upcoming_events' => (int) $upcoming_events,
            'total_tickets' => (int) $total_tickets,
            'total_users' => (int) $total_users,
            'media_uploads' => (int) $media_uploads,
            'total_revenue' => (float) $total_revenue
        ],
        'upcoming_events_list' => $upcoming_events_list,
        'recent_sales' => $recent_sales
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    error_log("Client dashboard stats error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Failed to fetch dashboard stats']);
}
?>