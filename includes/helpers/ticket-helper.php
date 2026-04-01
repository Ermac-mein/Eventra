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
    try {
        // Build secure signed payload instead of raw barcode
        $secureToken = buildSecureQRPayload($ticketData);

        $options = new QROptions([
            'version'    => 7,
            'outputType' => QRCode::OUTPUT_MARKUP_SVG,
            'eccLevel'   => QRCode::ECC_M,
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
    $dompdf = new Dompdf($options);

    // Generate secure QR code
    $qrCodePath = generateTicketQRCode($ticketData);
    if ($qrCodePath && file_exists($qrCodePath)) {
        $qrCodeData = base64_encode(file_get_contents($qrCodePath));
        $qrCodeSrc  = 'data:image/svg+xml;base64,' . $qrCodeData;
    } else {
        // QR generation failed — use a blank placeholder so PDF still renders
        $qrCodeSrc = 'data:image/svg+xml;base64,' . base64_encode('<svg xmlns="http://www.w3.org/2000/svg" width="130" height="130"><rect width="130" height="130" fill="#f3f4f6"/><text x="65" y="65" text-anchor="middle" dominant-baseline="middle" font-size="10" fill="#9ca3af">QR Unavailable</text></svg>');
    }

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
        <meta charset='UTF-8'>
        <style>
            @page { margin: 0; padding: 0; }
            * { box-sizing: border-box; }
            body { 
                font-family: 'Helvetica', 'Arial', sans-serif; 
                margin: 0; 
                padding: 0; 
                background-color: #f3f4f6; 
                color: #1f2937;
                line-height: 1.4;
            }
            .container {
                width: 100%;
                height: 100%;
                padding: 30px;
                display: block;
            }
            .ticket {
                width: 700px;
                margin: 0 auto;
                background-color: #ffffff;
                border: 1px solid #e5e7eb;
                border-radius: 20px;
                overflow: hidden;
                position: relative;
                box-shadow: 0 10px 25px rgba(0,0,0,0.1);
            }
            .ticket::before, .ticket::after {
                content: '';
                position: absolute;
                top: 72%;
                width: 30px;
                height: 30px;
                background-color: #f3f4f6;
                border-radius: 50%;
                z-index: 10;
            }
            .ticket::before { left: -15px; }
            .ticket::after { right: -15px; }

            .header {
                background-color: #7c3aed;
                padding: 25px 40px;
                color: #ffffff;
                text-align: center;
            }
            .header img { height: 35px; }
            .header h1 { 
                margin: 0; 
                font-size: 28px; 
                letter-spacing: 5px; 
                font-weight: 800;
                text-transform: uppercase;
            }
            .header p { 
                margin: 5px 0 0; 
                font-size: 11px; 
                opacity: 0.8; 
                letter-spacing: 2px;
            }

            .hero {
                height: 150px;
                background: " . ($eventImgSrc ? "url($eventImgSrc)" : "linear-gradient(90deg, #7c3aed, #4c1d95)") . ";
                background-size: cover;
                background-position: center;
                border-bottom: 2px solid #7c3aed;
            }

            .main {
                padding: 30px 40px;
                overflow: hidden;
            }
            .event-name {
                font-size: 32px;
                font-weight: 900;
                color: #111827;
                margin-bottom: 25px;
                text-transform: uppercase;
            }
            .info-grid { width: 100%; border-collapse: collapse; }
            .info-item { vertical-align: top; padding-bottom: 20px; }
            .info-label { 
                font-size: 10px; 
                color: #6b7280; 
                text-transform: uppercase; 
                letter-spacing: 1px; 
                margin-bottom: 4px; 
                font-weight: 700;
            }
            .info-value { font-size: 16px; font-weight: 700; color: #111827; }

            .qr-col { width: 150px; text-align: right; vertical-align: top; }
            .qr-code { 
                display: inline-block; 
                background: #ffffff; 
                padding: 10px; 
                border: 1px solid #e5e7eb; 
                border-radius: 12px; 
            }
            .qr-code img { width: 130px; height: 130px; }

            .stub {
                border-top: 2px dashed #e5e7eb;
                padding: 20px 40px;
                background-color: #faf5ff;
                overflow: hidden;
            }
            .stub-item { display: inline-block; width: 32%; }
            .stub-label { font-size: 10px; color: #6b7280; text-transform: uppercase; margin-bottom: 3px; font-weight: 700; }
            .stub-value { font-size: 14px; font-weight: 700; color: #7c3aed; font-family: monospace; }

            .footer {
                padding: 12px;
                text-align: center;
                font-size: 10px;
                color: #9ca3af;
                background: #ffffff;
                border-top: 1px solid #f3f4f6;
            }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='ticket'>
                <div class='header'>
                    <h1>EVENTRA</h1>
                    <p>OFFICIAL EVENT ACCESS PASS</p>
                </div>
                " . ($eventImgSrc ? "<div class='hero'></div>" : "") . "
                <div class='main'>
                    <table style='width:100%'>
                        <tr>
                            <td style='vertical-align:top;'>
                                <div class='event-name'>{$eventName}</div>
                                <table class='info-grid'>
                                    <tr>
                                        <td class='info-item' style='width:150px;'>
                                            <div class='info-label'>Date</div>
                                            <div class='info-value'>{$eventDate}</div>
                                        </td>
                                        <td class='info-item'>
                                            <div class='info-label'>Time</div>
                                            <div class='info-value'>{$eventTime}</div>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class='info-item' colspan='2'>
                                            <div class='info-label'>Venue</div>
                                            <div class='info-value'>" . htmlspecialchars($venue) . "</div>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class='info-item'>
                                            <div class='info-label'>Attendee</div>
                                            <div class='info-value'>" . htmlspecialchars($userName) . "</div>
                                        </td>
                                        <td class='info-item'>
                                            <div class='info-label'>Ticket Type</div>
                                            <div class='info-value'>" . ucfirst($ticketData['ticket_type'] ?? 'Standard') . "</div>
                                        </td>
                                    </tr>
                                </table>
                            </td>
                            <td class='qr-col'>
                                <div class='qr-code'>
                                    <img src='{$qrCodeSrc}' alt='QR'>
                                </div>
                                <div style='font-size:9px; color:#9ca3af; margin-top:10px; text-align:center;'>
                                    SCAN TO VALIDATE ENTRY
                                </div>
                            </td>
                        </tr>
                    </table>
                </div>
                <div class='stub'>
                    <div class='stub-item'>
                        <div class='stub-label'>Ticket Ref</div>
                        <div class='stub-value'>{$ticketId}</div>
                    </div>
                    <div class='stub-item' style='text-align:center;'>
                        <div class='stub-label'>Order Status</div>
                        <div class='stub-value' style='color:#10b981'>PAID / CONFIRMED</div>
                    </div>
                    <div class='stub-item' style='text-align:right;'>
                        <div class='stub-label'>Issued On</div>
                        <div class='stub-value' style='font-size:12px; color:#4b5563; font-family:sans-serif;'>{$generatedAt}</div>
                    </div>
                </div>
                <div class='footer'>
                    This ticket is valid for one-time admission &bull; Powered by Eventra System &bull; &copy; " . date('Y') . " Eventra
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
