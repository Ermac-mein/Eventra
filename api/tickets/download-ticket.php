<?php

/**
 * Download Ticket API
 * Streams the PDF ticket to the authenticated user.
 *
 * GET ?code=BARCODE
 */

require_once '../../config/database.php';
require_once '../../includes/middleware/auth.php';

$auth_id = checkAuth('user');

$barcode = trim($_GET['code'] ?? '');
if (empty($barcode)) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Ticket code is required.']);
    exit;
}

try {
    // Use auth_id directly (it is user_id from checkAuth)
    $resolved_user_id = $auth_id;

    // Verify ticket ownership
    $tStmt = $pdo->prepare("
        SELECT t.barcode, t.status
        FROM tickets t
        WHERE t.barcode = ? AND t.user_id = ?
    ");
    $tStmt->execute([$barcode, $resolved_user_id]);
    $ticket = $tStmt->fetch(PDO::FETCH_ASSOC);

    if (!$ticket) {
        http_response_code(404);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Ticket not found.']);
        exit;
    }

    // Build file path
    $pdfPath = __DIR__ . '/../../uploads/tickets/pdfs/ticket_' . $barcode . '.pdf';

    if (!file_exists($pdfPath)) {
        http_response_code(404);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Ticket PDF not found. Please contact support.']);
        exit;
    }

    // Stream file to client
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="eventra_ticket_' . $barcode . '.pdf"');
    header('Content-Length: ' . filesize($pdfPath));
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    readfile($pdfPath);
} catch (PDOException $e) {
    error_log('[download-ticket.php] DB error: ' . $e->getMessage());
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Failed to retrieve ticket.']);
}
