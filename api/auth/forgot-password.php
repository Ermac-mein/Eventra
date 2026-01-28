<?php
header('Content-Type: application/json');
require_once '../../config/database.php';

$data = json_decode(file_get_contents("php://input"), true);

if (!isset($data['email'])) {
    echo json_encode(['success' => false, 'message' => 'Email is required.']);
    exit;
}

$email = $data['email'];

try {
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user) {
        // In a real app, generate a token and send an email
        // For this demo, we'll just return success
        echo json_encode([
            'success' => true,
            'message' => 'Password reset link has been sent to your email.'
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'If this email is registered, you will receive a reset link.']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error.']);
}
?>