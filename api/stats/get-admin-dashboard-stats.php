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

    // 4. Total Revenue (Paid payments)
    $stmt = $pdo->query("SELECT COALESCE(SUM(amount), 0) as total FROM payments WHERE status = 'paid'");
    $total_revenue = $stmt->fetch()['total'];

    // 5. Pending Payments
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM payments WHERE status = 'pending'");
    $pending_payments = $stmt->fetch()['total'];

    // Recent Security Logs
    $stmt = $pdo->query("
        SELECT al.*, aa.email 
        FROM auth_logs al 
        LEFT JOIN auth_accounts aa ON al.auth_id = aa.id 
        ORDER BY al.created_at DESC 
        LIMIT 10
    ");
    $recent_logs = $stmt->fetchAll();

    // Top Revenue Events
    $stmt = $pdo->query("
        SELECT e.event_name, c.business_name as client_name, SUM(p.amount) as revenue
        FROM events e
        JOIN clients c ON e.client_id = c.id
        JOIN payments p ON e.id = p.event_id
        WHERE p.status = 'paid'
        GROUP BY e.id
        ORDER BY revenue DESC
        LIMIT 5
    ");
    $top_events = $stmt->fetchAll();

    echo json_encode([
        'success' => true,
        'stats' => [
            'total_users' => (int) $total_users,
            'total_clients' => (int) $total_clients,
            'total_events' => (int) $total_events,
            'total_revenue' => (float) $total_revenue,
            'pending_payments' => (int) $pending_payments
        ],
        'recent_logs' => $recent_logs,
        'top_events' => $top_events
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to fetch admin stats.']);
}
