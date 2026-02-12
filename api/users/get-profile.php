<?php
/**
 * Get User Profile API
 * Retrieves user or client profile information
 */
header('Content-Type: application/json');
require_once '../../config/database.php';
require_once '../../includes/middleware/auth.php';

// Check authentication
$current_user_id = checkAuth();

$user_id = $_GET['user_id'] ?? $current_user_id;

// Check if requesting own profile or admin
if ($user_id != $current_user_id && $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

try {
    $role = $_SESSION['role'] ?? 'user';
    $table = 'users';
    if ($role === 'client')
        $table = 'clients';
    if ($role === 'admin')
        $table = 'admins';

    // Join with auth_accounts to get the role
    $stmt = $pdo->prepare("
        SELECT p.*, a.id, a.role, a.email 
        FROM auth_accounts a 
        LEFT JOIN $table p ON a.id = p.auth_id 
        WHERE a.id = ?
    ");
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