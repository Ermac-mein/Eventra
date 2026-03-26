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

use Dompdf\Dompdf;
use Dompdf\Options;
use Chillerlan\QRCode\QRCode;
use Chillerlan\QRCode\QROptions;

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
        'ps'  => $ticketData['payment_status'] ?? 'paid',         // Payment status
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
    // Build secure signed payload instead of raw barcode
    $secureToken = buildSecureQRPayload($ticketData);

    $options = new QROptions([
        'version'    => 7,
        'outputType' => QRCode::OUTPUT_MARKUP_SVG,
        'eccLevel'   => QRCode::ECC_M, // Medium error correction (better for real scanning)
    ]);

    $qrcode = new QRCode($options);
    $svgData = $qrcode->render($secureToken);

    $fileName = 'qr_' . $ticketData['barcode'] . '.svg';
    $dir = __DIR__ . '/../../uploads/tickets/qrcodes/';
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    $filePath = $dir . $fileName;
    file_put_contents($filePath, $svgData);

    return $filePath;
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
    $dompdf = new Dompdf($options);

    // Generate secure QR code
    $qrCodePath = generateTicketQRCode($ticketData);
    $qrCodeData = base64_encode(file_get_contents($qrCodePath));
    $qrCodeSrc  = 'data:image/svg+xml;base64,' . $qrCodeData;

    // Format dates
    $eventDate = !empty($ticketData['event_date'])
        ? date('D, d M Y', strtotime($ticketData['event_date']))
        : 'TBC';
    $eventTime = !empty($ticketData['event_time'])
        ? date('g:i A', strtotime($ticketData['event_time']))
        : 'TBC';
    $venue = $ticketData['location'] ?? $ticketData['address'] ?? 'See event details';
    $userName = $ticketData['user_name'] ?? 'Attendee';
    $ticketId = $ticketData['barcode'];
    $eventName = htmlspecialchars($ticketData['event_name'] ?? 'Event');
    $generatedAt = date('d M Y, H:i');

    // Handle Event Image
    $eventImgSrc = "";
    if (!empty($ticketData['event_image'])) {
        $imgPath = __DIR__ . '/../../' . ltrim($ticketData['event_image'], '/');
        if (file_exists($imgPath)) {
            $imgData = base64_encode(file_get_contents($imgPath));
            $mime = mime_content_type($imgPath);
            $eventImgSrc = "data:$mime;base64,$imgData";
        }
    }

    $html = "
    <html>
    <head>
        <style>
            * { box-sizing: border-box; margin: 0; padding: 0; }
            body { font-family: 'Helvetica', sans-serif; color: #1f2937; background: #f9fafb; }
            .ticket-wrapper { padding: 20px; }
            .ticket {
                width: 100%;
                border: 2px solid #7c3aed;
                border-radius: 12px;
                overflow: hidden;
                background: #ffffff;
                box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            }
            .ticket-header {
                background: linear-gradient(135deg, #7c3aed, #4c1d95);
                color: white;
                padding: 18px 25px;
                display: flex;
                justify-content: space-between;
                align-items: center;
            }
            .ticket-header h1 { font-size: 20px; letter-spacing: 3px; font-weight: 900; }
            .ticket-header .ticket-badge {
                background: rgba(255,255,255,0.2);
                padding: 4px 12px;
                border-radius: 20px;
                font-size: 11px;
                letter-spacing: 1px;
            }
            .ticket-hero {
                height: 120px;
                background: " . ($eventImgSrc ? "url($eventImgSrc)" : "#7c3aed") . ";
                background-size: cover;
                background-position: center;
                border-bottom: 2px solid #7c3aed;
            }
            .ticket-body { display: flex; padding: 25px; gap: 20px; }
            .ticket-info { flex: 1; }
            .ticket-info h2 { font-size: 22px; font-weight: 800; color: #7c3aed; margin-bottom: 15px; }
            .ticket-info table { width: 100%; border-collapse: collapse; font-size: 13px; }
            .ticket-info table tr td { padding: 6px 0; vertical-align: top; }
            .ticket-info table tr td:first-child { font-weight: 700; color: #6b7280; width: 90px; }
            .ticket-info table tr td:last-child { color: #1f2937; }
            .ticket-qr { text-align: center; padding: 10px; border-left: 2px dashed #e5e7eb; padding-left: 20px; }
            .ticket-qr img { width: 130px; height: 130px; }
            .ticket-qr p { font-size: 10px; color: #9ca3af; margin-top: 8px; }
            .ticket-stub {
                border-top: 2px dashed #7c3aed;
                padding: 12px 25px;
                background: #faf5ff;
                display: flex;
                justify-content: space-between;
                align-items: center;
            }
            .ticket-id { font-family: monospace; font-size: 13px; color: #7c3aed; font-weight: 700; letter-spacing: 1px; }
            .ticket-footer {
                background: #f3f4f6;
                padding: 10px 25px;
                font-size: 10px;
                color: #9ca3af;
                text-align: center;
            }
        </style>
    </head>
    <body>
        <div class='ticket-wrapper'>
            <div class='ticket'>
                <div class='ticket-header'>
                    <h1>EVENTRA</h1>
                    <span class='ticket-badge'>OFFICIAL TICKET</span>
                </div>
                " . ($eventImgSrc ? "<div class='ticket-hero'></div>" : "") . "
                <div class='ticket-body'>
                    <div class='ticket-info'>
                        <h2>{$eventName}</h2>
                        <table>
                            <tr>
                                <td>📅 Date</td>
                                <td>{$eventDate}</td>
                            </tr>
                            <tr>
                                <td>⏰ Time</td>
                                <td>{$eventTime}</td>
                            </tr>
                            <tr>
                                <td>📍 Venue</td>
                                <td>" . htmlspecialchars($venue) . "</td>
                            </tr>
                            <tr>
                                <td>👤 Attendee</td>
                                <td>" . htmlspecialchars($userName) . "</td>
                            </tr>
                            <tr>
                                <td>🎟 Ticket ID</td>
                                <td><strong>{$ticketId}</strong></td>
                            </tr>
                            <tr>
                                <td>📦 Order</td>
                                <td>#" . ($ticketData['order_id'] ?? 'N/A') . "</td>
                            </tr>
                        </table>
                    </div>
                    <div class='ticket-qr'>
                        <img src='{$qrCodeSrc}' alt='QR Code'>
                        <p>Scan to validate<br>entry at venue</p>
                    </div>
                </div>
                <div class='ticket-stub'>
                    <div>
                        <div style='font-size:10px; color:#9ca3af; margin-bottom:3px;'>TICKET CODE</div>
                        <div class='ticket-id'>{$ticketId}</div>
                    </div>
                    <div style='text-align:right;'>
                        <div style='font-size:10px; color:#9ca3af; margin-bottom:3px;'>ISSUED</div>
                        <div style='font-size:11px; color:#4b5563;'>{$generatedAt}</div>
                    </div>
                </div>
                <div class='ticket-footer'>
                    Valid for one-time entry only &bull; Non-refundable &bull; Non-transferable &bull; &copy; " . date('Y') . " Eventra
                </div>
            </div>
        </div>
    </body>
    </html>
    ";

    $dompdf->loadHtml($html);
    $dompdf->setPaper('A5', 'landscape');
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
