<?php

/**
 * Get User Profile API
 * Retrieves user or client profile information
 */

header('Content-Type: application/json');
require_once '../../config/database.php';
require_once '../../includes/middleware/auth.php';

// Check authentication
$auth_id = getAuthId();
$user_id = $_GET['user_id'] ?? $auth_id;

// Check if requesting own profile or admin
if ($user_id != $auth_id && $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

try {
    require_once '../../includes/helpers/entity-resolver.php';

    // Use the robust entity resolver
    $user = resolveEntity($user_id, $_SESSION['role']);

    if (!$user && isset($_SESSION['role'])) {
        $user = resolveEntity($user_id); // Fallback if role is not in session or mismatch exists
    }

    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'User not found']);
        exit;
    }

    // Remove password from response
    unset($user['password']);

    // Ensure profile_pic has leading slash for absolute path parsing
    if (!empty($user['profile_pic']) && strpos($user['profile_pic'], '/') !== 0) {
        $user['profile_pic'] = '/' . $user['profile_pic'];
    }

    echo json_encode([
        'success' => true,
        'user' => $user
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
