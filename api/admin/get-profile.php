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
            a.id as auth_id,
            adm.id as admin_id,
            adm.name,
            a.email,
            adm.profile_pic,
            a.is_active,
            adm.created_at,
            adm.updated_at
        FROM admins adm
        JOIN auth_accounts a ON adm.admin_auth_id = a.id
        WHERE adm.admin_auth_id = ?
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
