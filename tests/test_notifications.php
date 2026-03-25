<?php
require_once __DIR__ . '/../api/utils/notification-helper.php';
require_once __DIR__ . '/../config/database.php';

echo "Testing notification roles...\n";

// Test Client Notification
echo "Testing createEventCreatedNotification...\n";
$res1 = createEventCreatedNotification(1, "Test Event");
if ($res1) {
    echo "SUCCESS: createEventCreatedNotification called.\n";
} else {
    echo "FAILURE: createEventCreatedNotification failed.\n";
}

// Test Scheduled Notification
echo "Testing createEventScheduledNotification...\n";
$res2 = createEventScheduledNotification(1, "Scheduled Event", date('Y-m-d H:i:s'));
if ($res2) {
    echo "SUCCESS: createEventScheduledNotification called.\n";
} else {
    echo "FAILURE: createEventScheduledNotification failed.\n";
}

// Check database
$stmt = $pdo->prepare("SELECT * FROM notifications ORDER BY id DESC LIMIT 2");
$stmt->execute();
$notifs = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($notifs as $notif) {
    echo "ID: {$notif['id']}, Type: {$notif['type']}, Recipient Role: {$notif['recipient_role']}\n";
    if ($notif['recipient_role'] !== 'client') {
        echo "ERROR: Expected recipient_role 'client', got '{$notif['recipient_role']}'\n";
    }
}

echo "Verification complete.\n";
