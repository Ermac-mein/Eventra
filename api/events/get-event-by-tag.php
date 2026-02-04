<?php
header('Content-Type: application/json');
require_once '../../config/database.php';

$tag = $_GET['tag'] ?? null;

if (!$tag) {
    echo json_encode(['success' => false, 'message' => 'Tag is required']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT e.*, u.name as client_name, u.profile_pic as client_profile_pic 
        FROM events e
        LEFT JOIN clients u ON e.client_id = u.id
        WHERE e.tag = ? AND e.status = 'published'
    ");
    $stmt->execute([$tag]);
    $event = $stmt->fetch();

    if ($event) {
        echo json_encode(['success' => true, 'event' => $event]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Event not found or not published']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>