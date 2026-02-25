<?php
require_once 'config/database.php';

$searchTerm = "%ima%";
$realClientId = 1; // Assuming a valid client ID

try {
    $sql = "
        (SELECT m.id, m.file_name COLLATE utf8mb4_unicode_ci as title, m.file_type COLLATE utf8mb4_unicode_ci as subtitle, m.file_path COLLATE utf8mb4_unicode_ci as extra, m.file_size, m.uploaded_at, 'file' COLLATE utf8mb4_unicode_ci as item_type
         FROM media m
         WHERE m.is_deleted = 0 AND (m.file_name LIKE ? OR m.file_type LIKE ?)
         AND m.client_id = ?)
        UNION ALL
        (SELECT f.id, f.name as title, 'folder' COLLATE utf8mb4_unicode_ci as subtitle, '' COLLATE utf8mb4_unicode_ci as extra, 0 as file_size, f.created_at as uploaded_at, 'folder' COLLATE utf8mb4_unicode_ci as item_type
         FROM media_folders f
         WHERE f.is_deleted = 0 AND f.name LIKE ?
         AND f.client_id = ?)
        ORDER BY uploaded_at DESC LIMIT 30
    ";
    $params = [$searchTerm, $searchTerm, $realClientId, $searchTerm, $realClientId];
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    echo "Media query successful.\n";
} catch (PDOException $e) {
    echo "Media query failed: " . $e->getMessage() . "\n";
}

try {
    $sql = "SELECT t.id, t.barcode as title, e.event_name as subtitle, u.name as extra, 
                   a.email as user_email, p.paid_at as purchase_date, 1 as quantity
            FROM tickets t
            INNER JOIN payments p ON t.payment_id = p.id
            INNER JOIN events e ON p.event_id = e.id
            LEFT JOIN users u ON p.user_id = u.user_auth_id
            LEFT JOIN auth_accounts a ON p.user_id = a.id
            WHERE (t.barcode LIKE ? OR u.name LIKE ? OR a.email LIKE ? OR e.event_name LIKE ?) AND e.client_id = ?";
    $params = [$searchTerm, $searchTerm, $searchTerm, $searchTerm, $realClientId];
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    echo "Tickets query successful.\n";
} catch (PDOException $e) {
    echo "Tickets query failed: " . $e->getMessage() . "\n";
}

try {
    $sql = "SELECT DISTINCT u.user_auth_id as id, u.name as title, a.email as subtitle, u.profile_pic
            FROM users u
            INNER JOIN payments p ON u.user_auth_id = p.user_id
            INNER JOIN tickets t ON p.id = t.payment_id
            INNER JOIN events e ON p.event_id = e.id
            INNER JOIN auth_accounts a ON u.user_auth_id = a.id
            WHERE e.client_id = ? AND (u.name LIKE ? OR a.email LIKE ?)
            LIMIT 20";
    $params = [$realClientId, $searchTerm, $searchTerm];
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    echo "Users query successful.\n";
} catch (PDOException $e) {
    echo "Users query failed: " . $e->getMessage() . "\n";
}

try {
    $sql = "SELECT e.id, e.event_name as title, e.event_type as subtitle, e.event_type as category, e.price, e.state, e.status, e.event_date, e.event_time, e.image_path, e.priority 
            FROM events e
            LEFT JOIN clients c ON e.client_id = c.id
            WHERE e.deleted_at IS NULL AND (e.event_name LIKE ? OR e.description LIKE ? OR e.state LIKE ? OR e.event_type LIKE ? OR c.business_name LIKE ? OR e.price LIKE ? OR e.event_date LIKE ?) AND e.client_id = ? ORDER BY e.event_date DESC LIMIT 20";
    $params = [$searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm, $realClientId];
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    echo "Events query successful.\n";
} catch (PDOException $e) {
    echo "Events query failed: " . $e->getMessage() . "\n";
}
