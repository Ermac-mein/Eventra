<?php

/**
 * Get Users API
 * Retrieves users who have interacted with the client's events
 */

header('Content-Type: application/json');
require_once '../../config/database.php';
require_once '../../includes/middleware/auth.php';

try {
    $auth_id = checkAuth('client');

    // Resolve real_client_id from auth_id
    $client_stmt = $pdo->prepare("SELECT id FROM clients WHERE client_auth_id = ?");
    $client_stmt->execute([$auth_id]);
    $client_row = $client_stmt->fetch();

    if (!$client_row) {
        echo json_encode(['success' => false, 'message' => 'Client profile not found.']);
        exit;
    }
    $real_client_id = $client_row['id'];

    // Get unique users who have purchased tickets for this client's events
    $stmt = $pdo->prepare("
        SELECT DISTINCT 
            u.id,
            u.custom_id,
            u.name,
            aa.email,
            u.phone,
            u.state,
            u.city,
            u.country,
            u.dob,
            u.gender,
            u.profile_pic,
            aa.created_at,
            'active' as status
        FROM users u
        JOIN auth_accounts aa ON u.user_auth_id = aa.id
        JOIN payments p ON u.id = p.user_id
        JOIN events e ON p.event_id = e.id
        WHERE e.client_id = ? AND p.status = 'paid'
        ORDER BY aa.created_at DESC
    ");
    $stmt->execute([$real_client_id]);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calculate basic stats for this client
    $total_distinct_users = count($users);

    // Engaged users are those with more than 1 payment (optional refinement)
    // For now, let's just use the count of users who bought tickets

    echo json_encode([
        'success' => true,
        'users' => $users,
        'stats' => [
            'total_users' => $total_distinct_users,
            'active_users' => $total_distinct_users,
            'engaged_users' => $total_distinct_users
        ]
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'General error: ' . $e->getMessage()]);
}
