<?php

/**
 * Ticket Helper for generating secure QR codes and PDF tickets
 *
 * QR Code payload is a signed token (HMAC-SHA256) to prevent forgery.
 * PDF tickets include: event name, date, time, location, attendee name,
 * ticket ID, and an embedded QR code image.
 */

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/email-helper.php';

use Dompdf\Dompdf;
use Dompdf\Options;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

/**
 * Helper to encode an image file to Base64 for Dompdf compatibility.
 */
function base64_encode_image($path) {
    if (!$path) return '';
    
    // Normalize path: handle relative paths and resolve to absolute
    $resolvedPath = $path;
    if (!file_exists($resolvedPath)) {
        // Try relative to project root
        $resolvedPath = __DIR__ . '/../../' . ltrim($path, '/');
    }
    
    if (!file_exists($resolvedPath)) {
        error_log("[TicketHelper] Image not found for base64 encoding: " . $path);
        return '';
    }
    
    $type = pathinfo($resolvedPath, PATHINFO_EXTENSION);
    $data = @file_get_contents($resolvedPath);
    if ($data === false) return '';
    
    return 'data:image/' . $type . ';base64,' . base64_encode($data);
}

/**
 * Generate a signed, secure QR code token for a ticket.
 * Payload: { tid, eid, uid, ps, iat, sig }
 *
 * @param array $ticketData  Ticket row data (must include barcode, event_id, user_id, payment_status or payment_id)
 * @return string Signed JSON payload that gets embedded in the QR
 */
function buildSecureQRPayload(array $ticketData): string
{
    $payload = [
        'tid' => $ticketData['barcode'],                          // Ticket ID
        'eid' => $ticketData['event_id'] ?? null,                 // Event ID
        'uid' => $ticketData['user_id'] ?? null,                  // User ID
        'oid' => $ticketData['order_id'] ?? null,                 // Order ID
        'ps' => $ticketData['payment_status'] ?? 'paid',         // Payment status
        'iat' => time(),                                           // Issued at
    ];

    // Sign the payload with HMAC-SHA256 using the server secret
    $dataStr = implode('|', [
        $payload['tid'],
        $payload['eid'],
        $payload['uid'],
        $payload['oid'] ?? '',
        $payload['ps'],
        $payload['iat']
    ]);
    $payload['sig'] = hash_hmac('sha256', $dataStr, QR_SECRET);

    return base64_encode(json_encode($payload));
}

/**
 * Verify a QR token received at scan time.
 *
 * @param string $qrData  The raw QR code content (base64-encoded JSON)
 * @return array ['valid' => bool, 'payload' => array|null, 'error' => string|null]
 */
function verifyQRPayload(string $qrData): array
{
    $decoded = base64_decode($qrData, true);
    if (!$decoded) {
        return ['valid' => false, 'payload' => null, 'error' => 'Invalid QR format'];
    }

    $payload = json_decode($decoded, true);
    if (!$payload || !isset($payload['tid'], $payload['eid'], $payload['uid'], $payload['ps'], $payload['iat'], $payload['sig'])) {
        return ['valid' => false, 'payload' => null, 'error' => 'Malformed QR payload'];
    }

    // Verify signature
    $dataStr = implode('|', [$payload['tid'], $payload['eid'], $payload['uid'], $payload['ps'], $payload['iat']]);
    $expectedSig = hash_hmac('sha256', $dataStr, QR_SECRET);

    if (!hash_equals($expectedSig, $payload['sig'])) {
        return ['valid' => false, 'payload' => null, 'error' => 'Invalid QR signature — possible forgery'];
    }

    return ['valid' => true, 'payload' => $payload, 'error' => null];
}

/**
 * Generate a QR code image for a ticket, embedding a signed secure token.
 *
 * @param array  $ticketData  Ticket row data
 * @return string Path to the generated QR code SVG file
 */
