<?php
// Protect output and silence warnings
if (!headers_sent()) {
    ob_start();
}
error_reporting(0);

header('Content-Type: application/json');
require_once '../../includes/middleware/auth.php';

// Lightweight short-circuit: if no session cookie and no Authorization header, return 401 without opening DB
$hasSessionCookie = isset($_COOKIE['EVENTRA_ADMIN_SESS']) || isset($_COOKIE['EVENTRA_CLIENT_SESS']) || isset($_COOKIE['EVENTRA_USER_SESS']);
$hasAuthHeader = !empty($_SERVER['HTTP_AUTHORIZATION']) || !empty($_SERVER['REDIRECT_HTTP_AUTHORIZATION']) || !empty($_SERVER['HTTP_ACCESS_TOKEN']);

if (!$hasSessionCookie && !$hasAuthHeader) {
    http_response_code(401);
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Unauthorized. Please log in.']);
    exit;
}

// Perform auth check (may open DB lazily)
checkAuth('admin');
$admin_auth_id = getAuthId();
$role = $_SESSION['role'] ?? 'admin';

// Check if user is admin
if ($role !== 'admin') {
    http_response_code(403);
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Access denied. Admin only.']);
    exit;
}

try {
    // Lazy-load PDO
    $pdo = getPDO();

    // Auto-delete notifications older than 2 days (non-critical)
    try {
        $pdo->prepare("DELETE FROM notifications WHERE created_at < DATE_SUB(NOW(), INTERVAL 2 DAY)")->execute();
    } catch (Exception $e) {
        // ignore cleanup errors
    }

    // Fetch notifications for admin, ordered by newest first
    $stmt = $pdo->prepare("
        SELECT n.*, c.business_name as client_name, c.profile_pic as client_profile_pic
        FROM notifications n
        LEFT JOIN clients c ON n.sender_auth_id = c.client_auth_id AND n.sender_role = 'client'
        WHERE n.recipient_auth_id = ?
        ORDER BY n.created_at DESC
        LIMIT 10
    ");
    $stmt->execute([$admin_auth_id]);
    $notifications = $stmt->fetchAll();

    // Get unread count
    $stmt = $pdo->prepare("SELECT COUNT(*) as unread_count FROM notifications WHERE recipient_auth_id = ? AND is_read = 0");
    $stmt->execute([$admin_auth_id]);
    $unread_result = $stmt->fetch();
    $unread_count = $unread_result['unread_count'];

    ob_clean();
    echo json_encode([
        'success' => true,
        'notifications' => $notifications,
        'unread_count' => (int) $unread_count
    ]);
} catch (PDOException $e) {
    error_log('Admin notifications DB error: ' . $e->getMessage());
    http_response_code(503);
    ob_clean();
    echo json_encode([
        'success' => false,
        'message' => 'Service temporarily unavailable.'
    ]);
}
