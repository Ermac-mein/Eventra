<?php
/**
 * Get Admin Dashboard Stats API
 * Provides comprehensive statistics for admin dashboard
 */
header('Content-Type: application/json');
require_once '../../config/database.php';

// Check authentication
require_once '../../includes/middleware/auth.php';
$admin_id = checkAuth('admin');

try {
    // 1. Total Users (Regular users)
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM auth_accounts WHERE role = 'user' AND deleted_at IS NULL");
    $total_users = $stmt->fetch()['total'];

    // 2. Total Clients
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM auth_accounts WHERE role = 'client' AND deleted_at IS NULL");
    $total_clients = $stmt->fetch()['total'];

    // 3. Total Events (Published)
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM events WHERE status = 'published' AND deleted_at IS NULL");
    $total_events = $stmt->fetch()['total'];

    // 4. Total Online (Current Session)
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM auth_accounts WHERE role = 'user' AND is_online = 1 AND deleted_at IS NULL");
    $online_users = $stmt->fetch()['total'] ?? 0;

    $stmt = $pdo->query("SELECT COUNT(*) as total FROM auth_accounts WHERE role = 'client' AND is_online = 1 AND deleted_at IS NULL");
    $online_clients = $stmt->fetch()['total'] ?? 0;

    // 5. Total Revenue (Paid payments)
    $stmt = $pdo->query("SELECT COALESCE(SUM(amount), 0) as total FROM payments WHERE status = 'paid'");
    $total_revenue = $stmt->fetch()['total'];

    // 6. Pending Payments
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM payments WHERE status = 'pending'");
    $pending_payments = $stmt->fetch()['total'];

    // 7. Recent Activities based on auth_logs
    $stmt = $pdo->query("
        SELECT al.event_type as type, al.details as message, al.created_at 
        FROM auth_logs al 
        ORDER BY al.created_at DESC 
        LIMIT 10
    ");
    $recent_activities = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 8. Top Users (by tickets)
    $stmt = $pdo->query("
        SELECT p.id, p.name, p.profile_pic, p.state, a.is_online,
               IF(a.is_online = 1, 'active', 'offline') as status,
               (SELECT COUNT(*) FROM tickets WHERE user_id = p.id) as ticket_count
        FROM users p
        JOIN auth_accounts a ON p.user_auth_id = a.id
        ORDER BY ticket_count DESC
        LIMIT 5
    ");
    $top_users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 9. Active Clients (by events)
    $stmt = $pdo->query("
        SELECT p.id, p.business_name as name, p.profile_pic, p.company, p.state, p.email, a.is_online,
               IF(a.is_online = 1, 'active', 'offline') as status,
               (SELECT COUNT(*) FROM events WHERE client_id = p.id) as event_count
        FROM clients p
        JOIN auth_accounts a ON p.client_auth_id = a.id
        ORDER BY event_count DESC
        LIMIT 5
    ");
    $active_clients = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 10. Upcoming Events
    $stmt = $pdo->query("
        SELECT e.id, e.event_name, e.event_date, e.image_path, c.business_name as client_name
        FROM events e
        JOIN clients c ON e.client_id = c.id
        WHERE e.status = 'published' AND e.event_date >= CURDATE()
        ORDER BY e.event_date ASC
        LIMIT 10
    ");
    $upcoming_events = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 11. Past & Trending Events
    $stmt = $pdo->query("
        SELECT e.id, e.event_name, e.event_date, e.image_path, c.business_name as client_name
        FROM events e
        JOIN clients c ON e.client_id = c.id
        WHERE e.status = 'published' AND e.event_date < CURDATE()
        ORDER BY e.event_date DESC
        LIMIT 10
    ");
    $past_events = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 12. Restored Events Count (placeholder or specific logic)
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM events WHERE status = 'restored' AND deleted_at IS NULL");
    $restored_events = $stmt->fetch()['total'] ?? 0;

    echo json_encode([
        'success' => true,
        'stats' => [
            'total_users' => (int) $total_users,
            'active_users' => (int) $online_users, // Now reflects online users specifically for "Active"
            'online_clients' => (int) $online_clients,
            'total_clients' => (int) $total_clients,
            'total_events' => (int) $total_events,
            'published_events' => (int) $total_events, // Alias
            'total_revenue' => (float) $total_revenue,
            'pending_payments' => (int) $pending_payments,
            'restored_events' => (int) $restored_events
        ],
        'recent_activities' => $recent_activities,
        'top_users' => $top_users,
        'active_clients' => $active_clients,
        'upcoming_events' => $upcoming_events,
        'past_events' => $past_events,
        'recent_logs' => $recent_activities // Backwards compatibility for other potential users
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to fetch admin stats.']);
}
