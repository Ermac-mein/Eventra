<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once '../../config/database.php';

try {
    echo "Starting debug...\n";

    $client_id = 1;
    $limit = 10;
    $offset = 0;

    $sql = "
        SELECT e.*, u.business_name as client_name, u.profile_pic as client_profile_pic, 0 as is_favorite
        FROM events e
        LEFT JOIN clients u ON e.client_id = u.auth_id
        WHERE e.client_id = ?
        ORDER BY e.created_at DESC
        LIMIT ? OFFSET ?
    ";

    echo "Preparing statement...\n";
    $stmt = $pdo->prepare($sql);

    echo "Binding params...\n";
    $stmt->bindValue(1, $client_id, PDO::PARAM_STR);
    $stmt->bindValue(2, $limit, PDO::PARAM_INT);
    $stmt->bindValue(3, $offset, PDO::PARAM_INT);

    echo "Executing...\n";
    $stmt->execute();

    echo "Fetching...\n";
    $events = $stmt->fetchAll();

    echo "Success! Found " . count($events) . " events.\n";
    print_r($events);

} catch (Exception $e) {
    echo "Caught Exception: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}
?>