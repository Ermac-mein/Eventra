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
$user_id = $_SESSION['user_id'];

if (!$event_id || $quantity < 1) {
    echo json_encode(['success' => false, 'message' => 'Invalid event ID or quantity']);
    exit;
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
        INSERT INTO tickets (event_id, user_id, quantity, total_price, ticket_code, status)
        VALUES (?, ?, ?, ?, ?, 'active')
    ");
    $stmt->execute([$event_id, $user_id, $quantity, $total_price, $ticket_code]);

    $ticket_id = $pdo->lastInsertId();

    // Update event attendee count
    $stmt = $pdo->prepare("UPDATE events SET attendee_count = attendee_count + ? WHERE id = ?");
    $stmt->execute([$quantity, $event_id]);

    // Get user details
    $stmt = $pdo->prepare("SELECT name FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();

    // Create notification for client
    $message = "User '{$user['name']}' purchased {$quantity} ticket(s) for '{$event['event_name']}'";
    $stmt = $pdo->prepare("INSERT INTO notifications (recipient_id, sender_id, message, type) VALUES (?, ?, ?, ?)");
    $stmt->execute([$event['client_id'], $user_id, $message, 'ticket_purchased']);

    // Create notification for admin
    $stmt = $pdo->prepare("SELECT id FROM users WHERE role = 'admin' LIMIT 1");
    $stmt->execute();
    $admin = $stmt->fetch();

    if ($admin) {
        $stmt = $pdo->prepare("INSERT INTO notifications (recipient_id, sender_id, message, type) VALUES (?, ?, ?, ?)");
        $stmt->execute([$admin['id'], $user_id, $message, 'ticket_purchased']);
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