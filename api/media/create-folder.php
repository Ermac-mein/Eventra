<?php
/**
 * Create Media Folder API
 * Creates a new folder for organizing media files
 */
header('Content-Type: application/json');
require_once '../../config/database.php';
require_once '../utils/notification-helper.php';

// Check authentication
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'client') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized. Client access required.']);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);
$folder_name = $data['folder_name'] ?? '';
$user_id = $_SESSION['user_id'];

// Get the actual client_id from clients table
$stmt = $pdo->prepare("SELECT id FROM clients WHERE client_auth_id = ?");
$stmt->execute([$user_id]);
$client_id = $stmt->fetchColumn();

if (!$client_id) {
    echo json_encode(['success' => false, 'message' => 'Client profile not found']);
    exit;
}

if (empty($folder_name)) {
    echo json_encode(['success' => false, 'message' => 'Folder name is required']);
    exit;
}

try {
    // Create physical folder
    $upload_dir = "../../uploads/media/client_$client_id/$folder_name/";
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    // Insert into database
    $stmt = $pdo->prepare("INSERT INTO media_folders (client_id, name) VALUES (?, ?)");
    $stmt->execute([$client_id, $folder_name]);
    $folder_id = $pdo->lastInsertId();

    // Trigger notification
    createFolderCreatedNotification($client_id, $folder_name);

    echo json_encode([
        'success' => true,
        'message' => 'Folder created successfully',
        'folder_name' => $folder_name,
        'folder_id' => $folder_id
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error creating folder: ' . $e->getMessage()]);
}
