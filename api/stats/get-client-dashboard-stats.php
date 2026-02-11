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

$auth_id = $_SESSION['user_id'];

try {
    // Resolve real_client_id from auth_id
    $client_stmt = $pdo->prepare("SELECT id FROM clients WHERE auth_id = ?");
    $client_stmt->execute([$auth_id]);
    $client_row = $client_stmt->fetch();

    if (!$client_row) {
        echo json_encode(['success' => false, 'message' => 'Client profile not found.']);
        exit;
    }
    $real_client_id = $client_row['id'];

    // Get published events count (excluding soft-deleted)
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total 
        FROM events 
        WHERE client_id = ? AND status = 'published' AND deleted_at IS NULL
    ");
    $stmt->execute([$real_client_id]);
    $upcoming_events = $stmt->fetch()['total'];

    // Get total tickets sold for client's events
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(quantity), 0) as total 
        FROM tickets 
        WHERE client_id = ?
    ");
    $stmt->execute([$real_client_id]);
    $total_tickets = $stmt->fetch()['total'];

    // Get unique users who bought tickets for client's events
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT user_id) as total
        FROM tickets 
        WHERE client_id = ?
    ");
    $stmt->execute([$real_client_id]);
    $total_users = $stmt->fetch()['total'];

    // Get media uploads count for this client
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total 
        FROM media 
        WHERE client_id = ?
    ");
    $stmt->execute([$real_client_id]);
    $media_uploads = $stmt->fetch()['total'];

    // Get referral stats for this client
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as tickets,
            COUNT(DISTINCT user_id) as users
        FROM tickets 
        WHERE referred_by_id = ?
    ");
    $stmt->execute([$real_client_id]);
    $referral_data = $stmt->fetch();
    $referred_tickets = $referral_data['tickets'] ?? 0;
    $referred_users = $referral_data['users'] ?? 0;

    // Get total revenue for client's events
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(total_price), 0) as revenue
        FROM tickets 
        WHERE client_id = ?
    ");
    $stmt->execute([$real_client_id]);
    $total_revenue = $stmt->fetch()['revenue'];

    // Get upcoming published events with details
    $stmt = $pdo->prepare("
        SELECT 
            e.*,
            COUNT(t.id) as ticket_count,
            COALESCE(SUM(t.total_price), 0) as event_revenue
        FROM events e
        LEFT JOIN tickets t ON e.id = t.event_id
        WHERE e.client_id = ? AND e.status = 'published' AND e.event_date >= CURDATE() AND e.deleted_at IS NULL
        GROUP BY e.id
        ORDER BY e.event_date ASC
        LIMIT 5
    ");
    $stmt->execute([$real_client_id]);
    $upcoming_events_list = $stmt->fetchAll();

    // Get recent ticket sales
    $stmt = $pdo->prepare("
        SELECT 
            t.*,
            COALESCE(u.display_name, 'User') as user_name,
            a.email as user_email,
            u.profile_pic as user_profile_pic,
            e.event_name
        FROM tickets t
        INNER JOIN events e ON t.event_id = e.id
        LEFT JOIN users u ON t.user_id = u.auth_id
        LEFT JOIN auth_accounts a ON t.user_id = a.id
        WHERE t.client_id = ?
        ORDER BY t.purchase_date DESC
        LIMIT 10
    ");
    $stmt->execute([$real_client_id]);
    $recent_sales = $stmt->fetchAll();

    echo json_encode([
        'success' => true,
        'stats' => [
            'upcoming_events' => (int) $upcoming_events,
            'total_tickets' => (int) $total_tickets,
            'total_users' => (int) $total_users,
            'media_uploads' => (int) $media_uploads,
            'total_revenue' => (float) $total_revenue,
            'referred_tickets' => (int) $referred_tickets,
            'referred_users' => (int) $referred_users
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