<?php
/**
 * Get Event Details API
 * Retrieves detailed information about a specific event (including deleted ones)
 */
header('Content-Type: application/json');
require_once '../../config/database.php';
require_once '../../includes/middleware/auth.php';

// Check authentication
$user_id = checkAuth();
$user_role = $_SESSION['role'];

$event_id = $_GET['event_id'] ?? null;

if (!$event_id) {
    echo json_encode(['success' => false, 'message' => 'Event ID is required']);
    exit;
}

try {
    // Get event details with client information
    $stmt = $pdo->prepare("
        SELECT e.*, c.business_name as client_name
        FROM events e
        LEFT JOIN clients c ON e.client_id = c.id
        WHERE e.id = ?
    ");
    $stmt->execute([$event_id]);
    $event = $stmt->fetch();

    if (!$event) {
        echo json_encode(['success' => false, 'message' => 'Event not found']);
        exit;
    }

    // Check permissions
    if ($user_role === 'client') {
        // Resolve client_id
        $stmt = $pdo->prepare("SELECT id FROM clients WHERE auth_id = ?");
        $stmt->execute([$user_id]);
        $client = $stmt->fetch();

        if (!$client || $event['client_id'] != $client['id']) {
            echo json_encode(['success' => false, 'message' => 'You do not have permission to view this event']);
            exit;
        }
    }

    echo json_encode([
        'success' => true,
        'event' => $event
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>