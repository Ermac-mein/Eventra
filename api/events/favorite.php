<?php

/**
 * Toggle Favorite API
 */

header('Content-Type: application/json');
require_once '../../config/database.php';
require_once '../../includes/middleware/auth.php';

// Get auth_id (this now handles both Bearer token and session)
$auth_id_or_user_id = checkAuth('user');

// Determine the actual user_id from auth_id
$user_id = null;
$stmt = $pdo->prepare("SELECT id FROM users WHERE user_auth_id = ?");
$stmt->execute([$auth_id_or_user_id]);
$user_id = $stmt->fetchColumn();

if (!$user_id) {
    // Try if it was a user_id passed directly (from session)
    $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ?");
    $stmt->execute([$auth_id_or_user_id]);
    $user_id = $stmt->fetchColumn();
}

if (!$user_id) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'User not found']);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);

if (!isset($data['event_id'])) {
    echo json_encode(['success' => false, 'message' => 'Event ID is required']);
    exit;
}

$event_id = $data['event_id'];

try {
    // Check if already favorited
    $stmt = $pdo->prepare("SELECT id FROM favorites WHERE user_id = ? AND event_id = ?");
    $stmt->execute([$user_id, $event_id]);
    $favorite = $stmt->fetch();

    if ($favorite) {
        // Remove from favorites
        $stmt = $pdo->prepare("DELETE FROM favorites WHERE user_id = ? AND event_id = ?");
        $stmt->execute([$user_id, $event_id]);
        $is_favorite = false;
        $message = "Removed from favorites";
    } else {
        // Add to favorites
        $stmt = $pdo->prepare("INSERT INTO favorites (user_id, event_id) VALUES (?, ?)");
        $stmt->execute([$user_id, $event_id]);
        $is_favorite = true;
        $message = "Added to favorites";
    }

    echo json_encode([
        'success' => true,
        'message' => $message,
        'is_favorite' => $is_favorite
    ]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
