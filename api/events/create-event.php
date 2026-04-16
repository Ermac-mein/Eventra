<?php

/**
 * Create Event API
 * Handles event creation with all fields including scheduling, priority, tags, and links
 */

header('Content-Type: application/json');
require_once '../../config/database.php';
require_once '../../config/env-loader.php';

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

// Check authentication and role
$client_id = checkAuth('client');

try {
    // 1. Resolve actual Client name and info from clients table
    $stmt = $pdo->prepare("SELECT id, name, verification_status FROM clients WHERE id = ?");
    $stmt->execute([$client_id]);
    $client_data = $stmt->fetch();

    if (!$client_data) {
        throw new Exception("Client profile not found for this account.");
    }

    if ($client_data['verification_status'] !== 'verified') {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Unauthorized: Your account must be verified by an administrator before you can create events.']);
        exit;
    }

    $real_client_id = $client_data['id'];
    $raw_client_name = $client_data['name'] ?? 'client';
    $client_name = strtolower(str_replace(' ', '-', preg_replace('/[^A-Za-z0-9 ]/', '', $raw_client_name)));
    // ─────────────────────────────────────────────────────────────────────────

    // 2. Handle file upload if present using standardized path
    $image_path = null;
    if (isset($_FILES['event_image']) && $_FILES['event_image']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = "../../uploads/events/";

        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        $file_extension = strtolower(pathinfo($_FILES['event_image']['name'], PATHINFO_EXTENSION));
        $file_name = uniqid('event_') . '.' . $file_extension;
        $target_path = $upload_dir . $file_name;

        if (move_uploaded_file($_FILES['event_image']['tmp_name'], $target_path)) {
            // Compress image before storing
            $target_path = compressEventImage($target_path, $file_extension);
            $image_path = "/uploads/events/" . basename($target_path);

            // Register in media table (Canonical folder: "Event Images")
            try {
                $file_size = filesize($target_path);
                $mime_type = mime_content_type($target_path);

                $folder_name = 'Event Images';
                $stmt = $pdo->prepare("SELECT id FROM media_folders WHERE client_id = ? AND name = ? AND is_deleted = 0 LIMIT 1");
                $stmt->execute([$real_client_id, $folder_name]);
                $folder_id = $stmt->fetchColumn() ?: null;

                if (!$folder_id) {
                    $stmt = $pdo->prepare("INSERT INTO media_folders (client_id, name) VALUES (?, ?)");
                    $stmt->execute([$real_client_id, $folder_name]);
                    $folder_id = $pdo->lastInsertId();
                }

                $media_stmt = $pdo->prepare("
                    INSERT INTO media (client_id, folder_id, folder_name, file_name, file_extension, file_path, file_type, file_size, mime_type)
                    VALUES (?, ?, ?, ?, ?, ?, 'image', ?, ?)
                ");
                $media_stmt->execute([
                    $real_client_id,
                    $folder_id,
                    $folder_name,
                    $_FILES['event_image']['name'],
                    $file_extension,
                    $image_path,
                    $file_size,
                    $mime_type
                ]);
            } catch (Throwable $media_err) {
                error_log("[Create Event Media Register Error] " . $media_err->getMessage());
            }
        } else {
            throw new Exception("Failed to move uploaded file. Check directory permissions.");
        }
    } elseif (isset($_FILES['event_image']) && $_FILES['event_image']['error'] !== UPLOAD_ERR_NO_FILE) {
        $upload_error = $_FILES['event_image']['error'];
        $error_msgs = [
            UPLOAD_ERR_INI_SIZE => "File is too large. Server limit is " . ini_get('upload_max_filesize') . ".",
            UPLOAD_ERR_FORM_SIZE => "File is too large (exceeds form limit).",
            UPLOAD_ERR_PARTIAL => "File was only partially uploaded.",
            UPLOAD_ERR_NO_TMP_DIR => "Missing a temporary folder.",
            UPLOAD_ERR_CANT_WRITE => "Failed to write file to disk.",
            UPLOAD_ERR_EXTENSION => "A PHP extension stopped the file upload."
        ];
        $msg = $error_msgs[$upload_error] ?? "Unknown upload error code: $upload_error";
        throw new Exception("Image upload failed: " . $msg);
    }

    // 3. Get POST data
    require_once '../utils/id-generator.php';
    $custom_id = generateEventId($pdo);

    $event_name = $_POST['event_name'] ?? '';
    $description = $_POST['description'] ?? '';
    $event_type = $_POST['event_type'] ?? '';
    $event_date = $_POST['event_date'] ?? '';
    $event_time = $_POST['event_time'] ?? '';
    $phone_contact_1 = $_POST['phone_contact_1'] ?? '';
    $phone_contact_2 = !empty($_POST['phone_contact_2']) ? $_POST['phone_contact_2'] : null;
    $state = $_POST['state'] ?? '';
    $address = $_POST['address'] ?? '';
    $visibility = $_POST['visibility'] ?? 'all states';
    $event_visibility = $_POST['event_visibility'] ?? 'public'; // public or private
    $price = $_POST['price'] ?? 0.00;

    // New pricing fields
    $ticket_type = $_POST['ticket_type'] ?? 'regular';
    $ticket_type_mode = $_POST['ticket_type_mode'] ?? 'both';
    $regular_price = !empty($_POST['regular_price']) ? floatval($_POST['regular_price']) : 0.00;
    $vip_price = !empty($_POST['vip_price']) ? floatval($_POST['vip_price']) : 0.00;
    $regular_quantity = !empty($_POST['regular_quantity']) ? intval($_POST['regular_quantity']) : null;
    $vip_quantity = !empty($_POST['vip_quantity']) ? intval($_POST['vip_quantity']) : null;

    // Compute ticket_count and total_tickets from submitted quantities
    $total_tickets = null;
    if ($regular_quantity !== null || $vip_quantity !== null) {
        $total_tickets = ($regular_quantity ?? 0) + ($vip_quantity ?? 0);
    } elseif (!empty($_POST['max_capacity'])) {
        $total_tickets = intval($_POST['max_capacity']);
    }
    $ticket_count = $total_tickets; // Start at full capacity on creation

    $status = $_POST['status'] ?? 'draft';
    $scheduled_publish_time = !empty($_POST['scheduled_publish_time'])
        ? $_POST['scheduled_publish_time']
        : date('Y-m-d H:i:s');

    // Date cap: event_date must be within 365 days from today
    if (!empty($event_date) && strtotime($event_date) > strtotime('+365 days')) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Event date cannot be more than 365 days from today.']);
        exit;
    }

    // Validate required fields (image optional if already provided as URL)
    if (
        empty($event_name) || empty($description) || empty($event_type) ||
        empty($event_date) || empty($event_time) || empty($phone_contact_1) ||
        empty($state) || empty($address) || empty($image_path)
    ) {
        http_response_code(400);
        $missing = [];
        if (empty($event_name)) {
            $missing[] = 'Event Name';
        }
        if (empty($description)) {
            $missing[] = 'Description';
        }
        if (empty($event_type)) {
            $missing[] = 'Category';
        }
        if (empty($event_date)) {
            $missing[] = 'Date';
        }
        if (empty($event_time)) {
            $missing[] = 'Time';
        }
        if (empty($phone_contact_1)) {
            $missing[] = 'Primary Contact';
        }
        if (empty($state)) {
            $missing[] = 'State';
        }
        if (empty($address)) {
            $missing[] = 'Address';
        }
        if (empty($image_path)) {
            $missing[] = 'Event Image (Banner)';
        }

        echo json_encode([
            'success' => false,
            'message' => 'All required fields must be filled: ' . implode(', ', $missing)
        ]);
        exit;
    }

    // Auto-generate tag from event name (lowercase, hyphenated)
    $tag = strtolower(str_replace(' ', '-', preg_replace('/[^A-Za-z0-9 ]/', '', $event_name)));

    $base_url = $_ENV['APP_URL'] ?? 'http://localhost:8000';
    $external_link = $base_url . '/public/pages/event-details.html?event=' . $tag . '&client=' . $client_name;

    // Insert event with enriched columns (priority deprecated — admin_status drives moderation)
    $stmt = $pdo->prepare("
        INSERT INTO events (
            client_id, custom_id, event_name, description, event_type, event_date, event_time,
            phone_contact_1, phone_contact_2, state, address, visibility, tag,
            external_link, price, regular_price, vip_price, regular_quantity, vip_quantity,
            image_path, status, scheduled_publish_time, category, event_visibility, ticket_type_mode,
            ticket_count, total_tickets, sales_count, view_count, is_boosted, admin_status
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $stmt->execute([
        $real_client_id,
        $custom_id,
        $event_name,
        $description,
        $event_type,
        $event_date,
        $event_time,
        $phone_contact_1,
        $phone_contact_2,
        $state,
        $address,
        $visibility,
        $tag,
        $external_link,
        $price,
        $regular_price,
        $vip_price,
        $regular_quantity,
        $vip_quantity,
        $image_path,
        $status,
        $scheduled_publish_time,
        $event_type,       // category mirrors event_type
        $event_visibility,
        $ticket_type_mode,
        $ticket_count,     // atomic stock
        $total_tickets,    // original capacity
        0,                 // sales_count starts at 0
        0,                 // view_count starts at 0
        0,                 // is_boosted — always false on client create
        'pending'          // admin_status — awaits approval
    ]);

    $event_id = $pdo->lastInsertId();

    // Create notifications using helper functions
    require_once '../utils/notification-helper.php';

    $auth_id = getAuthId();

    // Notify admin about event creation
    $admin_id = getAdminUserId();
    if ($admin_id) {
        $display_name = $client_data['name'] ?? 'Client';
        $admin_message = "New event created: '{$event_name}' by {$display_name} - Status: {$status}";
        createNotification($admin_id, $admin_message, 'event_created', $auth_id, 'admin', 'client');
    }

    // Notify client
    if ($status === 'scheduled' && $scheduled_publish_time) {
        createEventScheduledNotification($auth_id, $event_name, $scheduled_publish_time);
    } elseif ($status === 'published') {
        createEventPublishedNotification($auth_id, $event_name);
    } else {
        createEventCreatedNotification($auth_id, $event_name);
    }

    echo json_encode([
        'success' => true,
        'message' => 'Event created successfully',
        'event' => [
            'id' => $event_id,
            'event_name' => $event_name,
            'tag' => $tag,
            'external_link' => $external_link,
            'status' => $status
        ]
    ]);
} catch (Throwable $e) {
    if (strpos($e->getMessage(), 'Image upload failed') !== false || strpos($e->getMessage(), 'required fields') !== false) {
        http_response_code(400);
    } else {
        http_response_code(500);
    }
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
