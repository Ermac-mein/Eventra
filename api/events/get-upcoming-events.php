<?php
/**
 * Get Upcoming Events API for Schedule Notifications
 * Returns upcoming events for the authenticated client
 */
header('Content-Type: application/json');
require_once '../../config/database.php';

// Check authentication
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'client') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
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

    // Get upcoming published events for this client
    $stmt = $pdo->prepare("
        SELECT 
            id,
            event_name,
            event_date,
            event_time,
            state,
            image_path,
            description
        FROM events
        WHERE client_id = ? 
            AND status = 'published' 
            AND deleted_at IS NULL
            AND event_date >= CURDATE()
        ORDER BY event_date ASC, event_time ASC
        LIMIT 20
    ");
    $stmt->execute([$real_client_id]);
    $events = $stmt->fetchAll();

    echo json_encode([
        'success' => true,
        'events' => $events
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    error_log("Get upcoming events error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Failed to fetch upcoming events']);
}
?>