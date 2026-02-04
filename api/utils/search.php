<?php
/**
 * Unified Search API
 * Searches across events, tickets, and user accounts
 */
header('Content-Type: application/json');
require_once '../../config/database.php';
require_once '../../includes/middleware/auth.php';

// Check authentication
$userId = checkAuth();
$userRole = $_SESSION['role'];

$query = $_GET['q'] ?? '';
$type = $_GET['type'] ?? 'all'; // all, events, tickets, users

if (strlen($query) < 2) {
    echo json_encode(['success' => true, 'results' => []]);
    exit;
}

$searchTerm = "%$query%";
$results = [
    'events' => [],
    'tickets' => [],
    'users' => []
];

try {
    // 1. Search Events
    if ($type === 'all' || $type === 'events') {
        $sql = "SELECT id, event_name as title, event_type as subtitle, state, status, event_date, event_time, image_path 
                FROM events 
                WHERE (event_name LIKE ? OR description LIKE ? OR state LIKE ?)";

        $params = [$searchTerm, $searchTerm, $searchTerm];

        if ($userRole === 'client') {
            $sql .= " AND client_id = ?";
            $params[] = $userId;
        } elseif ($userRole !== 'admin') {
            $sql .= " AND status = 'published'";
        }

        $sql .= " ORDER BY event_date DESC LIMIT 10";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $results['events'] = $stmt->fetchAll();
    }

    // 2. Search Tickets (Admins and Clients only)
    if (($userRole === 'admin' || $userRole === 'client') && ($type === 'all' || $type === 'tickets')) {
        $sql = "SELECT t.id, t.ticket_code as title, e.event_name as subtitle, u.display_name as extra, t.purchase_date
                FROM tickets t
                INNER JOIN events e ON t.event_id = e.id
                INNER JOIN users u ON t.user_id = u.auth_id
                WHERE (t.ticket_code LIKE ? OR u.display_name LIKE ? OR u.email LIKE ?)";

        $params = [$searchTerm, $searchTerm, $searchTerm];

        if ($userRole === 'client') {
            $sql .= " AND e.client_id = ?";
            $params[] = $userId;
        }

        $sql .= " ORDER BY t.purchase_date DESC LIMIT 10";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $results['tickets'] = $stmt->fetchAll();
    }

    // 3. Search Users (Admins and Clients only)
    if (($userRole === 'admin' || $userRole === 'client') && ($type === 'all' || $type === 'users')) {
        if ($userRole === 'admin') {
            $sql = "SELECT id, email as title, role as subtitle, created_at FROM auth_accounts WHERE email LIKE ? LIMIT 10";
            $params = [$searchTerm];
        } else {
            // Client: search users who bought tickets for their events
            $sql = "SELECT DISTINCT u.auth_id as id, u.display_name as title, u.email as subtitle, u.profile_pic
                    FROM users u
                    INNER JOIN tickets t ON u.auth_id = t.user_id
                    INNER JOIN events e ON t.event_id = e.id
                    WHERE e.client_id = ? AND (u.display_name LIKE ? OR u.email LIKE ?)";
            $params = [$userId, $searchTerm, $searchTerm];
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $results['users'] = $stmt->fetchAll();
    }

    echo json_encode([
        'success' => true,
        'results' => $results
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Search failed: ' . $e->getMessage()]);
}
