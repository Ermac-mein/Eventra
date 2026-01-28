<?php
header('Content-Type: application/json');
require_once '../../includes/middleware/auth.php';
require_once '../../config/database.php';

$user_id = checkAuth(); // Ensure user is logged in

// Check if user is admin
if ($_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied. Admin only.']);
    exit;
}

try {
    // Fetch admin profile data
    $stmt = $pdo->prepare("
        SELECT 
            id,
            name,
            email,
            profile_pic,
            status,
            created_at,
            updated_at
        FROM users 
        WHERE id = ? AND role = 'admin'
    ");
    $stmt->execute([$user_id]);
    $admin = $stmt->fetch();

    if ($admin) {
        echo json_encode([
            'success' => true,
            'admin' => $admin
        ]);
    } else {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Admin profile not found'
        ]);
    }

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>