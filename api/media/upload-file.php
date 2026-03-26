<?php

/**
 * Upload Media File API
 * Handles file uploads (images, videos, documents)
 */

header('Content-Type: application/json');
require_once '../../config/database.php';
require_once '../utils/notification-helper.php';
require_once '../../config/env-loader.php';

// Check authentication
// Check authentication
require_once '../../includes/middleware/auth.php';
$client_id = clientMiddleware();

if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'No file uploaded or upload error']);
    exit;
}

$folder_name = $_POST['folder_name'] ?? 'default';
$folder_id = $_POST['folder_id'] ?? null;
$file = $_FILES['file'];

// Resolve folder if ID is provided
if ($folder_id) {
    $stmt = $pdo->prepare("SELECT name FROM media_folders WHERE id = ? AND client_id = ? AND is_deleted = 0");
    $stmt->execute([$folder_id, $client_id]);
    $fetched_name = $stmt->fetchColumn();

    if ($fetched_name) {
        $folder_name = $fetched_name;
    } else {
        // Invalid folder ID, fall back to root/default
        $folder_id = null;
        $folder_name = 'default';
    }
} elseif ($folder_name !== 'default') {
    // Fallback to name-based lookup
    $stmt = $pdo->prepare("SELECT id FROM media_folders WHERE client_id = ? AND name = ? AND is_deleted = 0 LIMIT 1");
    $stmt->execute([$client_id, $folder_name]);
    $folder_id = $stmt->fetchColumn() ?: null;

    if (!$folder_id) {
        $stmt = $pdo->prepare("INSERT INTO media_folders (client_id, name) VALUES (?, ?)");
        $stmt->execute([$client_id, $folder_name]);
        $folder_id = $pdo->lastInsertId();
    }
}

// Validate file size
$max_size = $_ENV['UPLOAD_MAX_SIZE'] ?? 5242880; // 5MB default
if ($file['size'] > $max_size) {
    echo json_encode(['success' => false, 'message' => 'File size exceeds maximum allowed size']);
    exit;
}

// Get file info
$file_name = basename($file['name']);
$file_extension = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
$mime_type = mime_content_type($file['tmp_name']);
$file_size = $file['size'];

// Determine file type
$file_type = 'other';
if (in_array($file_extension, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'])) {
    $file_type = 'image';
} elseif (in_array($file_extension, ['mp4', 'mov', 'avi', 'mkv', 'webm'])) {
    $file_type = 'video';
} elseif ($file_extension === 'pdf') {
    $file_type = 'pdf';
} elseif (in_array($file_extension, ['doc', 'docx'])) {
    $file_type = 'word';
} elseif (in_array($file_extension, ['xls', 'xlsx'])) {
    $file_type = 'excel';
} elseif (in_array($file_extension, ['ppt', 'pptx'])) {
    $file_type = 'powerpoint';
} elseif (in_array($file_extension, ['zip', 'rar', '7z'])) {
    $file_type = 'archive';
}

try {
    // Create upload directory
    $upload_dir = "../../uploads/media/client_$client_id/$folder_name/";
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    // Generate unique file name
    $unique_name = uniqid('media_') . '.' . $file_extension;
    $target_path = $upload_dir . $unique_name;
    $db_path = "/uploads/media/client_$client_id/$folder_name/$unique_name";

    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $target_path)) {
        echo json_encode(['success' => false, 'message' => 'Failed to save file']);
        exit;
    }

    // Insert into database
    $stmt = $pdo->prepare("
        INSERT INTO media (client_id, folder_id, folder_name, file_name, file_extension, file_path, file_type, file_size, mime_type)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([$client_id, $folder_id, $folder_name, $file_name, $file_extension, $db_path, $file_type, $file_size, $mime_type]);

    $media_id = $pdo->lastInsertId();

    $auth_id = getAuthId();
    // The notification-helper.php is already required at the top of the file.
    // Calling the notification function after successful DB insertion.
    createMediaUploadedNotification($auth_id, $file_name, $folder_name);

    // Notify Admin
    $admin_id = getAdminUserId();
    if ($admin_id) {
        $admin_msg = "Client uploaded new media: '{$file_name}'";
        createNotification($admin_id, $admin_msg, 'media_uploaded', $auth_id, 'admin', 'client');
    }

    echo json_encode([
        'success' => true,
        'message' => 'File uploaded successfully',
        'media' => [
            'id' => $media_id,
            'file_name' => $file_name,
            'file_path' => $db_path,
            'file_type' => $file_type,
            'file_size' => $file_size
        ]
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
