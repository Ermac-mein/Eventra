<?php
/**
 * Get Event Details API
 * Retrieves detailed information about a specific event (including deleted ones)
 */
header('Content-Type: application/json');
require_once '../../config/database.php';
require_once '../../includes/middleware/auth.php';

// Check authentication (optional for public view)
$user_id = null;
$user_role = 'guest';

if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $user_role = $_SESSION['role'] ?? 'guest';
}

$event_id = $_GET['event_id'] ?? null;

if (!$event_id) {
    echo json_encode(['success' => false, 'message' => 'Event ID is required']);
    exit;
}

try {
    // Get event details with client information
    $stmt = $pdo->prepare("
        SELECT e.*, c.business_name as client_name, c.profile_pic as client_profile_pic
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

    // For non-admins/clients, only show published events (unless it's their own)
    if ($user_role !== 'admin' && $event['status'] !== 'published') {
        if ($user_role === 'client') {
            // Check if it's the client's own event
            $stmt = $pdo->prepare("SELECT id FROM clients WHERE auth_id = ?");
            $stmt->execute([$user_id]);
            $client = $stmt->fetch();
            if (!$client || $event['client_id'] != $client['id']) {
                echo json_encode(['success' => false, 'message' => 'Event not published']);
                exit;
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Event not published']);
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