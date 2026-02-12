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
    // Get ALL events count (Published, Draft, Deleted, and Restored)
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM events");
    $total_events = $stmt->fetch()['total'];

    // Get strictly PUBLISHED and NOT DELETED events count
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM events WHERE status = 'published' AND deleted_at IS NULL");
    $published_events_count = $stmt->fetch()['total'];

    // Get active users count (regular users with an auth account)
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM auth_accounts WHERE role = 'user' AND is_active = 1");
    $active_users = $stmt->fetch()['total'];

    // Get total clients count
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM auth_accounts WHERE role = 'client' AND is_active = 1");
    $total_clients = $stmt->fetch()['total'];

    // Get total revenue from tickets
    $stmt = $pdo->query("SELECT COALESCE(SUM(total_price), 0) as revenue FROM tickets");
    $total_revenue = $stmt->fetch()['revenue'];

    $stmt = $pdo->prepare("
        SELECT 
            n.id,
            n.message,
            n.type,
            n.is_read,
            n.created_at,
            a.email as sender_name,
            COALESCE(u.profile_pic, c.profile_pic, adm.profile_pic) as sender_profile_pic
        FROM notifications n
        LEFT JOIN auth_accounts a ON n.sender_auth_id = a.id
        LEFT JOIN users u ON a.id = u.auth_id
        LEFT JOIN clients c ON a.id = c.auth_id
        LEFT JOIN admins adm ON a.id = adm.auth_id
        WHERE n.recipient_auth_id = ?
        ORDER BY n.created_at DESC
        LIMIT 10
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $recent_activities = $stmt->fetchAll();

    // Get top users (users with most ticket purchases)
    $stmt = $pdo->query("
        SELECT 
            a.id,
            p.display_name as name,
            a.email,
            p.profile_pic,
            IF(a.is_active = 1, 'active', 'inactive') as status,
            COUNT(t.id) as ticket_count
        FROM auth_accounts a
        JOIN users p ON a.id = p.auth_id
        LEFT JOIN tickets t ON a.id = t.user_id
        WHERE a.role = 'user'
        GROUP BY a.id
        ORDER BY ticket_count DESC
        LIMIT 5
    ");
    $top_users = $stmt->fetchAll();

    // Get active clients (clients with most events)
    $stmt = $pdo->query("
        SELECT 
            a.id,
            p.business_name as name,
            a.email,
            p.profile_pic,
            p.company,
            IF(a.is_active = 1, 'active', 'inactive') as status,
            COUNT(e.id) as event_count
        FROM auth_accounts a
        JOIN clients p ON a.id = p.auth_id
        LEFT JOIN events e ON a.id = e.client_id
        WHERE a.role = 'client'
        GROUP BY a.id
        ORDER BY event_count DESC
        LIMIT 5
    ");
    $active_clients = $stmt->fetchAll();

    // Get upcoming published events
    $stmt = $pdo->query("
        SELECT 
            e.*,
            p.business_name as client_name,
            COUNT(t.id) as ticket_count
        FROM events e
        LEFT JOIN clients p ON e.client_id = p.id
        LEFT JOIN tickets t ON e.id = t.event_id
        WHERE e.status = 'published' AND e.deleted_at IS NULL
        GROUP BY e.id
        ORDER BY e.created_at DESC
        LIMIT 10
    ");
    $upcoming_events = $stmt->fetchAll();

    echo json_encode([
        'success' => true,
        'stats' => [
            'total_events' => (int) $total_events,
            'published_events' => (int) $published_events_count,
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