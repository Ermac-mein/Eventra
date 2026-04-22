<?php

/**
 * Update Event API
 * Updates event details (only for draft and scheduled events)
 */

header('Content-Type: application/json');
require_once '../../config/database.php';
require_once '../../includes/middleware/auth.php';
require_once '../utils/notification-helper.php';

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

$headers = getallheaders();
$headersLower = array_change_key_case($headers, CASE_LOWER);
$portal = $headersLower['x-eventra-portal'] ?? null;

if ($portal === 'client') {
    $user_id = checkAuth('client');
    $role = 'client';
} elseif ($portal === 'admin') {
    $user_id = checkAuth('admin');
    $role = 'admin';
} else {
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

    // LOCKING: Prevent client edit if tickets sold; admins can always edit
    if ($role !== 'admin' && $event['attendee_count'] > 0) {
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

                $media_stmt = $pdo->prepare("
                    INSERT INTO media (client_id, folder_id, folder_name, file_name, file_extension, file_path, file_type, file_size, mime_type)
                    VALUES (?, NULL, 'default', ?, ?, ?, 'image', ?, ?)
                ");
                $media_stmt->execute([
                    $event['client_id'],
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
    $scheduled_publish_time = $_POST['scheduled_publish_time'] ?? null;
    $status = $_POST['status'] ?? ($event['status'] ?? 'draft');

    if ($status === 'scheduled') {
        if (empty($scheduled_publish_time)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Scheduled publish time is required for scheduled events.']);
            exit;
        }
        if (strtotime($scheduled_publish_time) <= time()) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Scheduled publish time must be in the future.']);
            exit;
        }
    } elseif (empty($scheduled_publish_time)) {
        $scheduled_publish_time = null;
    }

    // Validation
    $required_fields = ['event_name', 'event_type', 'event_date', 'event_time', 'price', 'status', 'address', 'phone_contact_1'];
    foreach ($required_fields as $field) {
        if (!isset($_POST[$field]) || trim($_POST[$field]) === '') {
            echo json_encode(['success' => false, 'message' => "Field '$field' is required"]);
            exit;
        }
    }

    // Date cap: event_date must be within 365 days from today
    if (!empty($_POST['event_date']) && strtotime($_POST['event_date']) > strtotime('+365 days')) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Event date cannot be more than 365 days from today.']);
        exit;
    }

    // Recalculate ticket_count / total_tickets when quantities change
    $new_regular_qty = !empty($_POST['regular_quantity']) ? intval($_POST['regular_quantity']) : null;
    $new_vip_qty     = !empty($_POST['vip_quantity'])     ? intval($_POST['vip_quantity'])     : null;
    $new_total_tickets = null;
    $new_ticket_count  = null;
    if ($new_regular_qty !== null || $new_vip_qty !== null) {
        $new_total_tickets = ($new_regular_qty ?? 0) + ($new_vip_qty ?? 0);
        // Preserve tickets already sold
        $already_sold = (int)($event['sales_count'] ?? $event['attendee_count'] ?? 0);
        $new_ticket_count = max(0, $new_total_tickets - $already_sold);
    }

    // Admin-only fields
    $new_admin_status = ($role === 'admin' && isset($_POST['admin_status']))
        ? $_POST['admin_status']
        : ($event['admin_status'] ?? 'pending');
    $new_is_boosted = ($role === 'admin' && isset($_POST['is_boosted']))
        ? (int)$_POST['is_boosted']
        : (int)($event['is_boosted'] ?? 0);

    // Build UPDATE (priority column intentionally omitted — deprecated)
    $sql = "UPDATE events SET
            event_name = ?,
            event_type = ?,
            event_date = ?,
            event_time = ?,
            price = ?,
            regular_quantity = ?,
            vip_quantity = ?,
            status = ?,
            description = ?,
            state = ?,
            visibility = ?,
            event_visibility = ?,
            address = ?,
            phone_contact_1 = ?,
            phone_contact_2 = ?,
            image_path = ?,
            category = ?,
            admin_status = ?,
            is_boosted = ?,
            total_tickets = COALESCE(?, total_tickets),
            ticket_count  = COALESCE(?, ticket_count),
            scheduled_publish_time = ?,
            updated_at = NOW()
            WHERE id = ?";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $_POST['event_name'],
        $_POST['event_type'],
        $_POST['event_date'],
        $_POST['event_time'],
        $_POST['price'],
        $new_regular_qty,
        $new_vip_qty,
        $_POST['status'],
        $_POST['description'],
        $_POST['state'],
        $_POST['visibility'] ?? 'all states',
        $_POST['event_visibility'] ?? 'public',
        $_POST['address'],
        $_POST['phone_contact_1'],
        $_POST['phone_contact_2'] ?? null,
        $image_path,
        $_POST['category'] ?? $_POST['event_type'],
        $new_admin_status,
        $new_is_boosted,
        $new_total_tickets,
        $new_ticket_count,
        $scheduled_publish_time,
        $event_id
    ]);

    try {
        $message = "Event '{$_POST['event_name']}' has been updated";
        $auth_id = getAuthId();
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
    } catch (Throwable $notif_err) {
        error_log("[Update Event Notification Error] " . $notif_err->getMessage());
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
