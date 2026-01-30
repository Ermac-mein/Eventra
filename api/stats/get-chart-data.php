<?php
/**
 * Get Chart Data API
 * Provides time-series data for dashboard charts
 */
header('Content-Type: application/json');
require_once '../../config/database.php';

// Check authentication
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$user_role = $_SESSION['role'];
$user_id = $_SESSION['user_id'];
$period = $_GET['period'] ?? '7days'; // 7days, 30days, 90days

// Determine date range
$date_ranges = [
    '7days' => 7,
    '30days' => 30,
    '90days' => 90
];

$days = $date_ranges[$period] ?? 7;
$start_date = date('Y-m-d', strtotime("-{$days} days"));

try {
    if ($user_role === 'admin') {
        // Admin chart data - events, tickets, users over time

        // Events created per day
        $stmt = $pdo->prepare("
            SELECT DATE(created_at) as date, COUNT(*) as count
            FROM events
            WHERE created_at >= ?
            GROUP BY DATE(created_at)
            ORDER BY date ASC
        ");
        $stmt->execute([$start_date]);
        $events_data = $stmt->fetchAll();

        // Tickets sold per day
        $stmt = $pdo->prepare("
            SELECT DATE(purchase_date) as date, COUNT(*) as count, SUM(total_price) as revenue
            FROM tickets
            WHERE purchase_date >= ?
            GROUP BY DATE(purchase_date)
            ORDER BY date ASC
        ");
        $stmt->execute([$start_date]);
        $tickets_data = $stmt->fetchAll();

        // Users registered per day
        $stmt = $pdo->prepare("
            SELECT DATE(created_at) as date, COUNT(*) as count
            FROM users
            WHERE created_at >= ? AND role = 'user'
            GROUP BY DATE(created_at)
            ORDER BY date ASC
        ");
        $stmt->execute([$start_date]);
        $users_data = $stmt->fetchAll();

        // Format data for Chart.js
        $labels = [];
        $events_counts = [];
        $tickets_counts = [];
        $revenue_data = [];
        $users_counts = [];

        // Create a complete date range
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-{$i} days"));
            $labels[] = date('M d', strtotime($date));

            // Find matching data or use 0
            $events_counts[] = findCountForDate($events_data, $date);
            $tickets_counts[] = findCountForDate($tickets_data, $date);
            $revenue_data[] = findRevenueForDate($tickets_data, $date);
            $users_counts[] = findCountForDate($users_data, $date);
        }

        echo json_encode([
            'success' => true,
            'period' => $period,
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => 'Events Created',
                    'data' => $events_counts,
                    'borderColor' => 'rgb(59, 130, 246)',
                    'backgroundColor' => 'rgba(59, 130, 246, 0.1)'
                ],
                [
                    'label' => 'Tickets Sold',
                    'data' => $tickets_counts,
                    'borderColor' => 'rgb(16, 185, 129)',
                    'backgroundColor' => 'rgba(16, 185, 129, 0.1)'
                ],
                [
                    'label' => 'Users Registered',
                    'data' => $users_counts,
                    'borderColor' => 'rgb(245, 158, 11)',
                    'backgroundColor' => 'rgba(245, 158, 11, 0.1)'
                ]
            ],
            'revenue' => [
                'label' => 'Revenue (₦)',
                'data' => $revenue_data
            ]
        ]);

    } elseif ($user_role === 'client') {
        // Client chart data - ticket sales and revenue for their events

        $stmt = $pdo->prepare("
            SELECT DATE(t.purchase_date) as date, COUNT(*) as count, SUM(t.total_price) as revenue
            FROM tickets t
            INNER JOIN events e ON t.event_id = e.id
            WHERE e.client_id = ? AND t.purchase_date >= ?
            GROUP BY DATE(t.purchase_date)
            ORDER BY date ASC
        ");
        $stmt->execute([$user_id, $start_date]);
        $sales_data = $stmt->fetchAll();

        // Format data
        $labels = [];
        $tickets_counts = [];
        $revenue_data = [];

        for ($i = $days - 1; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-{$i} days"));
            $labels[] = date('M d', strtotime($date));

            $tickets_counts[] = findCountForDate($sales_data, $date);
            $revenue_data[] = findRevenueForDate($sales_data, $date);
        }

        echo json_encode([
            'success' => true,
            'period' => $period,
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => 'Tickets Sold',
                    'data' => $tickets_counts,
                    'borderColor' => 'rgb(139, 92, 246)',
                    'backgroundColor' => 'rgba(139, 92, 246, 0.1)'
                ],
                [
                    'label' => 'Revenue (₦)',
                    'data' => $revenue_data,
                    'borderColor' => 'rgb(16, 185, 129)',
                    'backgroundColor' => 'rgba(16, 185, 129, 0.1)'
                ]
            ]
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid role for chart data']);
    }

} catch (PDOException $e) {
    http_response_code(500);
    error_log("Chart data error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Failed to fetch chart data']);
}

/**
 * Helper function to find count for a specific date
 */
function findCountForDate($data, $date)
{
    foreach ($data as $row) {
        if ($row['date'] === $date) {
            return (int) $row['count'];
        }
    }
    return 0;
}

/**
 * Helper function to find revenue for a specific date
 */
function findRevenueForDate($data, $date)
{
    foreach ($data as $row) {
        if ($row['date'] === $date) {
            return (float) ($row['revenue'] ?? 0);
        }
    }
    return 0;
}
?>