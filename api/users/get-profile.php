<?php
/**
 * Get User Profile API
 * Retrieves user or client profile information
 */
header('Content-Type: application/json');
require_once '../../config/database.php';

// Check authentication
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$user_id = $_GET['user_id'] ?? $_SESSION['user_id'];

// Check if requesting own profile or admin
if ($user_id != $_SESSION['user_id'] && $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();

    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'User not found']);
        exit;
    }

    // Remove password from response
    unset($user['password']);

    echo json_encode([
        'success' => true,
        'user' => $user
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>