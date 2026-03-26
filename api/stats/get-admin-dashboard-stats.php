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
    // 1. Total Users
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE deleted_at IS NULL");
    $total_users = $stmt->fetch()['total'];

    // 2. Total Clients
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM clients WHERE deleted_at IS NULL");
    $total_clients = $stmt->fetch()['total'];

    // 3. Total Events (Published)
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM events WHERE status = 'published' AND deleted_at IS NULL");
    $total_events = $stmt->fetch()['total'];

    // 4. Total Online (Using auth_accounts since that's where status is stored)
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM auth_accounts WHERE is_online = 1 AND last_seen >= DATE_SUB(NOW(), INTERVAL 10 MINUTE) AND role = 'user' AND deleted_at IS NULL");
    $stmt->execute();
    $online_users = $stmt->fetch()['total'] ?? 0;

    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM auth_accounts WHERE is_online = 1 AND last_seen >= DATE_SUB(NOW(), INTERVAL 10 MINUTE) AND role = 'client' AND deleted_at IS NULL");
    $stmt->execute();
    $online_clients = $stmt->fetch()['total'] ?? 0;

    // 5. Total Revenue — correctly as SUM(e.price) for all paid tickets
    $stmt = $pdo->query("
        SELECT COALESCE(SUM(e.price), 0) AS total
        FROM tickets t
        JOIN events e   ON t.event_id   = e.id
        JOIN payments p ON t.payment_id = p.id
        WHERE p.status = 'paid'
    ");
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
        SELECT u.id, u.name, u.profile_pic, u.state, a.is_online,
               IF(a.is_online = 1, 'active', 'offline') as status,
               (SELECT COUNT(*) FROM tickets WHERE user_id = u.id) as ticket_count
        FROM users u
        JOIN auth_accounts a ON u.user_auth_id = a.id
        WHERE u.deleted_at IS NULL
        ORDER BY ticket_count DESC
        LIMIT 5
    ");
    $top_users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 9. Active Clients (by events)
    $stmt = $pdo->query("
        SELECT c.id, c.business_name as name, c.profile_pic, c.company, c.state, a.email, a.is_online,
               IF(a.is_online = 1, 'active', 'offline') as status,
               (SELECT COUNT(*) FROM events WHERE client_id = c.id) as event_count
        FROM clients c
        JOIN auth_accounts a ON c.client_auth_id = a.id
        WHERE c.deleted_at IS NULL
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

    // 13. User Checked-In (Tickets used today)
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM tickets WHERE used = 1 AND DATE(used_at) = CURDATE()");
    $user_checked_in = $stmt->fetch()['total'] ?? 0;

    // 14. Clients Verified/Unverified
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM clients WHERE verification_status = 'verified' AND deleted_at IS NULL");
    $clients_verified = $stmt->fetch()['total'] ?? 0;

    $stmt = $pdo->query("SELECT COUNT(*) as total FROM clients WHERE verification_status != 'verified' AND deleted_at IS NULL");
    $clients_unverified = $stmt->fetch()['total'] ?? 0;

    echo json_encode([
        'success' => true,
        'stats' => [
            'total_users' => (int) $total_users,
            'active_users' => (int) $online_users, // "Active" reflects online users
            'user_checked_in' => (int) $user_checked_in, // ADDED
            'online_clients' => (int) $online_clients,
            'total_clients' => (int) $total_clients,
            'clients_verified' => (int) $clients_verified, // ADDED
            'clients_unverified' => (int) $clients_unverified, // ADDED
            'total_events' => (int) $total_events,
            'published_events' => (int) $total_events,
            'total_revenue' => (float) $total_revenue,
            'platform_earnings' => (float) ($total_revenue * 0.30),
            'pending_payments' => (int) $pending_payments,
            'restored_events' => (int) $restored_events,
            'total_clients_events' => (int) $pdo->query("SELECT COUNT(*) FROM events WHERE deleted_at IS NULL")->fetchColumn()
        ],
        'recent_activities' => $recent_activities,
        'top_users' => $top_users,
        'active_clients' => $active_clients,
        'upcoming_events' => $upcoming_events,
        'past_events' => $past_events,
        'recent_logs' => $recent_activities
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to fetch admin stats.']);
}
