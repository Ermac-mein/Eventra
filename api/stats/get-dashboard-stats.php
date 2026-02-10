<?php
/**
 * Dashboard Statistics API
 * Returns statistics for the client dashboard
 */
header('Content-Type: application/json');
require_once '../../config/database.php';

// Check authentication
if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized'
    ]);
    exit;
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'] ?? 'user';

try {
    $stats = [];

    if ($user_role === 'client') {
        // Resolve real_client_id from auth_id
        $client_stmt = $pdo->prepare("SELECT id FROM clients WHERE auth_id = ?");
        $client_stmt->execute([$user_id]);
        $client_row = $client_stmt->fetch();

        if (!$client_row) {
            echo json_encode(['success' => false, 'message' => 'Client profile not found.']);
            exit;
        }
        $real_client_id = $client_row['id'];

        // Client-specific stats

        // Upcoming Events (published or scheduled events in the future)
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count 
            FROM events 
            WHERE client_id = ? 
            AND status IN ('published', 'scheduled')
            AND event_date >= CURDATE()
        ");
        $stmt->execute([$real_client_id]);
        $stats['upcoming_events'] = $stmt->fetch()['count'] ?? 0;

        // Total Tickets Sold for client's events
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count 
            FROM tickets t
            INNER JOIN events e ON t.event_id = e.id
            WHERE e.client_id = ?
            AND t.status = 'active'
        ");
        $stmt->execute([$real_client_id]);
        $stats['tickets_sold'] = $stmt->fetch()['count'] ?? 0;

        // Total Users who bought tickets to client's events
        $stmt = $pdo->prepare("
            SELECT COUNT(DISTINCT t.user_id) as count 
            FROM tickets t
            INNER JOIN events e ON t.event_id = e.id
            WHERE e.client_id = ?
        ");
        $stmt->execute([$real_client_id]);
        $stats['total_users'] = $stmt->fetch()['count'] ?? 0;

        // Media Uploads
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count 
            FROM media 
            WHERE client_id = ?
        ");
        $stmt->execute([$real_client_id]);
        $stats['media_uploads'] = $stmt->fetch()['count'] ?? 0;

        // Total Events (all time)
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count 
            FROM events 
            WHERE client_id = ?
        ");
        $stmt->execute([$real_client_id]);
        $stats['total_events'] = $stmt->fetch()['count'] ?? 0;

    } elseif ($user_role === 'admin') {
        // Admin stats - all data

        $stmt = $pdo->query("SELECT COUNT(*) as count FROM events WHERE status = 'published'");
        $stats['total_events'] = $stmt->fetch()['count'] ?? 0;

        $stmt = $pdo->query("SELECT COUNT(*) as count FROM tickets WHERE status = 'active'");
        $stats['tickets_sold'] = $stmt->fetch()['count'] ?? 0;

        $stmt = $pdo->query("SELECT COUNT(*) as count FROM auth_accounts WHERE role = 'user' AND is_active = 1");
        $stats['total_users'] = $stmt->fetch()['count'] ?? 0;

        $stmt = $pdo->query("SELECT COUNT(*) as count FROM media");
        $stats['media_uploads'] = $stmt->fetch()['count'] ?? 0;

    } else {
        // Regular user stats

        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count 
            FROM tickets 
            WHERE user_id = ? 
            AND status = 'active'
        ");
        $stmt->execute([$user_id]);
        $stats['my_tickets'] = $stmt->fetch()['count'] ?? 0;

        $stmt = $pdo->query("SELECT COUNT(*) as count FROM events WHERE status = 'published'");
        $stats['available_events'] = $stmt->fetch()['count'] ?? 0;
    }

    echo json_encode([
        'success' => true,
        'stats' => $stats
    ]);

} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Failed to fetch statistics: ' . $e->getMessage()
    ]);
}
?>