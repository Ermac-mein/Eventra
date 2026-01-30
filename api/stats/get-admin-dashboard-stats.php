<?php
/**
 * Get Admin Dashboard Stats API
 * Provides comprehensive statistics for admin dashboard
 */
header('Content-Type: application/json');
require_once '../../config/database.php';

// Check authentication
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized - Admin access required']);
    exit;
}

try {
    // Get total events count
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM events");
    $total_events = $stmt->fetch()['total'];

    // Get published events count
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM events WHERE status = 'published'");
    $published_events = $stmt->fetch()['total'];

    // Get active users count (regular users)
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE role = 'user'");
    $active_users = $stmt->fetch()['total'];

    // Get total clients count
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE role = 'client'");
    $total_clients = $stmt->fetch()['total'];

    // Get total revenue from tickets
    $stmt = $pdo->query("SELECT COALESCE(SUM(total_price), 0) as revenue FROM tickets");
    $total_revenue = $stmt->fetch()['revenue'];

    $stmt = $pdo->prepare("
        SELECT 
            n.id,
            n.message,
            n.type,
            n.created_at,
            u.name as sender_name,
            u.profile_pic as sender_profile_pic
        FROM notifications n
        LEFT JOIN users u ON n.sender_id = u.id
        WHERE n.recipient_id = ?
        ORDER BY n.created_at DESC
        LIMIT 10
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $recent_activities = $stmt->fetchAll();

    // Get top users (users with most ticket purchases)
    $stmt = $pdo->query("
        SELECT 
            u.id,
            u.name,
            u.email,
            u.profile_pic,
            u.state,
            u.status,
            COUNT(t.id) as ticket_count
        FROM users u
        LEFT JOIN tickets t ON u.id = t.user_id
        WHERE u.role = 'user'
        GROUP BY u.id
        ORDER BY ticket_count DESC
        LIMIT 5
    ");
    $top_users = $stmt->fetchAll();

    // Get active clients (clients with most events)
    $stmt = $pdo->query("
        SELECT 
            u.id,
            u.name,
            u.email,
            u.profile_pic,
            u.company,
            u.status,
            COUNT(e.id) as event_count
        FROM users u
        LEFT JOIN events e ON u.id = e.client_id
        WHERE u.role = 'client'
        GROUP BY u.id
        ORDER BY event_count DESC
        LIMIT 5
    ");
    $active_clients = $stmt->fetchAll();

    // Get upcoming published events
    $stmt = $pdo->query("
        SELECT 
            e.*,
            u.name as client_name,
            COUNT(t.id) as ticket_count
        FROM events e
        LEFT JOIN users u ON e.client_id = u.id
        LEFT JOIN tickets t ON e.id = t.event_id
        WHERE e.status = 'published' AND e.event_date >= CURDATE()
        GROUP BY e.id
        ORDER BY e.event_date ASC
        LIMIT 10
    ");
    $upcoming_events = $stmt->fetchAll();

    echo json_encode([
        'success' => true,
        'stats' => [
            'total_events' => (int) $total_events,
            'published_events' => (int) $published_events,
            'active_users' => (int) $active_users,
            'total_clients' => (int) $total_clients,
            'total_revenue' => (float) $total_revenue
        ],
        'recent_activities' => $recent_activities,
        'top_users' => $top_users,
        'active_clients' => $active_clients,
        'upcoming_events' => $upcoming_events
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    error_log("Admin dashboard stats error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Failed to fetch dashboard stats']);
}
?>