<?php
/**
 * API: Upload Media
 * Handles file uploads for events and media gallery
 */
header('Content-Type: application/json');
require_once '../../config/database.php';
require_once '../utils/notification-helper.php';

// Check authentication
require_once '../../includes/middleware/auth.php';
$client_id = clientMiddleware();

if (!isset($_FILES['files'])) {
    echo json_encode(['success' => false, 'message' => 'No files uploaded']);
    exit;
}

try {
    $pdo->beginTransaction();
    $folder_name = $_POST['folder_name'] ?? 'default';
    $folder_id = $_POST['folder_id'] ?? null;

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
        // Fallback to name-based lookup (legacy/robustness)
        $stmt = $pdo->prepare("SELECT id FROM media_folders WHERE client_id = ? AND name = ? AND is_deleted = 0 LIMIT 1");
        $stmt->execute([$client_id, $folder_name]);
        $folder_id = $stmt->fetchColumn() ?: null;

        // Auto-create if not exists (only for name-based uploads)
        if (!$folder_id) {
            $stmt = $pdo->prepare("INSERT INTO media_folders (client_id, name) VALUES (?, ?)");
            $stmt->execute([$client_id, $folder_name]);
            $folder_id = $pdo->lastInsertId();
        }
    }

    // Create upload directory if not exists
    $uploadDir = '../../uploads/media/client_' . $client_id . '/' . ($folder_name !== 'default' ? $folder_name . '/' : '');
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $uploadedFiles = [];
    $files = $_FILES['files'];

    // Handle multiple files
    $fileCount = is_array($files['name']) ? count($files['name']) : 1;

    for ($i = 0; $i < $fileCount; $i++) {
        $fileName = is_array($files['name']) ? $files['name'][$i] : $files['name'];
        $fileTmpName = is_array($files['tmp_name']) ? $files['tmp_name'][$i] : $files['tmp_name'];
        $fileSize = is_array($files['size']) ? $files['size'][$i] : $files['size'];
        $fileType = is_array($files['type']) ? $files['type'][$i] : $files['type'];

        // Generate unique filename
        $fileExtension = pathinfo($fileName, PATHINFO_EXTENSION);
        $uniqueFileName = uniqid() . '_' . time() . '.' . $fileExtension;
        $filePath = $uploadDir . $uniqueFileName;

        // Move uploaded file
        if (move_uploaded_file($fileTmpName, $filePath)) {
            // Determine file type
            $fileExtensionLower = strtolower($fileExtension);
            $fileEnum = 'other';
            if (in_array($fileExtensionLower, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'])) {
                $fileEnum = 'image';
            } elseif (in_array($fileExtensionLower, ['mp4', 'mov', 'avi', 'mkv', 'webm'])) {
                $fileEnum = 'video';
            } elseif ($fileExtensionLower === 'pdf') {
                $fileEnum = 'pdf';
            } elseif (in_array($fileExtensionLower, ['doc', 'docx'])) {
                $fileEnum = 'word';
            } elseif (in_array($fileExtensionLower, ['xls', 'xlsx'])) {
                $fileEnum = 'excel';
            } elseif (in_array($fileExtensionLower, ['ppt', 'pptx'])) {
                $fileEnum = 'powerpoint';
            } elseif (in_array($fileExtensionLower, ['zip', 'rar', '7z'])) {
                $fileEnum = 'archive';
            }

            // Save to database
            $stmt = $pdo->prepare("
                INSERT INTO media (client_id, folder_id, folder_name, file_name, file_extension, file_path, file_type, file_size, mime_type)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $dbFilePath = '/uploads/media/client_' . $client_id . '/' . ($folder_name !== 'default' ? $folder_name . '/' : '') . $uniqueFileName;

            $stmt->execute([
                $client_id,
                $folder_id,
                $folder_name,
                $fileName,
                $fileExtensionLower,
                $dbFilePath,
                $fileEnum,
                $fileSize,
                $fileType
            ]);

            $uploadedFiles[] = [
                'id' => $pdo->lastInsertId(),
                'name' => $fileName,
                'path' => $dbFilePath
            ];
        }
    }

    if (count($uploadedFiles) > 0) {
        $msg_filename = $uploadedFiles[0]['name'];
        if (count($uploadedFiles) > 1) {
            $msg_filename .= " and " . (count($uploadedFiles) - 1) . " others";
        }
        // The notification-helper.php is already required at the top of the file.
        createMediaUploadedNotification($client_id, $msg_filename, $folder_name);
    }

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => count($uploadedFiles) . ' file(s) uploaded successfully',
        'files' => $uploadedFiles
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'message' => 'Upload error: ' . $e->getMessage()]);
}
