<?php

/**
 * Download Ticket API
 * Streams the PDF ticket to the authenticated user.
 *
 * GET ?code=BARCODE
 */

require_once '../../config/database.php';
require_once '../../includes/helpers/ticket-helper.php';

$barcode = trim($_GET['code'] ?? '');
if (empty($barcode)) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Ticket code is required.']);
    exit;
}

try {
    // Verify ticket using the barcode as a secure token
    $tStmt = $pdo->prepare("
        SELECT 
            t.barcode, t.status, t.event_id, t.user_id, t.payment_id,
            e.event_name, e.event_date, e.event_time, e.location, e.address, e.image_path,
            u.name as user_name,
            p.status as payment_status, p.id as order_id
        FROM tickets t
        JOIN events e ON t.event_id = e.id
        JOIN users u ON t.user_id = u.id
        LEFT JOIN payments p ON t.payment_id = p.id
        WHERE t.barcode = ?
    ");
    $tStmt->execute([$barcode]);
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
        try {
            // Generate the PDF on-the-fly
            generateTicketPDF($ticket);
            
            if (!file_exists($pdfPath)) {
                throw new Exception("PDF generation failed to create file.");
            }
        } catch (Exception $e) {
            error_log('[download-ticket.php] Generation error: ' . $e->getMessage());
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Failed to generate ticket. Please contact support.']);
            exit;
        }
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
