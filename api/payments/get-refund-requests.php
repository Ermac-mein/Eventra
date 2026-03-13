<?php
/**
 * Get Refund Requests API (Organizer)
 * Returns all refund requests for the organizer's events.
 */
header('Content-Type: application/json');
require_once '../../config/database.php';
require_once '../../includes/middleware/auth.php';

$client_auth_id = clientMiddleware();

$status_filter = $_GET['status'] ?? ''; // '', 'pending', 'approved', 'declined'

try {
    // Fetch organizer's client id
    $cStmt = $pdo->prepare("SELECT id FROM clients WHERE client_auth_id = ?");
    $cStmt->execute([$client_auth_id]);
    $client = $cStmt->fetch(PDO::FETCH_ASSOC);

    if (!$client) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Client not found.']);
        exit;
    }

    $where = "WHERE o.organizer_id = ?";
    $params = [$client['id']];

    if ($status_filter !== '') {
        $where .= " AND rr.status = ?";
        $params[] = $status_filter;
    }

    $stmt = $pdo->prepare("
        SELECT rr.id, rr.order_id, rr.reason, rr.status, rr.organizer_note, rr.processed_at, rr.created_at,
               o.amount, o.transaction_reference, o.refund_status,
               e.event_name, e.event_date,
               u.name AS user_name,
               a.email AS user_email
        FROM refund_requests rr
        JOIN orders o ON rr.order_id = o.id
        JOIN events e ON o.event_id = e.id
        JOIN users u ON rr.user_id = u.id
        JOIN auth_accounts a ON u.user_auth_id = a.id
        {$where}
        ORDER BY rr.created_at DESC
    ");
    $stmt->execute($params);
    $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'requests' => $requests, 'total' => count($requests)]);

} catch (PDOException $e) {
    error_log('[get-refund-requests.php] DB error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to fetch refund requests.']);
}
