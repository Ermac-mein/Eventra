<?php
/**
 * Dashboard Statistics API
 * Returns statistics for the client dashboard
 */
header('Content-Type: application/json');
require_once '../../config/database.php';
require_once '../../includes/middleware/auth.php';

// Check authentication
$user_id = checkAuth();
$user_role = $_SESSION['user_role'] ?? $_SESSION['role'] ?? 'user';

try {
    $stats = [];

    if ($user_role === 'client') {
        // Resolve real_client_id from auth_id
        $client_stmt = $pdo->prepare("SELECT id FROM clients WHERE client_auth_id = ?");
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
            AND t.status = 'paid'
        ");
        $stmt->execute([$real_client_id]);
        $stats['tickets_sold'] = $stmt->fetch()['count'] ?? 0;

        // Total Registered Users in System
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count 
            FROM auth_accounts 
            WHERE role = 'user' AND is_active = 1
        ");
        $stmt->execute();
        $stats['total_users'] = $stmt->fetch()['count'] ?? 0;

        // Media Items (Folders + Files that are not deleted)
        $stmt = $pdo->prepare("
            SELECT 
                (SELECT COUNT(*) FROM media WHERE client_id = ? AND is_deleted = 0) +
                (SELECT COUNT(*) FROM media_folders WHERE client_id = ? AND is_deleted = 0) as count
        ");
        $stmt->execute([$real_client_id, $real_client_id]);
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

        $stmt = $pdo->query("SELECT COUNT(*) as count FROM tickets WHERE status = 'paid'");
        $stats['tickets_sold'] = $stmt->fetch()['count'] ?? 0;

        $stmt = $pdo->query("SELECT COUNT(*) as count FROM auth_accounts WHERE role = 'user' AND is_active = 1");
        $stats['total_users'] = $stmt->fetch()['count'] ?? 0;

        $stmt = $pdo->query("
            SELECT 
                (SELECT COUNT(*) FROM media WHERE is_deleted = 0) +
                (SELECT COUNT(*) FROM media_folders WHERE is_deleted = 0) as count
        ");
        $stats['media_uploads'] = $stmt->fetch()['count'] ?? 0;

    } else {
        // Regular user stats

        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count 
            FROM tickets 
            WHERE user_id = ? 
            AND status = 'paid'
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
