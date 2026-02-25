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
$payment_reference = $data['payment_reference'] ?? null;
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
        $pdo->rollBack(); // Rollback if event not found
        echo json_encode(['success' => false, 'message' => 'Event not found or not available']);
        exit;
    }

    // Calculate total price
    $total_price = $event['price'] * $quantity;

    // Generate unique ticket code (this is not used later, barcode is generated per ticket)
    // $ticket_code = 'TK-' . strtoupper(uniqid());

    // Handle Payment Binding
    $payment_id = null;
    if ($total_price > 0 && $payment_reference) {
        $stmt = $pdo->prepare("INSERT INTO payments (event_id, user_id, reference, amount, status, paystack_response, paid_at) VALUES (?, ?, ?, ?, 'paid', 'inline_checkout', NOW())");
        $stmt->execute([$event_id, $user_id, $payment_reference, $total_price]);
        $payment_id = $pdo->lastInsertId();
    } elseif ($total_price == 0) {
        // Free ticket
        $stmt = $pdo->prepare("INSERT INTO payments (event_id, user_id, reference, amount, status, paystack_response, paid_at) VALUES (?, ?, ?, ?, 'paid', 'free', NOW())");
        $stmt->execute([$event_id, $user_id, 'FREE-' . uniqid(), 0]);
        $payment_id = $pdo->lastInsertId();
    } else {
        $pdo->rollBack(); // Rollback if payment validation fails
        echo json_encode(['success' => false, 'message' => 'Payment validation failed']);
        exit;
    }

    // Insert ticket
    $stmt = $pdo->prepare("
        INSERT INTO tickets (payment_id, barcode, used, created_at, status)
        VALUES (?, ?, 0, NOW(), 'paid')
    ");
    $tickets_generated = [];

    for ($i = 0; $i < $quantity; $i++) {
        $barcode = 'VIP-' . strtoupper(substr(uniqid(), -8));
        $stmt->execute([$payment_id, $barcode]);
        $tickets_generated[] = $barcode;
    }

    // Update event attendee count
    $stmt = $pdo->prepare("UPDATE events SET attendee_count = attendee_count + ? WHERE id = ?");
    $stmt->execute([$quantity, $event_id]);

    $pdo->commit(); // Commit transaction if all operations succeed

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
        'tickets' => $tickets_generated,
        'quantity' => $quantity,
        'total_price' => $total_price,
        'event_name' => $event['event_name']
    ]);

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack(); // Rollback on any PDO exception
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
