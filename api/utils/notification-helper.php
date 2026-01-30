<?php
/**
 * Notification Helper Functions
 * Provides standardized notification creation across the application
 */

require_once __DIR__ . '/../../config/database.php';

/**
 * Create a notification
 * 
 * @param int $recipient_id User ID who will receive the notification
 * @param string $message Notification message
 * @param string $type Notification type (login, logout, event_created, etc.)
 * @param int|null $sender_id Optional sender user ID
 * @return bool Success status
 */
function createNotification($recipient_id, $message, $type = 'info', $sender_id = null)
{
    global $pdo;

    try {
        $stmt = $pdo->prepare("
            INSERT INTO notifications (recipient_id, sender_id, message, type, is_read, created_at)
            VALUES (?, ?, ?, ?, 0, NOW())
        ");

        $stmt->execute([$recipient_id, $sender_id, $message, $type]);
        return true;
    } catch (PDOException $e) {
        error_log("Notification creation failed: " . $e->getMessage());
        return false;
    }
}

/**
 * Create a login notification
 */
function createLoginNotification($user_id, $user_name, $user_email)
{
    $message = "Welcome back, {$user_name}! You logged in with {$user_email}";
    return createNotification($user_id, $message, 'login', $user_id);
}

/**
 * Create a logout notification
 */
function createLogoutNotification($user_id, $user_name)
{
    $message = "{$user_name} logged out";
    return createNotification($user_id, $message, 'logout', $user_id);
}

/**
 * Create an event created notification
 */
function createEventCreatedNotification($client_id, $event_name)
{
    $message = "Event '{$event_name}' has been created successfully";
    return createNotification($client_id, $message, 'event_created', $client_id);
}

/**
 * Create an event scheduled notification
 */
function createEventScheduledNotification($client_id, $event_name, $scheduled_time)
{
    $formatted_time = date('M d, Y \a\t g:i A', strtotime($scheduled_time));
    $message = "Event '{$event_name}' has been scheduled for {$formatted_time}";
    return createNotification($client_id, $message, 'event_scheduled', $client_id);
}

/**
 * Create an event published notification
 */
function createEventPublishedNotification($client_id, $event_name)
{
    $message = "Event '{$event_name}' has been published and is now live";
    return createNotification($client_id, $message, 'event_published', $client_id);
}

/**
 * Create a media uploaded notification
 */
function createMediaUploadedNotification($client_id, $file_name, $folder_name = null)
{
    $location = $folder_name ? "to folder '{$folder_name}'" : "";
    $message = "Media file '{$file_name}' has been uploaded {$location}";
    return createNotification($client_id, $message, 'media_uploaded', $client_id);
}

/**
 * Create a scheduled event due notification (with action buttons)
 */
function createScheduledEventDueNotification($client_id, $event_id, $event_name)
{
    $message = "Event '{$event_name}' is ready to be published. Click to publish or cancel.";
    return createNotification($client_id, $message, 'scheduled_event_due', $client_id);
}

/**
 * Get unread notification count for a user
 */
function getUnreadNotificationCount($user_id)
{
    global $pdo;

    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM notifications WHERE recipient_id = ? AND is_read = 0");
        $stmt->execute([$user_id]);
        $result = $stmt->fetch();
        return $result['count'] ?? 0;
    } catch (PDOException $e) {
        error_log("Failed to get notification count: " . $e->getMessage());
        return 0;
    }
}

/**
 * Create an event deleted notification
 */
function createEventDeletedNotification($admin_id, $event_name, $deleted_by_name)
{
    $message = "Event '{$event_name}' has been deleted by {$deleted_by_name}";
    return createNotification($admin_id, $message, 'event_deleted', null);
}

/**
 * Create ticket purchase notifications for admin, client, and user
 */
function createTicketPurchaseNotification($admin_id, $client_id, $user_id, $buyer_name, $buyer_email, $event_name, $quantity, $total_price)
{
    // Notify admin with buyer info
    $admin_message = "New ticket purchase: {$buyer_name} ({$buyer_email}) bought {$quantity} ticket(s) for '{$event_name}' - Total: ₦" . number_format($total_price, 2);
    createNotification($admin_id, $admin_message, 'ticket_purchase', $user_id);

    // Notify client with buyer info
    $client_message = "New ticket sale: {$buyer_name} ({$buyer_email}) purchased {$quantity} ticket(s) for your event '{$event_name}' - Total: ₦" . number_format($total_price, 2);
    createNotification($client_id, $client_message, 'ticket_purchase', $user_id);

    // Notify user (buyer) with confirmation
    $user_message = "Purchase confirmed! You bought {$quantity} ticket(s) for '{$event_name}' - Total: ₦" . number_format($total_price, 2);
    createNotification($user_id, $user_message, 'ticket_purchase_confirmation', $user_id);

    return true;
}

/**
 * Create user login notification for admin
 */
function createUserLoginNotification($admin_id, $user_id, $user_name, $user_email, $user_role = 'user')
{
    $role_label = ucfirst($user_role);
    $message = "{$role_label} login: {$user_name} ({$user_email}) has logged in";
    return createNotification($admin_id, $message, 'user_login', $user_id);
}

/**
 * Create client login notification for admin
 */
function createClientLoginNotification($admin_id, $client_id, $client_name, $client_email)
{
    $message = "Client login: {$client_name} ({$client_email}) has logged in";
    return createNotification($admin_id, $message, 'client_login', $client_id);
}

/**
 * Get admin user ID (first admin in the system)
 */
function getAdminUserId()
{
    global $pdo;

    try {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE role = 'admin' LIMIT 1");
        $stmt->execute();
        $result = $stmt->fetch();
        return $result['id'] ?? null;
    } catch (PDOException $e) {
        error_log("Failed to get admin user ID: " . $e->getMessage());
        return null;
    }
}
?>