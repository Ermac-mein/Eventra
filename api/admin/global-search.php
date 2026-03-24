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
        'clients' => [],
        'tickets' => [],
        'payments' => []
    ];

    // 1. Search Events
    $stmt = $pdo->prepare("
        SELECT id, custom_id, event_name as name, event_type as type, state, status, address
        FROM events
        WHERE (event_name LIKE ? OR description LIKE ? OR state LIKE ? OR event_type LIKE ? OR custom_id LIKE ? OR address LIKE ? OR location LIKE ?) AND deleted_at IS NULL
        LIMIT 5
    ");
    $stmt->execute([$searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm]);
    $results['events'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 2. Search Users
    $stmt = $pdo->prepare("
        SELECT id, custom_id, name, email, profile_pic, phone
        FROM users
        WHERE (name LIKE ? OR email LIKE ? OR custom_id LIKE ? OR phone LIKE ?)
        LIMIT 5
    ");
    $stmt->execute([$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
    $results['users'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 3. Search Clients
    $stmt = $pdo->prepare("
        SELECT id, custom_id, business_name as name, email, profile_pic, company, phone, subaccount_code
        FROM clients
        WHERE (business_name LIKE ? OR email LIKE ? OR company LIKE ? OR custom_id LIKE ? OR phone LIKE ? OR subaccount_code LIKE ?)
        LIMIT 5
    ");
    $stmt->execute([$searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm]);
    $results['clients'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 4. Search Tickets
    $stmt = $pdo->prepare("
        SELECT t.id, t.custom_id, t.barcode, u.name as user_name, e.event_name, t.status
        FROM tickets t
        JOIN users u ON t.user_id = u.id
        JOIN events e ON t.event_id = e.id
        WHERE t.custom_id LIKE ? OR t.barcode LIKE ?
        LIMIT 5
    ");
    $stmt->execute([$searchTerm, $searchTerm]);
    $results['tickets'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 5. Search Payments
    $stmt = $pdo->prepare("
        SELECT p.id, p.custom_id, p.reference, u.name as user_name, p.amount, p.status
        FROM payments p
        JOIN users u ON p.user_id = u.id
        WHERE p.custom_id LIKE ? OR p.reference LIKE ? OR p.transaction_id LIKE ?
        LIMIT 5
    ");
    $stmt->execute([$searchTerm, $searchTerm, $searchTerm]);
    $results['payments'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
