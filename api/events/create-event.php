<?php
/**
 * Create Event API
 * Handles event creation with all fields including scheduling, priority, tags, and links
 */
header('Content-Type: application/json');
require_once '../../config/database.php';
require_once '../../config/env-loader.php';

require_once '../../includes/middleware/auth.php';

// Check authentication and role
$client_id = checkAuth('client');

try {
    // 1. Resolve actual Client name from client_id early
    $stmt = $pdo->prepare("SELECT name, verification_status FROM clients WHERE id = ?");
    $stmt->execute([$client_id]);
    $client_data = $stmt->fetch();

    if (!$client_data) {
        throw new Exception("Client profile not found for this account.");
    }

    $real_client_id = $client_id;
    $raw_client_name = $client_data['name'] ?? 'client';
    $client_name = strtolower(str_replace(' ', '-', preg_replace('/[^A-Za-z0-9 ]/', '', $raw_client_name)));

    // ── Event Limit Check for Unverified Clients ──────────────────────────────
    $limitStmt = $pdo->prepare("
        SELECT COUNT(*) AS event_count
        FROM events WHERE client_id = ? AND deleted_at IS NULL
    ");
    $limitStmt->execute([$real_client_id]);
    $limitData = $limitStmt->fetch();

    if ($client_data && $client_data['verification_status'] !== 'verified') {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => "Unverified accounts cannot create events. Please complete your profile verification (NIN, BVN, bank account) and wait for admin approval to start creating events.",
            'limit_reached' => true
        ]);
        exit;
    }
    // ─────────────────────────────────────────────────────────────────────────

    // 2. Handle file upload if present using standardized path
    $image_path = null;
    if (isset($_FILES['event_image']) && $_FILES['event_image']['error'] === UPLOAD_ERR_OK) {
        $folder_name = 'Event Images';
        $upload_dir = "../../uploads/media/client_{$real_client_id}/{$folder_name}/";
        
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        $file_extension = strtolower(pathinfo($_FILES['event_image']['name'], PATHINFO_EXTENSION));
        $file_name = uniqid('event_') . '.' . $file_extension;
        $target_path = $upload_dir . $file_name;

        if (move_uploaded_file($_FILES['event_image']['tmp_name'], $target_path)) {
            $image_path = "/uploads/media/client_{$real_client_id}/{$folder_name}/" . $file_name;
            
            // Register in media table
            try {
                $file_size = filesize($target_path);
                $mime_type = mime_content_type($target_path);

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
    require_once '/api/utils/id-generator.php';
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
    $price = $_POST['price'] ?? 0.00;

    // Validate priority
    $allowed_priorities = ['nearby', 'hot', 'trending', 'upcoming', 'featured'];
    $priority_input = $_POST['priority'] ?? 'nearby';
    $priority = in_array($priority_input, $allowed_priorities) ? $priority_input : 'nearby';

    $status = $_POST['status'] ?? 'draft';
    $scheduled_publish_time = !empty($_POST['scheduled_publish_time'])
        ? $_POST['scheduled_publish_time']
        : date('Y-m-d H:i:s');


    // Validate required fields
    if (
        empty($event_name) || empty($description) || empty($event_type) ||
        empty($event_date) || empty($event_time) || empty($phone_contact_1) ||
        empty($state) || empty($address)
    ) {
        echo json_encode(['success' => false, 'message' => 'All required fields must be filled.']);
        exit;
    }

    // Auto-generate tag from event name (lowercase, hyphenated)
    $tag = strtolower(str_replace(' ', '-', preg_replace('/[^A-Za-z0-9 ]/', '', $event_name)));

    $base_url = $_ENV['APP_URL'] ?? 'http://localhost:8000';
    $external_link = $base_url . '/public/pages/event-details.html?event=' . $tag . '&client=' . $client_name;

    // Insert event
    $stmt = $pdo->prepare("
        INSERT INTO events (
            client_id, custom_id, event_name, description, event_type, event_date, event_time,
            phone_contact_1, phone_contact_2, state, address, visibility, tag,
            external_link, price, image_path, priority, status, scheduled_publish_time, category
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $stmt->execute([
        $real_client_id, // Use the correct foreign key
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
        $image_path,
        $priority,
        $status,
        $scheduled_publish_time,
        $event_type
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
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
