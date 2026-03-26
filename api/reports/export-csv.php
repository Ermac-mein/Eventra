<?php

header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="report_' . date('Y-m-d_H-i-s') . '.csv"');

require_once '../../config/database.php';
require_once '../../includes/middleware/auth.php';

// Check auth (Admin or Client)
$user_id = checkAuth(); // General check, then we refine
$role = $_SESSION['role'];

$type = $_GET['type'] ?? 'events';

// Open output stream
$output = fopen('php://output', 'w');

try {
    if ($type === 'events') {
        fputcsv($output, ['ID', 'Event Name', 'Status', 'Date', 'Time', 'Location', 'Revenue', 'Attendees']);

        $sql = "SELECT e.id, e.event_name, e.status, e.event_date, e.event_time, e.state, 
                       COALESCE(SUM(p.amount), 0) as revenue, COUNT(t.id) as attendees
                FROM events e
                LEFT JOIN payments p ON e.id = p.event_id AND p.status = 'paid'
                LEFT JOIN tickets t ON p.id = t.payment_id
                WHERE e.deleted_at IS NULL ";

        if ($role === 'client') {
            $client_id = $user_id;
            $sql .= " AND e.client_id = ? ";
            $stmt = $pdo->prepare($sql . " GROUP BY e.id");
            $stmt->execute([$client_id]);
        } else {
            $stmt = $pdo->prepare($sql . " GROUP BY e.id");
            $stmt->execute();
        }

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            fputcsv($output, $row);
        }
    } elseif ($type === 'tickets') {
        fputcsv($output, ['ID', 'Event', 'Buyer Name', 'Buyer Email', 'Price', 'Barcode', 'Used', 'Paid At']);

        $sql = "SELECT t.id, e.event_name, u.name, a.email, p.amount, t.barcode, t.used, p.paid_at
                FROM tickets t
                JOIN payments p ON t.payment_id = p.id
                JOIN events e ON p.event_id = e.id
                JOIN users u ON p.user_id = u.id
                JOIN auth_accounts a ON u.user_auth_id = a.id
                WHERE p.status = 'paid' ";

        if ($role === 'client') {
            $client_id = $user_id;
            $sql .= " AND e.client_id = ? ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$client_id]);
        } else {
            $stmt = $pdo->prepare($sql);
            $stmt->execute();
        }

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            fputcsv($output, $row);
        }
    }
} catch (Exception $e) {
    error_log("Export Error: " . $e->getMessage());
}

fclose($output);
exit();
