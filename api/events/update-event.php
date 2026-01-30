<?php
/**
 * Update Event API
 * Updates event details (only for draft and scheduled events)
 */
header('Content-Type: application/json');
require_once '../../config/database.php';

// Check authentication
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];
$event_id = $_POST['event_id'] ?? null;

if (!$event_id) {
    echo json_encode(['success' => false, 'message' => 'Event ID is required']);
    exit;
}

try {
    // Get current event details
    $stmt = $pdo->prepare("SELECT * FROM events WHERE id = ?");
    $stmt->execute([$event_id]);
    $event = $stmt->fetch();

    if (!$event) {
        echo json_encode(['success' => false, 'message' => 'Event not found']);
        exit;
    }

    // Check if user owns the event or is admin
    if ($_SESSION['role'] !== 'admin' && $event['client_id'] != $user_id) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'You do not have permission to update this event']);
        exit;
    }

    // CRITICAL: Prevent editing published events
    if ($event['status'] === 'published') {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => 'Published events cannot be edited. Only draft and scheduled events can be modified.'
        ]);
        exit;
    }

    // Handle image upload if provided
    $image_path = $event['image_path']; // Keep existing image by default
    if (isset($_FILES['event_image']) && $_FILES['event_image']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = '../../uploads/events/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        $file_extension = pathinfo($_FILES['event_image']['name'], PATHINFO_EXTENSION);
        $new_filename = 'event_' . $event_id . '_' . time() . '.' . $file_extension;
        $upload_path = $upload_dir . $new_filename;

        if (move_uploaded_file($_FILES['event_image']['tmp_name'], $upload_path)) {
            $image_path = '/uploads/events/' . $new_filename;

            // Delete old image if it exists
            if ($event['image_path'] && file_exists('../../' . $event['image_path'])) {
                unlink('../../' . $event['image_path']);
            }
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
            status = ?,
            description = ?,
            state = ?,
            visibility = ?,
            address = ?,
            phone_contact_1 = ?,
            phone_contact_2 = ?,
            image_path = ?,
            updated_at = NOW()
            WHERE id = ?";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $_POST['event_name'],
        $_POST['event_type'],
        $_POST['priority'] ?? 'normal',
        $_POST['event_date'],
        $_POST['event_time'],
        $_POST['price'],
        $_POST['status'],
        $_POST['description'],
        $_POST['state'],
        $_POST['visibility'] ?? 'all_states',
        $_POST['address'],
        $_POST['phone_contact_1'],
        $_POST['phone_contact_2'] ?? null,
        $image_path,
        $event_id
    ]);

    // Create notification
    $message = "Event '{$_POST['event_name']}' has been updated";
    $stmt = $pdo->prepare("INSERT INTO notifications (recipient_id, sender_id, message, type) VALUES (?, ?, ?, ?)");
    $stmt->execute([$event['client_id'], $user_id, $message, 'event_updated']);

    echo json_encode([
        'success' => true,
        'message' => 'Event updated successfully'
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>