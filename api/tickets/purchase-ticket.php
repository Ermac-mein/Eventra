<?php
/**
 * Purchase Ticket API
 * Handles ticket purchases for events
 */
header('Content-Type: application/json');
require_once '../../config/database.php';

// Check authentication
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'user') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized. User access required.']);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);
$event_id = $data['event_id'] ?? null;
$quantity = $data['quantity'] ?? 1;
$referred_by_client_name = $data['referred_by_client'] ?? null;
$user_id = $_SESSION['user_id'];

if (!$event_id || $quantity < 1) {
    echo json_encode(['success' => false, 'message' => 'Invalid event ID or quantity']);
    exit;
}

$referred_by_id = null;
if ($referred_by_client_name) {
    // Lookup client ID by name (slugified search)
    $stmt = $pdo->prepare("SELECT id FROM clients WHERE name = ? OR REPLACE(LOWER(name), ' ', '-') = ?");
    $stmt->execute([$referred_by_client_name, $referred_by_client_name]);
    $referred_by_id = $stmt->fetchColumn() ?: null;
}

try {
    // Get event details
    $stmt = $pdo->prepare("SELECT * FROM events WHERE id = ? AND status = 'published'");
    $stmt->execute([$event_id]);
    $event = $stmt->fetch();

    if (!$event) {
        echo json_encode(['success' => false, 'message' => 'Event not found or not available']);
        exit;
    }

    // Calculate total price
    $total_price = $event['price'] * $quantity;

    // Generate unique ticket code
    $ticket_code = 'TK-' . strtoupper(uniqid());

    // Insert ticket
    $stmt = $pdo->prepare("
        INSERT INTO tickets (event_id, client_id, user_id, referred_by_id, quantity, total_price, ticket_code, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, 'active')
    ");
    $stmt->execute([$event_id, $event['client_id'], $user_id, $referred_by_id, $quantity, $total_price, $ticket_code]);

    $ticket_id = $pdo->lastInsertId();

    // Update event attendee count
    $stmt = $pdo->prepare("UPDATE events SET attendee_count = attendee_count + ? WHERE id = ?");
    $stmt->execute([$quantity, $event_id]);

    // Get user details
    $stmt = $pdo->prepare("SELECT name, email FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();

    // Create notifications for admin, client, and user using helper function
    require_once '../utils/notification-helper.php';
    $admin_id = getAdminUserId();

    if ($admin_id) {
        createTicketPurchaseNotification(
            $admin_id,
            $event['client_id'],
            $user_id,
            $user['name'],
            $user['email'],
            $event['event_name'],
            $quantity,
            $total_price
        );
    }

    echo json_encode([
        'success' => true,
        'message' => 'Ticket purchased successfully',
        'ticket' => [
            'id' => $ticket_id,
            'ticket_code' => $ticket_code,
            'quantity' => $quantity,
            'total_price' => $total_price,
            'event_name' => $event['event_name']
        ]
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>