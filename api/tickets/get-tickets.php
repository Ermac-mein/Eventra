<?php
/**
 * Get Tickets API
 * Retrieves tickets purchased for the client's events.
 * Revenue = SUM(event.price) per paid ticket row.
 */
header('Content-Type: application/json');
require_once '../../config/database.php';
require_once '../../includes/middleware/auth.php';

try {
    $auth_id  = checkAuth('client');
    $isAdmin  = false;

    $real_client_id = $auth_id;

    // Get tickets with related information
    $stmt = $pdo->prepare("
        SELECT
            t.id,
            t.custom_id,
            t.barcode,
            t.used,
            t.status,
            t.created_at AS purchase_date,
            e.event_name,
            e.image_path AS event_image,
            e.category,
            e.price AS event_price,
            u.name AS buyer_name,
            p.amount,
            p.status AS payment_status,
            p.reference,
            c.business_name AS organiser_name
        FROM tickets t
        JOIN payments p ON t.payment_id = p.id
        JOIN users u ON p.user_id = u.id
        JOIN events e ON p.event_id = e.id
        JOIN clients c ON e.client_id = c.id
        WHERE e.client_id = ?
        ORDER BY t.created_at DESC
    ");
    $stmt->execute([$real_client_id]);
    $tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Normalise price display: 0 or NULL → "Free" flag
    foreach ($tickets as &$ticket) {
        $ticket['price_display'] = (empty($ticket['event_price']) || (float)$ticket['event_price'] === 0.0)
            ? 'Free'
            : number_format((float)$ticket['event_price'], 2);
        $ticket['event_price'] = (float)$ticket['event_price'];
        $ticket['amount']      = (float)$ticket['amount'];
    }
    unset($ticket);

    // Stats: revenue = SUM(e.price) per paid ticket
    $stats_stmt = $pdo->prepare("
        SELECT
            SUM(CASE WHEN p.status = 'paid' THEN 1 ELSE 0 END)                                     AS total_tickets,
            COALESCE(SUM(CASE WHEN p.status = 'paid' THEN e.price ELSE 0 END), 0)                  AS total_revenue,
            SUM(CASE WHEN p.status = 'pending' THEN 1 ELSE 0 END)                                  AS pending_tickets,
            SUM(CASE WHEN t.status = 'cancelled' OR p.status = 'refunded' THEN 1 ELSE 0 END)       AS cancelled_tickets
        FROM tickets t
        JOIN payments p ON t.payment_id = p.id
        JOIN events e   ON p.event_id = e.id
        WHERE e.client_id = ?
    ");
    $stats_stmt->execute([$real_client_id]);
    $stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

    $stats['total_tickets']    = (int)   ($stats['total_tickets']    ?? 0);
    $stats['total_revenue']    = (float) ($stats['total_revenue']    ?? 0.0);
    $stats['pending_tickets']  = (int)   ($stats['pending_tickets']  ?? 0);
    $stats['cancelled_tickets']= (int)   ($stats['cancelled_tickets'] ?? 0);

    echo json_encode([
        'success' => true,
        'tickets' => $tickets,
        'stats'   => $stats,
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'General error: ' . $e->getMessage()]);
}
