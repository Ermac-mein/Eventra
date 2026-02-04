<?php
/**
 * Global Search API
 * Unified search for events, users, and clients
 */
header('Content-Type: application/json');
require_once '../../config/database.php';
require_once '../../includes/middleware/auth.php';

// Check admin authentication
$admin_id = checkAuth('admin');

$query = $_GET['q'] ?? '';
$query = trim($query);

if (strlen($query) < 2) {
    echo json_encode([
        'success' => true,
        'results' => [
            'events' => [],
            'users' => [],
            'clients' => []
        ]
    ]);
    exit;
}

$searchTerm = '%' . $query . '%';

try {
    $results = [
        'events' => [],
        'users' => [],
        'clients' => []
    ];

    // 1. Search Events
    $stmt = $pdo->prepare("
        SELECT id, event_name as name, event_type as type, state, tag
        FROM events
        WHERE event_name LIKE ? OR description LIKE ? OR state LIKE ?
        LIMIT 5
    ");
    $stmt->execute([$searchTerm, $searchTerm, $searchTerm]);
    $results['events'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 2. Search Users
    $stmt = $pdo->prepare("
        SELECT a.id, u.display_name as name, a.email, u.profile_pic
        FROM auth_accounts a
        JOIN users u ON a.id = u.auth_id
        WHERE (u.display_name LIKE ? OR a.email LIKE ?) AND a.role = 'user'
        LIMIT 5
    ");
    $stmt->execute([$searchTerm, $searchTerm]);
    $results['users'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 3. Search Clients
    $stmt = $pdo->prepare("
        SELECT a.id, c.business_name as name, a.email, c.profile_pic, c.company
        FROM auth_accounts a
        JOIN clients c ON a.id = c.auth_id
        WHERE (c.business_name LIKE ? OR a.email LIKE ? OR c.company LIKE ?) AND a.role = 'client'
        LIMIT 5
    ");
    $stmt->execute([$searchTerm, $searchTerm, $searchTerm]);
    $results['clients'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'results' => $results,
        'query' => $query
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    error_log("Global Search Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Search failed due to database error.']);
}
?>