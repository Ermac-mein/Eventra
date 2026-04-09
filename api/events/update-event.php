<?php

/**
 * Update Event API
 * Updates event details (only for draft and scheduled events)
 */

header('Content-Type: application/json');
require_once '../../includes/middleware/auth.php';

/**
 * Compress event image for storage
 */
function compressEventImage($filePath, $extension) {
    if (!extension_loaded('gd')) {
        return $filePath;
    }

    try {
        $maxWidth = 1200;
        $quality = 80;
        
        $image = null;
        switch ($extension) {
            case 'jpg':
            case 'jpeg':
                $image = imagecreatefromjpeg($filePath);
                break;
            case 'png':
                $image = imagecreatefrompng($filePath);
                break;
            case 'webp':
                $image = imagecreatefromwebp($filePath);
                break;
        }

        if (!$image) return $filePath;

        $width = imagesx($image);
        $height = imagesy($image);

        if ($width > $maxWidth) {
            $ratio = $maxWidth / $width;
            $newWidth = $maxWidth;
            $newHeight = (int)($height * $ratio);

            $resized = imagecreatetruecolor($newWidth, $newHeight);
            imagecopyresampled($resized, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
            imagedestroy($image);
            $image = $resized;
        }

        $tempPath = $filePath . '.tmp';
        switch ($extension) {
            case 'jpg':
            case 'jpeg':
                imagejpeg($image, $tempPath, $quality);
                break;
            case 'png':
                imagepng($image, $tempPath, 6);
                break;
            case 'webp':
                imagewebp($image, $tempPath, $quality);
                break;
        }

        imagedestroy($image);

        if (filesize($tempPath) < filesize($filePath)) {
            unlink($filePath);
            rename($tempPath, $filePath);
        } else {
            unlink($tempPath);
        }

        chmod($filePath, 0644);
        return $filePath;
    } catch (Exception $e) {
        return $filePath;
    }
}

// Check authentication - require either client or admin role
$role = $_SESSION['role'] ?? null;

if ($role === 'client') {
    $user_id = checkAuth('client');
} elseif ($role === 'admin') {
    $user_id = checkAuth('admin');
} else {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$event_id = $_POST['event_id'] ?? null;

if (!$event_id) {
    echo json_encode(['success' => false, 'message' => 'Event ID is required']);
    exit;
}

try {
    // Use user_id (which is client_id from checkAuth('client'))
    $real_client_id = $user_id;

    // Get current event details
    $stmt = $pdo->prepare("SELECT * FROM events WHERE id = ?");
    $stmt->execute([$event_id]);
    $event = $stmt->fetch();

    if (!$event) {
        echo json_encode(['success' => false, 'message' => 'Event not found']);
        exit;
    }

    // Check if user owns the event or is admin
    if ($role !== 'admin' && $event['client_id'] != $real_client_id) {
        error_log("[Update Event Debug] Role: $role | Event Client ID: " . $event['client_id'] . " | User Real Client ID: " . $real_client_id);
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'You do not have permission to update this event']);
        exit;
    }

    // LOCKING: Prevent edit if there are payments/attendees
    if ($event['attendee_count'] > 0) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'This event is locked because tickets have already been sold. Please contact support for critical changes.']);
        exit;
    }

    // Handle image upload if provided using standardized path
    $image_path = $event['image_path']; // Keep existing image by default
    if (isset($_FILES['event_image']) && $_FILES['event_image']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = "../../uploads/events/";

        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        $file_extension = strtolower(pathinfo($_FILES['event_image']['name'], PATHINFO_EXTENSION));
        $new_filename = uniqid('event_') . '.' . $file_extension;
        $upload_path = $upload_dir . $new_filename;

        if (move_uploaded_file($_FILES['event_image']['tmp_name'], $upload_path)) {
            // Compress image
            $upload_path = compressEventImage($upload_path, $file_extension);
            $image_path = "/uploads/events/" . basename($upload_path);

            // Delete old image if it exists
            if ($event['image_path']) {
                $old_full_path = __DIR__ . '/../../' . ltrim($event['image_path'], '/');
                if (file_exists($old_full_path) && !is_dir($old_full_path)) {
                    unlink($old_full_path);
                }
            }

            // Register the image in the media table
            try {
                $file_size = filesize($upload_path);
                $mime_type = mime_content_type($upload_path);

                $folder_name = 'Event Images';
                $stmt = $pdo->prepare("SELECT id FROM media_folders WHERE client_id = ? AND name = ? AND is_deleted = 0 LIMIT 1");
                $stmt->execute([$event['client_id'], $folder_name]);
                $folder_id = $stmt->fetchColumn() ?: null;

                if (!$folder_id) {
                    $stmt = $pdo->prepare("INSERT INTO media_folders (client_id, name) VALUES (?, ?)");
                    $stmt->execute([$event['client_id'], $folder_name]);
                    $folder_id = $pdo->lastInsertId();
                }

                $media_stmt = $pdo->prepare("
                    INSERT INTO media (client_id, folder_id, folder_name, file_name, file_extension, file_path, file_type, file_size, mime_type)
                    VALUES (?, ?, ?, ?, ?, ?, 'image', ?, ?)
                ");
                $media_stmt->execute([
                    $event['client_id'],
                    $folder_id,
                    $folder_name,
                    $_FILES['event_image']['name'],
                    $file_extension,
                    $image_path,
                    $file_size,
                    $mime_type
                ]);
            } catch (Throwable $media_err) {
                // Log media registration error but don't fail the entire update
                error_log("[Update Event Media Register Error] " . $media_err->getMessage());
            }
        } else {
            throw new Exception("Failed to move uploaded file to $upload_path. Check directory permissions.");
        }
    } elseif (isset($_FILES['event_image']) && $_FILES['event_image']['error'] !== UPLOAD_ERR_NO_FILE) {
        $error_code = $_FILES['event_image']['error'];
        $error_message = "Image upload failed (Error: $error_code)";

        if ($error_code === UPLOAD_ERR_INI_SIZE || $error_code === UPLOAD_ERR_FORM_SIZE) {
            $max_size = ini_get('upload_max_filesize');
            $error_message = "The uploaded image is too large. Your server's current limit is $max_size. Please upload a smaller image or increase 'upload_max_filesize' in your PHP configuration.";
        }

        throw new Exception($error_message);
    }

    // Validation
    $required_fields = ['event_name', 'event_type', 'event_date', 'event_time', 'price', 'status', 'address', 'phone_contact_1'];
    foreach ($required_fields as $field) {
        if (!isset($_POST[$field]) || trim($_POST[$field]) === '') {
            echo json_encode(['success' => false, 'message' => "Field '$field' is required"]);
            exit;
        }
    }

    // Update event
    $sql = "UPDATE events SET 
            event_name = ?,
            event_type = ?,
            priority = ?,
            event_date = ?,
            event_time = ?,
            price = ?,
            regular_price = ?,
            vip_price = ?,
            regular_quantity = ?,
            vip_quantity = ?,
            status = ?,
            description = ?,
            state = ?,
            visibility = ?,
            event_visibility = ?,
            ticket_type_mode = ?,
            address = ?,
            phone_contact_1 = ?,
            phone_contact_2 = ?,
            image_path = ?,
            category = ?,
            updated_at = NOW()
            WHERE id = ?";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $_POST['event_name'],
        $_POST['event_type'],
        $_POST['priority'] ?? 'nearby',
        $_POST['event_date'],
        $_POST['event_time'],
        $_POST['price'],
        $_POST['regular_price'] ?? 0,
        $_POST['vip_price'] ?? 0,
        $_POST['regular_quantity'] ?? null,
        $_POST['vip_quantity'] ?? null,
        $_POST['status'],
        $_POST['description'],
        $_POST['state'],
        $_POST['visibility'] ?? 'all states',
        $_POST['event_visibility'] ?? 'public',
        $_POST['ticket_type_mode'] ?? 'both',
        $_POST['address'],
        $_POST['phone_contact_1'],
        $_POST['phone_contact_2'] ?? null,
        $image_path,
        $_POST['category'] ?? $_POST['event_type'],
        $event_id
    ]);

    $message = "Event '{$_POST['event_name']}' has been updated";
    $auth_id = $_SESSION['auth_id'];
    $client_auth_id = $event['client_auth_id'] ?? null;
    if (!$client_auth_id) {
        $stmt = $pdo->prepare("SELECT client_auth_id FROM clients WHERE id = ?");
        $stmt->execute([$event['client_id']]);
        $client_auth_id = $stmt->fetchColumn();
    }

    createNotification($client_auth_id, $message, 'event_updated', $auth_id, 'client', ($role === 'admin' ? 'admin' : 'client'));

    // Notify Admin as well
    $admin_id = getAdminUserId();
    if ($admin_id && $auth_id != $admin_id) {
        $name = $_POST['event_name'];
        $admin_message = "Event '{$name}' has been updated" . ($role === 'admin' ? " by an administrator." : " by organizer.");
        createNotification($admin_id, $admin_message, 'event_updated', $auth_id, 'admin', ($role === 'admin' ? 'admin' : 'client'));
    }

    echo json_encode([
        'success' => true,
        'message' => 'Event updated successfully'
    ]);
} catch (Throwable $e) {
    error_log("[Update Event Global Error] " . $e->getMessage() . "\n" . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Internal server error: ' . $e->getMessage()]);
}
