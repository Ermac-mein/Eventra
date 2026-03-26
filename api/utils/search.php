<?php

/**
 * Unified Search API
 * Searches across events, tickets, users, and media based on role-specific visibility.
 */

header('Content-Type: application/json');
require_once '../../config/database.php';
require_once '../../includes/middleware/auth.php';

// Check authentication
checkAuth();
$auth_id = getAuthId();
$role = $_SESSION['role'] ?? 'user';

$query = $_GET['q'] ?? '';
if (empty($query) || strlen($query) < 2) {
    echo json_encode(['success' => true, 'results' => ['events' => [], 'tickets' => [], 'users' => [], 'media' => []]]);
    exit;
}

$searchTerm = "%$query%";

try {
    $results = [
        'events' => [],
        'tickets' => [],
        'users' => [],
        'media' => []
    ];

    // --- 1. SEARCH EVENTS ---
    $eventSql = "SELECT id, event_name as title, SUBSTRING(description, 1, 60) as subtitle, category, price 
                 FROM events 
                 WHERE (event_name LIKE ? OR description LIKE ?) AND deleted_at IS NULL";

    if ($role === 'client') {
        $eventSql .= " AND client_id = (SELECT id FROM clients WHERE client_auth_id = ?)";
    }

    $stmt = $pdo->prepare($eventSql);
    if ($role === 'client') {
        $stmt->execute([$searchTerm, $searchTerm, $auth_id]);
    } else {
        $stmt->execute([$searchTerm, $searchTerm]);
    }
    $results['events'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // --- 2. SEARCH TICKETS / ORDERS ---
    $ticketSql = "SELECT t.id, e.event_name as title, CONCAT('Ticket #', t.id) as subtitle, u.name as extra
                  FROM tickets t
                  JOIN events e ON t.event_id = e.id
                  JOIN users u ON t.user_id = u.id
                  WHERE (e.event_name LIKE ? OR u.name LIKE ? OR t.id LIKE ?)";

    if ($role === 'client') {
        $ticketSql .= " AND e.client_id = (SELECT id FROM clients WHERE client_auth_id = ?)";
    }

    $stmt = $pdo->prepare($ticketSql);
    if ($role === 'client') {
        $stmt->execute([$searchTerm, $searchTerm, $searchTerm, $auth_id]);
    } else {
        $stmt->execute([$searchTerm, $searchTerm, $searchTerm]);
    }
    $results['tickets'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // --- 3. SEARCH USERS / ATTENDEES ---
    if ($role === 'admin') {
        $userSql = "SELECT u.id, u.name as title, aa.email as subtitle 
                    FROM users u 
                    JOIN auth_accounts aa ON u.user_auth_id = aa.id
                    WHERE (u.name LIKE ? OR aa.email LIKE ? OR u.phone LIKE ?)";
        $stmt = $pdo->prepare($userSql);
        $stmt->execute([$searchTerm, $searchTerm, $searchTerm]);
    } else {
        // Clients only see users who have bought tickets to their events
        $userSql = "SELECT DISTINCT u.id, u.name as title, aa.email as subtitle 
                    FROM users u 
                    JOIN auth_accounts aa ON u.user_auth_id = aa.id
                    JOIN tickets t ON t.user_id = u.id
                    JOIN events e ON t.event_id = e.id
                    WHERE (u.name LIKE ? OR aa.email LIKE ? OR u.phone LIKE ?)
                    AND e.client_id = (SELECT id FROM clients WHERE client_auth_id = ?)";
        $stmt = $pdo->prepare($userSql);
        $stmt->execute([$searchTerm, $searchTerm, $searchTerm, $auth_id]);
    }
    $results['users'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // --- 4. SEARCH MEDIA ---
    $mediaSql = "SELECT id, file_name as title, CONCAT(file_type, ' • ', file_size, ' bytes') as subtitle, file_size, 'file' as item_type
                 FROM media
                 WHERE file_name LIKE ? AND is_deleted = 0";

    if ($role === 'client') {
        $mediaSql .= " AND client_id = (SELECT id FROM clients WHERE client_auth_id = ?)";
    }

    $stmt = $pdo->prepare($mediaSql);
    if ($role === 'client') {
        $stmt->execute([$searchTerm, $auth_id]);
    } else {
        $stmt->execute([$searchTerm]);
    }
    $results['media'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'results' => $results
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Search error: ' . $e->getMessage()]);
}
