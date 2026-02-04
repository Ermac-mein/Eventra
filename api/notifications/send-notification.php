<?php
require_once __DIR__ . '/../../config/database.php';

function sendNotification($recipient_id, $message, $type = 'info', $sender_id = null)
{
    global $pdo;
    try {
        $stmt = $pdo->prepare("INSERT INTO notifications (recipient_auth_id, sender_auth_id, message, type) VALUES (?, ?, ?, ?)");
        $stmt->execute([$recipient_id, $sender_id, $message, $type]);
        return true;
    } catch (PDOException $e) {
        return false;
    }
}
?>