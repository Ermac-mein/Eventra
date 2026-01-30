/**
* API: Upload Media
* Handles file uploads for events and media gallery
*/
<?php
header('Content-Type: application/json');
require_once '../../config/database.php';

// Check authentication
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'client') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$client_id = $_SESSION['user_id'];

if (!isset($_FILES['files'])) {
    echo json_encode(['success' => false, 'message' => 'No files uploaded']);
    exit;
}

try {
    // Create upload directory if not exists
    $uploadDir = '../../uploads/media/' . $client_id . '/';
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
            // Save to database
            $stmt = $pdo->prepare("
                INSERT INTO media (client_id, name, file_path, file_type, file_size, type)
                VALUES (?, ?, ?, ?, ?, 'file')
            ");
            $stmt->execute([
                $client_id,
                $fileName,
                '/uploads/media/' . $client_id . '/' . $uniqueFileName,
                $fileType,
                $fileSize
            ]);

            $uploadedFiles[] = [
                'id' => $pdo->lastInsertId(),
                'name' => $fileName,
                'path' => '/uploads/media/' . $client_id . '/' . $uniqueFileName
            ];
        }
    }

    echo json_encode([
        'success' => true,
        'message' => count($uploadedFiles) . ' file(s) uploaded successfully',
        'files' => $uploadedFiles
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Upload error: ' . $e->getMessage()]);
}
?>