<?php
/**
 * Main Cron Scheduler for Eventra
 * This script should be run every 5 minutes via crontab:
 * ** * php /home/mein/Documents/Eventra/cron/scheduler.php >> /home/mein/Documents/Eventra/cron/cron.log 2>&1
 */

// 1. Setup Environment
require_once __DIR__ . '/../config/database.php';

echo "[" . date('Y-m-d H:i:s') . "] Starting Eventra Scheduler...\n";

try {
    // 2. Clear Expired Pending Payments (> 2 hours)
    // Mark pending payments that haven't been completed as 'failed' after 2 hours
    $stmt = $pdo->prepare("
        UPDATE payments 
        SET status = 'failed' 
        WHERE status = 'pending' 
          AND created_at < DATE_SUB(NOW(), INTERVAL 2 HOUR)
    ");
    $stmt->execute();
    $expiredCount = $stmt->rowCount();
    if ($expiredCount > 0) {
        echo " - Cleared $expiredCount expired pending payments.\n";
    }

    // 3. Archive Passed Events
    // Automatically set admin_status = 'archived' for all events whose event_date has passed.
    $stmtArchive = $pdo->prepare("
        UPDATE events 
        SET admin_status = 'archived' 
        WHERE event_date < CURDATE() 
          AND admin_status NOT IN ('archived', 'banished')
          AND deleted_at IS NULL
    ");
    $stmtArchive->execute();
    $archivedCount = $stmtArchive->rowCount();
    if ($archivedCount > 0) {
        echo " - Archived $archivedCount past events.\n";
    }

    // 4. Trigger Scheduled Notifications
    // We execute the existing notification cron using absolute path
    $notificationCron = __DIR__ . '/../api/events/schedule-notification-cron.php';
    if (file_exists($notificationCron)) {
        echo " - Running Notification Cron...\n";
        // Use escapeshellarg() for safety and absolute PHP path
        $phpPath = PHP_BINARY ?: 'php';
        $output = shell_exec(escapeshellarg($phpPath) . ' ' . escapeshellarg($notificationCron));
        if ($output) {
            echo "   " . str_replace("\n", "\n   ", trim($output)) . "\n";
        }
    } else {
        echo " - ERROR: Notification cron script not found at $notificationCron\n";
    }

    echo "[" . date('Y-m-d H:i:s') . "] Scheduler finished successfully.\n";

} catch (Exception $e) {
    echo "FATAL ERROR in Scheduler: " . $e->getMessage() . "\n";
    error_log("Eventra Scheduler Error: " . $e->getMessage());
}
