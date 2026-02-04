<?php
header('Content-Type: application/json');
require_once '../../includes/middleware/auth.php';
require_once '../../config/database.php';

// Check authentication and admin role
$user_id = checkAuth();

// Verify user is admin
$stmt = $pdo->prepare("SELECT role FROM auth_accounts WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user || $user['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied. Admin only.']);
    exit;
}

// Check if file was uploaded
if (!isset($_FILES['profile_pic']) || $_FILES['profile_pic']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'No file uploaded or upload error occurred.']);
    exit;
}

$file = $_FILES['profile_pic'];

// Validate file size (max 5MB)
$maxSize = 5 * 1024 * 1024; // 5MB in bytes
if ($file['size'] > $maxSize) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'File size exceeds 5MB limit.']);
    exit;
}

// Validate file type
$allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

if (!in_array($mimeType, $allowedTypes)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid file type. Only JPG, PNG, and GIF are allowed.']);
    exit;
}

// Get file extension
$extension = '';
switch ($mimeType) {
    case 'image/jpeg':
    case 'image/jpg':
        $extension = 'jpg';
        break;
    case 'image/png':
        $extension = 'png';
        break;
    case 'image/gif':
        $extension = 'gif';
        break;
}

// Generate unique filename
$filename = 'admin_' . $user_id . '_' . time() . '.' . $extension;
$uploadDir = '../../public/assets/imgs/profiles/';
$uploadPath = $uploadDir . $filename;
$dbPath = '/public/assets/imgs/profiles/' . $filename;

// Create directory if it doesn't exist
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// Move uploaded file
if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to save uploaded file.']);
    exit;
}

// Delete old profile picture if it exists and is not the default
try {
    $stmt = $pdo->prepare("SELECT profile_pic FROM admins WHERE auth_id = ?");
    $stmt->execute([$user_id]);
    $oldPic = $stmt->fetchColumn();

    // Standardize check: ignore defaults and nulls
    $defaults = ['/public/assets/imgs/admin.png', 'public/assets/imgs/admin.png', '../../public/assets/imgs/admin.png'];

    if ($oldPic && !in_array($oldPic, $defaults)) {
        // Resolve absolute path for unlink
        $unlinkPath = $oldPic;
        if (strpos($oldPic, '/') === 0) {
            $unlinkPath = '../../' . substr($oldPic, 1);
        } elseif (strpos($oldPic, '../../') !== 0) {
            $unlinkPath = '../../' . $oldPic;
        } else {
            $unlinkPath = $oldPic;
        }

        if (file_exists($unlinkPath)) {
            unlink($unlinkPath);
        }
    }
} catch (Exception $e) {
    // Continue even if old file deletion fails
}

// Update database
try {
    $stmt = $pdo->prepare("UPDATE admins SET profile_pic = ?, updated_at = NOW() WHERE auth_id = ?");
    $stmt->execute([$dbPath, $user_id]);

    echo json_encode([
        'success' => true,
        'message' => 'Profile picture updated successfully.',
        'profile_pic' => $dbPath
    ]);
} catch (PDOException $e) {
    // Delete uploaded file if database update fails
    if (file_exists($uploadPath)) {
        unlink($uploadPath);
    }

    error_log("Profile pic update failed: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    error_log("General error in profile upload: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Internal server error: ' . $e->getMessage()]);
}
?>