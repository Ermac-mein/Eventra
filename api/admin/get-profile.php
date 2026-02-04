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
    // Fetch admin profile data from auth_accounts and joined admins table
    $stmt = $pdo->prepare("
        SELECT 
            ad.id,
            ad.name,
            a.email,
            ad.profile_pic,
            a.is_active,
            ad.created_at,
            ad.updated_at
        FROM auth_accounts a
        JOIN admins ad ON a.id = ad.auth_id
        WHERE a.id = ?
    ");
    $stmt->execute([$user_id]);
    $admin = $stmt->fetch();

    if ($admin) {
        // Map is_active back to 'active' for frontend consistency if needed, 
        // but often 1/0 is fine or 'active'/'inactive'
        $admin['status'] = $admin['is_active'] ? 'active' : 'inactive';

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