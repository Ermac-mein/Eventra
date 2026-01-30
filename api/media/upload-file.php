<?php
/**
 * Upload Media File API
 * Handles file uploads (images, videos, documents)
 */
header('Content-Type: application/json');
require_once '../../config/database.php';
require_once '../../config/env-loader.php';

// Check authentication
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'client') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized. Client access required.']);
    exit;
}

$client_id = $_SESSION['user_id'];

if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'No file uploaded or upload error']);
    exit;
}

$folder_name = $_POST['folder_name'] ?? 'default';
$file = $_FILES['file'];

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
if (in_array($file_extension, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
    $file_type = 'image';
} elseif (in_array($file_extension, ['mp4', 'avi', 'mov', 'wmv', 'flv', 'webm'])) {
    $file_type = 'video';
} elseif (in_array($file_extension, ['pdf', 'doc', 'docx', 'xls', 'xlsx'])) {
    $file_type = 'document';
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
        INSERT INTO media (client_id, folder_name, file_name, file_path, file_type, file_size, mime_type)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([$client_id, $folder_name, $file_name, $db_path, $file_type, $file_size, $mime_type]);

    $media_id = $pdo->lastInsertId();

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
?>