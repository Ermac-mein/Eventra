<?php
/**
 * Unified Search API
 * Searches across events, tickets, users, and media with advanced filters
 */
header('Content-Type: application/json');
require_once '../../config/database.php';
require_once '../../includes/middleware/auth.php';

// Check authentication
$userId = checkAuth();
$userRole = $_SESSION['role'];

$query = $_GET['q'] ?? '';
$type = $_GET['type'] ?? 'all'; // all, events, tickets, users, media
$category = $_GET['category'] ?? null;
$priority = $_GET['priority'] ?? null;
$status = $_GET['status'] ?? null;
$dateFrom = $_GET['date_from'] ?? null;
$dateTo = $_GET['date_to'] ?? null;

if (strlen($query) < 2 && !$category && !$priority && !$status && !$dateFrom) {
    echo json_encode(['success' => true, 'results' => []]);
    exit;
}

$searchTerm = "%$query%";
$results = [
    'events' => [],
    'tickets' => [],
    'users' => [],
    'media' => []
];

try {
    // 1. Search Events
    if ($type === 'all' || $type === 'events') {
        $sql = "SELECT id, event_name as title, event_type as subtitle, state, status, event_date, event_time, image_path, priority 
                FROM events 
                WHERE deleted_at IS NULL AND (event_name LIKE ? OR description LIKE ? OR state LIKE ? OR event_type LIKE ?)";

        $params = [$searchTerm, $searchTerm, $searchTerm, $searchTerm];

        // Add filters
        if ($category) {
            $sql .= " AND event_type = ?";
            $params[] = $category;
        }
        if ($priority) {
            $sql .= " AND priority = ?";
            $params[] = $priority;
        }
        if ($status) {
            $sql .= " AND status = ?";
            $params[] = $status;
        }
        if ($dateFrom) {
            $sql .= " AND event_date >= ?";
            $params[] = $dateFrom;
        }
        if ($dateTo) {
            $sql .= " AND event_date <= ?";
            $params[] = $dateTo;
        }

        if ($userRole === 'client') {
            // Resolve real client_id from auth_id
            $c_stmt = $pdo->prepare("SELECT id FROM clients WHERE auth_id = ?");
            $c_stmt->execute([$userId]);
            $realClientId = $c_stmt->fetchColumn();

            $sql .= " AND client_id = ?";
            $params[] = $realClientId;
        } elseif ($userRole !== 'admin') {
            $sql .= " AND status = 'published'";
        }

        $sql .= " ORDER BY event_date DESC LIMIT 20";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $results['events'] = $stmt->fetchAll();
    }

    // 2. Search Tickets (Admins and Clients only)
    if (($userRole === 'admin' || $userRole === 'client') && ($type === 'all' || $type === 'tickets')) {
        // FIXED: Added proper JOIN to auth_accounts for email field
        $sql = "SELECT t.id, t.ticket_code as title, e.event_name as subtitle, u.display_name as extra, 
                       a.email as user_email, t.purchase_date, t.quantity
                FROM tickets t
                INNER JOIN events e ON t.event_id = e.id
                LEFT JOIN users u ON t.user_id = u.auth_id
                LEFT JOIN auth_accounts a ON t.user_id = a.id
                WHERE (t.ticket_code LIKE ? OR u.display_name LIKE ? OR a.email LIKE ? OR e.event_name LIKE ?)";

        $params = [$searchTerm, $searchTerm, $searchTerm, $searchTerm];

        if ($userRole === 'client') {
            // Resolve real client_id from auth_id
            $c_stmt = $pdo->prepare("SELECT id FROM clients WHERE auth_id = ?");
            $c_stmt->execute([$userId]);
            $realClientId = $c_stmt->fetchColumn();

            $sql .= " AND t.client_id = ?";
            $params[] = $realClientId;
        }

        // Add date filter for tickets
        if ($dateFrom) {
            $sql .= " AND t.purchase_date >= ?";
            $params[] = $dateFrom;
        }
        if ($dateTo) {
            $sql .= " AND t.purchase_date <= ?";
            $params[] = $dateTo;
        }

        $sql .= " ORDER BY t.purchase_date DESC LIMIT 20";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $results['tickets'] = $stmt->fetchAll();
    }

    // 3. Search Users (Admins and Clients only)
    if (($userRole === 'admin' || $userRole === 'client') && ($type === 'all' || $type === 'users')) {
        if ($userRole === 'admin') {
            // FIXED: Properly join users and auth_accounts
            $sql = "SELECT a.id, a.email as title, a.role as subtitle, u.display_name, u.profile_pic, a.created_at 
                    FROM auth_accounts a
                    LEFT JOIN users u ON a.id = u.auth_id
                    WHERE a.email LIKE ? OR u.display_name LIKE ? 
                    LIMIT 20";
            $params = [$searchTerm, $searchTerm];
        } else {
            // Client: search users who bought tickets for their events
            $c_stmt = $pdo->prepare("SELECT id FROM clients WHERE auth_id = ?");
            $c_stmt->execute([$userId]);
            $realClientId = $c_stmt->fetchColumn();

            $sql = "SELECT DISTINCT u.auth_id as id, u.display_name as title, a.email as subtitle, u.profile_pic
                    FROM users u
                    INNER JOIN tickets t ON u.auth_id = t.user_id
                    INNER JOIN events e ON t.event_id = e.id
                    INNER JOIN auth_accounts a ON u.auth_id = a.id
                    WHERE e.client_id = ? AND (u.display_name LIKE ? OR a.email LIKE ?)
                    LIMIT 20";
            $params = [$realClientId, $searchTerm, $searchTerm];
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $results['users'] = $stmt->fetchAll();
    }

    // 4. Search Media (NEW - Admins and Clients only)
    if (($userRole === 'admin' || $userRole === 'client') && ($type === 'all' || $type === 'media')) {
        $sql = "SELECT m.id, m.file_name as title, m.file_type as subtitle, m.file_path, m.file_size, 
                       m.uploaded_at
                FROM media m
                WHERE (m.file_name LIKE ? OR m.file_type LIKE ?)";

        $params = [$searchTerm, $searchTerm];

        if ($userRole === 'client') {
            // Resolve real client_id from auth_id
            $c_stmt = $pdo->prepare("SELECT id FROM clients WHERE auth_id = ?");
            $c_stmt->execute([$userId]);
            $realClientId = $c_stmt->fetchColumn();

            $sql .= " AND m.client_id = ?";
            $params[] = $realClientId;
        }

        // Add date filter for media
        if ($dateFrom) {
            $sql .= " AND m.uploaded_at >= ?";
            $params[] = $dateFrom;
        }
        if ($dateTo) {
            $sql .= " AND m.uploaded_at <= ?";
            $params[] = $dateTo;
        }

        $sql .= " ORDER BY m.uploaded_at DESC LIMIT 20";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $results['media'] = $stmt->fetchAll();
    }

    echo json_encode([
        'success' => true,
        'results' => $results
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    error_log("Search error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Search failed: ' . $e->getMessage()]);
}
