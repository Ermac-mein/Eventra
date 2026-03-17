<?php
/**
 * Get All Tickets API for Admin
 * Returns all tickets globalwide with full details.
 * Revenue = SUM(event.price) per valid ticket.
 */
header('Content-Type: application/json');
require_once '../../config/database.php';
require_once '../../includes/middleware/auth.php';

checkAuth('admin');

try {
    $limit  = min(1000, max(1, (int)($_GET['limit']  ?? 500)));
    $offset = max(0, (int)($_GET['offset'] ?? 0));
    $search = trim($_GET['search'] ?? '');
    $status = trim($_GET['status'] ?? '');

    $where  = '1=1';
    $params = [];

    if ($search !== '') {
        $like    = "%$search%";
        $where  .= " AND (e.event_name LIKE ? OR u.name LIKE ? OR t.ticket_code LIKE ? OR t.custom_id LIKE ?)";
        $params  = array_merge($params, [$like, $like, $like, $like]);
    }
    if ($status !== '' && in_array($status, ['valid', 'used', 'cancelled'])) {
        $where  .= ' AND t.status = ?';
        $params[] = $status;
    }

    // Count
    $countSql = "SELECT COUNT(*) FROM tickets t
                 JOIN events e ON t.event_id = e.id
                 JOIN users u  ON t.user_id  = u.id
                 WHERE $where";
    $cStmt = $pdo->prepare($countSql);
    $cStmt->execute($params);
    $total = (int)$cStmt->fetchColumn();

    // Tickets
    $sql = "
        SELECT
            t.id,
            t.custom_id,
            t.ticket_code,
            t.barcode,
            t.status,
            t.used,
            t.created_at,
            e.event_name,
            e.image_path  AS event_image,
            e.category,
            e.price       AS event_price,
            e.event_date,
            u.name        AS user_name,
            p.amount,
            p.status      AS payment_status,
            p.reference,
            p.custom_id   AS payment_custom_id,
            c.business_name AS organiser_name
        FROM tickets t
        JOIN events e   ON t.event_id  = e.id
        JOIN payments p ON t.payment_id= p.id
        JOIN users u    ON t.user_id   = u.id
        JOIN clients c  ON e.client_id = c.id
        WHERE $where
        ORDER BY t.created_at DESC
        LIMIT $limit OFFSET $offset
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Normalise
    foreach ($tickets as &$t) {
        $t['event_price']   = (float)$t['event_price'];
        $t['amount']        = (float)$t['amount'];
        $t['price_display'] = ($t['event_price'] === 0.0)
            ? 'Free'
            : '₦' . number_format($t['event_price'], 2);
        // For legacy template compat
        $t['total_price'] = $t['event_price'];
    }
    unset($t);

    // Stats
    $statsSql = "
        SELECT
            COUNT(*)                                                              AS total_tickets,
            SUM(CASE WHEN t.status = 'used'      THEN 1 ELSE 0 END)             AS used_tickets,
            SUM(CASE WHEN t.status = 'cancelled' THEN 1 ELSE 0 END)             AS cancelled_tickets,
            COALESCE(SUM(CASE WHEN p.status='paid' THEN e.price ELSE 0 END), 0) AS total_revenue
        FROM tickets t
        JOIN payments p ON t.payment_id = p.id
        JOIN events e   ON t.event_id   = e.id
    ";
    $sStmt = $pdo->query($statsSql);
    $stats = $sStmt->fetch(PDO::FETCH_ASSOC);
    $stats['total_tickets']    = (int)$stats['total_tickets'];
    $stats['used_tickets']     = (int)$stats['used_tickets'];
    $stats['cancelled_tickets']= (int)$stats['cancelled_tickets'];
    $stats['total_revenue']    = (float)$stats['total_revenue'];
    $stats['remaining_tickets']= $stats['total_tickets'] - $stats['used_tickets'] - $stats['cancelled_tickets'];

    echo json_encode([
        'success' => true,
        'tickets' => $tickets,
        'stats'   => $stats,
        'total'   => $total,
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
