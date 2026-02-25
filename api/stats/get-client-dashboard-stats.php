<?php
/**
 * Get Client Dashboard Stats API
 * Provides statistics for client dashboard
 */
header('Content-Type: application/json');
require_once '../../config/database.php';

// Check authentication
require_once '../../includes/middleware/auth.php';
$auth_id = checkAuth('client');

try {
    // 1. Resolve real_client_id from auth_id
    $client_stmt = $pdo->prepare("SELECT id FROM clients WHERE client_auth_id = ?");
    $client_stmt->execute([$auth_id]);
    $client_row = $client_stmt->fetch();

    if (!$client_row) {
        echo json_encode(['success' => false, 'message' => 'Client profile not found.']);
        exit;
    }
    $real_client_id = $client_row['id'];

    // 2. Client Revenue (Paid)
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(p.amount), 0) as total 
        FROM payments p
        JOIN events e ON p.event_id = e.id
        WHERE e.client_id = ? AND p.status = 'paid'
    ");
    $stmt->execute([$real_client_id]);
    $client_revenue = $stmt->fetch()['total'];

    // 3. Total Tickets Sold
    $stmt = $pdo->prepare("
        SELECT COUNT(t.id) as total 
        FROM tickets t
        JOIN payments p ON t.payment_id = p.id
        JOIN events e ON p.event_id = e.id
        WHERE e.client_id = ? AND p.status = 'paid'
    ");
    $stmt->execute([$real_client_id]);
    $total_tickets = $stmt->fetch()['total'];

    // 3.5 Total Unique Users (Attendees)
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT p.user_id) as total
        FROM payments p
        JOIN events e ON p.event_id = e.id
        WHERE e.client_id = ? AND p.status = 'paid'
    ");
    $stmt->execute([$real_client_id]);
    $total_users = $stmt->fetch()['total'];

    // 4. Total Events
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM events WHERE client_id = ? AND deleted_at IS NULL AND status = 'published'");
    $stmt->execute([$real_client_id]);
    $total_events = $stmt->fetch()['total'];

    // 5. Upcoming Events
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM events WHERE client_id = ? AND event_date >= CURDATE() AND status = 'published' AND deleted_at IS NULL");
    $stmt->execute([$real_client_id]);
    $upcoming_events_count = $stmt->fetch()['total'];

    // 6. Detailed Attendee List (With profile pics)
    $stmt = $pdo->prepare("
        SELECT u.name, a.email, u.profile_pic, e.event_name, p.paid_at, t.barcode, t.used, p.amount, t.created_at, p.paystack_response
        FROM tickets t
        JOIN payments p ON t.payment_id = p.id
        JOIN users u ON p.user_id = u.id
        JOIN auth_accounts a ON u.user_auth_id = a.id
        JOIN events e ON p.event_id = e.id
        WHERE e.client_id = ? AND p.status = 'paid'
        ORDER BY t.created_at DESC
        LIMIT 10
    ");
    $stmt->execute([$real_client_id]);
    $attendees = $stmt->fetchAll();

    // 7. Event Performance Breakdown
    $stmt = $pdo->prepare("
        SELECT e.id, e.event_name, e.event_date, e.status, e.image_path,
               COUNT(t.id) as tickets_sold, 
               COALESCE(SUM(p.amount), 0) as revenue
        FROM events e
        LEFT JOIN payments p ON e.id = p.event_id AND p.status = 'paid'
        LEFT JOIN tickets t ON p.id = t.payment_id
        WHERE e.client_id = ? AND e.deleted_at IS NULL AND e.status = 'published'
        GROUP BY e.id
        ORDER BY e.event_date ASC
    ");
    $stmt->execute([$real_client_id]);
    $event_breakdown = $stmt->fetchAll();

    // 8. Total Media Items
    $stmt = $pdo->prepare("
        SELECT 
            (SELECT COUNT(*) FROM media WHERE client_id = ? AND is_deleted = 0) +
            (SELECT COUNT(*) FROM media_folders WHERE client_id = ? AND is_deleted = 0) as total
    ");
    $stmt->execute([$real_client_id, $real_client_id]);
    $total_media = $stmt->fetch()['total'];

    echo json_encode([
        'success' => true,
        'stats' => [
            'total_revenue' => (float) $client_revenue,
            'total_tickets' => (int) $total_tickets,
            'total_events' => (int) $total_events,
            'upcoming_events' => (int) $upcoming_events_count,
            'total_users' => (int) $total_users,
            'total_media' => (int) $total_media
        ],
        'attendees' => $attendees,
        'events' => $event_breakdown
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to fetch client stats: ' . $e->getMessage()]);
}
