<?php
/**
 * Get Upcoming Events API for Schedule Notifications
 * Returns upcoming events for the authenticated client
 */
// Error reporting temporarily enabled for debugging
ini_set('display_errors', 0);
error_reporting(E_ALL);

header('Content-Type: application/json');

try {
    require_once '../../config/database.php';
    require_once '../../includes/middleware/auth.php';

    // Check authentication and ensure it's a client
    $auth_id = clientMiddleware();

    // Use auth_id directly (it is client_id from clientMiddleware)
    $real_client_id = $auth_id;

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
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'events' => $events
    ]);

} catch (Throwable $e) {
    http_response_code(500);
    error_log("Get upcoming events BIG FATAL error: " . $e->getMessage() . " on line " . $e->getLine());
    echo json_encode([
        'success' => false,
        'message' => 'Critical error: ' . $e->getMessage()
    ]);
}
