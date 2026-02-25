<?php
header('Content-Type: application/json');
require_once '../../config/database.php';
require_once '../../includes/middleware/auth.php'; // For clientMiddleware or similar
require_once '../../includes/classes/Ticket.php';

// This endpoint should be protected by ClientMiddleware as they are the ones scanning
try {
    // clientMiddleware(); // Assuming client scans tickets

    $data = json_decode(file_get_contents("php://input"), true);
    $barcode = $data['barcode'] ?? null;

    if (!$barcode) {
        echo json_encode(['success' => false, 'message' => 'Barcode is required.']);
        exit;
    }

    $result = Ticket::validateAndUse($pdo, $barcode);

    if ($result['success']) {
        echo json_encode([
            'success' => true,
            'message' => 'Ticket Validated!',
            'data' => [
                'event_name' => $result['data']['event_name'],
                'event_date' => $result['data']['event_date'],
                'organizer' => $result['data']['client_name'],
                'buyer_name' => $result['data']['buyer_name'],
                'buyer_email' => $result['data']['buyer_email'],
                'scanned_at' => date('Y-m-d H:i:s')
            ]
        ]);
    } else {
        echo json_encode($result);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