function generateTicketQRCode(array $ticketData): string
{
    try {
        // Build secure signed payload instead of raw barcode
        $secureToken = buildSecureQRPayload($ticketData);

        $options = new QROptions([
            'version' => QRCode::VERSION_AUTO,
            'outputType' => QRCode::OUTPUT_IMAGE_PNG,
            'eccLevel' => QRCode::ECC_M,
        ]);

        $qrcode = new QRCode($options);
        $svgData = $qrcode->render($secureToken);

        $fileName = 'qr_' . $ticketData['barcode'] . '.png';

        $dir = __DIR__ . '/../../uploads/tickets/qrcodes/';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $filePath = $dir . $fileName;
        file_put_contents($filePath, $svgData);

        return $filePath;
    } catch (\Throwable $e) {
        // QR generation failed — log and return empty string so ticket still issues
        error_log('[Eventra] QR code generation failed for ticket ' . ($ticketData['barcode'] ?? 'unknown') . ': ' . $e->getMessage());
        return '';
    }
}

/**
 * Generate a PDF ticket with all required fields + embedded QR code.
 *
 * Required fields in $ticketData:
 *   event_name, event_date, event_time, location / address,
 *   user_name, barcode, event_id, user_id, payment_status
 *
 * @param array $ticketData
 * @return string Path to generated PDF file
 */
function generateTicketPDF(array $ticketData): string
{
    $options = new Options();
    $options->set('isRemoteEnabled', true);
    $options->set('isFontSubsettingEnabled', true);
    $options->set('defaultFont', 'Inter');
    $options->set('dpi', 150);
    $dompdf = new Dompdf($options);

    // Generate secure QR code
    $qrCodePath = generateTicketQRCode($ticketData);
    $qr_code_image_path = $qrCodePath;

    // Prepare template variables
    $event_name = htmlspecialchars($ticketData['event_name'] ?? 'Event');
    $event_date = !empty($ticketData['event_date'])
        ? date('D, d M Y', strtotime($ticketData['event_date']))
        : 'TBC';
    $event_time = !empty($ticketData['event_time'])
        ? date('g:i A', strtotime($ticketData['event_time']))
        : 'TBC';
    $venue_name = htmlspecialchars($ticketData['location'] ?? $ticketData['address'] ?? 'See event details');
    $attendee_name = htmlspecialchars($ticketData['user_name'] ?? 'Attendee');
    $ticket_id = $ticketData['barcode'];
    
    // Additional fields for improved design
    $event_image_path = $ticketData['image_path'] ?? null;
    $ticket_type = strtolower($ticketData['ticket_type'] ?? 'regular');
    $event_type_label = ($ticket_type === 'vip') ? 'VIP Access Pass' : ($ticketData['event_type'] ?? 'Regular Entry Pass');
    
    // Price information
    $price_value = $ticketData['price'] ?? $ticketData['amount'] ?? null;
    $payment_status = $ticketData['payment_status'] ?? 'paid';
    if ($payment_status === 'free' || $price_value === 0 || $price_value === '0') {
        $price_display = 'FREE';
    } elseif ($price_value) {
        $price_display = '₦' . number_format((float)$price_value, 2);
    } else {
        $price_display = null;
    }
    
    // State/Location information
    $state = htmlspecialchars($ticketData['state'] ?? $ticketData['location_state'] ?? '');

    // Encode images to Base64 to fix "blank PDF" issues
    $qr_base64 = base64_encode_image($qrCodePath);
    $event_img_base64 = $event_image_path ? base64_encode_image(__DIR__ . '/../../' . $event_image_path) : '';
    
    // User-requested variable names for template
    $event_title = $event_name;
    $date = $event_date;
    $time = $event_time;
    $venue = $venue_name;
    $user_name = $attendee_name;
    $ticket_id = $ticketData['barcode'];
    $ticket_type_display = $event_type_label;
    $price = $price_display;


    // Map data for EmailHelper::buildTicketHtml
    $richTicketData = [
        'barcode'             => $ticket_id,
        'event_name'          => $event_name,
        'user_name'           => $user_name,
        'location'            => $venue_name,
        'state'               => $state,
        'ticket_type'         => $ticket_type,
        'ticket_type_display' => $event_type_label,
        'qr_base64'           => $qr_base64,
        'amount'              => $price_value,
        'event_date'          => $ticketData['event_date'] ?? null,
        'event_time'          => $ticketData['event_time'] ?? null,
    ];

    $html = EmailHelper::buildTicketHtml($richTicketData);

    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();

    $fileName = 'ticket_' . $ticketData['barcode'] . '.pdf';
    $dir = __DIR__ . '/../../uploads/tickets/pdfs/';
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    $filePath = $dir . $fileName;
    file_put_contents($filePath, $dompdf->output());

    return $filePath;
}
