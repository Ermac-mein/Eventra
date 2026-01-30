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
    // Handle file upload if present
    $image_path = null;
    if (isset($_FILES['event_image']) && $_FILES['event_image']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = '../../uploads/events/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        $file_extension = pathinfo($_FILES['event_image']['name'], PATHINFO_EXTENSION);
        $file_name = uniqid('event_') . '.' . $file_extension;
        $target_path = $upload_dir . $file_name;

        if (move_uploaded_file($_FILES['event_image']['tmp_name'], $target_path)) {
            $image_path = '/uploads/events/' . $file_name;
        }
    }

    // Get POST data
    $event_name = $_POST['event_name'] ?? '';
    $description = $_POST['description'] ?? '';
    $event_type = $_POST['event_type'] ?? '';
    $event_date = $_POST['event_date'] ?? '';
    $event_time = $_POST['event_time'] ?? '';
    $phone_contact_1 = $_POST['phone_contact_1'] ?? '';
    $phone_contact_2 = !empty($_POST['phone_contact_2']) ? $_POST['phone_contact_2'] : null;
    $state = $_POST['state'] ?? '';
    $address = $_POST['address'] ?? '';
    $visibility = $_POST['visibility'] ?? 'all_states';
    $price = $_POST['price'] ?? 0.00;
    $priority = $_POST['priority'] ?? 'nearby';
    $status = $_POST['status'] ?? 'draft';
    $scheduled_publish_time = !empty($_POST['scheduled_publish_time']) ? $_POST['scheduled_publish_time'] : null;

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

    // Auto-generate external link with event name and client name
    $stmt = $pdo->prepare("SELECT name FROM users WHERE id = ?");
    $stmt->execute([$client_id]);
    $client = $stmt->fetch();
    $client_name = strtolower(str_replace(' ', '-', preg_replace('/[^A-Za-z0-9 ]/', '', $client['name'])));

    $base_url = $_ENV['APP_URL'] ?? 'http://localhost:8000';
    $external_link = $base_url . '/public/pages/event-details.html?event=' . $tag . '&client=' . $client_name;

    // Insert event
    $stmt = $pdo->prepare("
        INSERT INTO events (
            client_id, event_name, description, event_type, event_date, event_time,
            phone_contact_1, phone_contact_2, state, address, visibility, tag,
            external_link, price, image_path, priority, status, scheduled_publish_time
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $stmt->execute([
        $client_id,
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
        $scheduled_publish_time
    ]);

    $event_id = $pdo->lastInsertId();

    // Create notifications using helper functions
    require_once '../utils/notification-helper.php';

    // Notify admin about event creation
    $admin_id = getAdminUserId();
    if ($admin_id) {
        $client_name = $client['name'];
        $admin_message = "New event created: '{$event_name}' by {$client_name} - Status: {$status}";
        createNotification($admin_id, $admin_message, 'event_created', $client_id);
    }

    // Notify client
    if ($status === 'scheduled' && $scheduled_publish_time) {
        createEventScheduledNotification($client_id, $event_name, $scheduled_publish_time);
    } elseif ($status === 'published') {
        createEventPublishedNotification($client_id, $event_name);
    } else {
        createEventCreatedNotification($client_id, $event_name);
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

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>