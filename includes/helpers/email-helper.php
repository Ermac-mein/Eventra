<?php

/**
 * Email Helper using PHPMailer
 * Supports HTML emails with optional PDF attachments.
 * Redesigned concert ticket layout.
 */

// -------------------------------------------------------------------
// 1. ROBUST PHPMailer LOADING (handles broken Composer autoloader)
// -------------------------------------------------------------------
$phpmailerLoaded = class_exists('PHPMailer\PHPMailer\PHPMailer');

if (!$phpmailerLoaded) {
    // Attempt to load Composer autoloader with error suppression
    $autoloadPath = __DIR__ . '/../../vendor/autoload.php';
    if (file_exists($autoloadPath)) {
        $prev = error_reporting(0);
        @include_once $autoloadPath;
        error_reporting($prev);
    }

    // If still not loaded, try manual inclusion of PHPMailer files
    if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
        $phpmailerBase = __DIR__ . '/../../vendor/phpmailer/phpmailer/src/';
        $files = ['Exception.php', 'PHPMailer.php', 'SMTP.php'];
        foreach ($files as $file) {
            $path = $phpmailerBase . $file;
            if (file_exists($path)) {
                include_once $path;
            }
        }
    }

    // Final check
    if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
        // Define a dummy class to prevent fatal errors elsewhere
        if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
            class_alias('stdClass', 'PHPMailer\PHPMailer\PHPMailer');
        }
    }
}

// Import namespaces after loading
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../../config/email.php';

class EmailHelper
{
    /**
     * Send an email with optional file attachments.
     *
     * @param string $to          Recipient email address
     * @param string $subject     Email subject
     * @param string $body        Email HTML content
     * @param array  $attachments Array of absolute file paths to attach
     * @param string $altBody     Plain-text fallback (auto-generated if empty)
     * @return array ['success' => bool, 'message' => string]
     */
    public static function sendEmail(
        string $to,
        string $subject,
        string $body,
        array $attachments = [],
        string $altBody = ''
    ): array {
        if (empty(SMTP_HOST) || empty(SMTP_USER) || empty(SMTP_PASS)) {
            error_log('[EmailHelper] SMTP credentials not configured.');
            return ['success' => false, 'message' => 'SMTP credentials not configured.'];
        }

        if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
            error_log('[EmailHelper] PHPMailer class not found. Manual loading failed.');
            return [
                'success' => false,
                'message' => 'Email service is currently unavailable. Please contact support or try again later.'
            ];
        }

        $mail = new PHPMailer(true);

        try {
            $mail->isSMTP();
            $mail->Host = SMTP_HOST;
            $mail->SMTPAuth = true;
            $mail->Username = SMTP_USER;
            $mail->Password = SMTP_PASS;
            $mail->SMTPSecure = SMTP_SECURE;
            $mail->Port = (int) SMTP_PORT;

            $mail->setFrom(EMAIL_FROM, EMAIL_FROM_NAME);
            $mail->addAddress($to);

            foreach ($attachments as $filePath) {
                if (file_exists($filePath)) {
                    $mail->addAttachment($filePath);
                } else {
                    error_log("[EmailHelper] Attachment not found: {$filePath}");
                }
            }

            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $body;
            $mail->AltBody = $altBody ?: strip_tags($body);

            $mail->send();
            error_log("[EmailHelper] Sent to: {$to} | Subject: {$subject}");
            return ['success' => true, 'message' => 'Email sent successfully'];

        } catch (Exception $ex) {
            error_log("[EmailHelper] Mailer error to {$to}: {$mail->ErrorInfo}");
            return ['success' => false, 'message' => "Email delivery failed: {$mail->ErrorInfo}"];
        }
    }

    /* ─────────────────────────────────────────────────────────────
     *  LEGACY SIMPLE CONFIRMATION WRAPPER
     * ───────────────────────────────────────────────────────────── */

    /**
     * Send a simple ticket confirmation email.
     */
    public static function sendTicketEmail(
        string $to,
        string $userName,
        string $eventName,
        string $barcode,
        string $pdfPath = ''
    ): array {
        $subject = "Your Ticket for {$eventName}";
        $safeUser = htmlspecialchars($userName, ENT_QUOTES, 'UTF-8');
        $safeEvent = htmlspecialchars($eventName, ENT_QUOTES, 'UTF-8');
        $safeBarcode = htmlspecialchars($barcode, ENT_QUOTES, 'UTF-8');
        $year = date('Y');

        $body = <<<HTML
        <div style="font-family:Arial,sans-serif;max-width:600px;margin:auto;padding:20px;border:1px solid #eee;">
            <h2 style="color:#2ecc71;">Ticket Confirmation</h2>
            <p>Hi <strong>{$safeUser}</strong>,</p>
            <p>Thank you for your purchase! Your ticket for <strong>{$safeEvent}</strong> is ready.</p>
            <div style="background:#f9f9f9;padding:20px;text-align:center;border-radius:10px;margin:20px 0;">
                <p style="margin-bottom:5px;color:#666;">Ticket ID</p>
                <div style="font-size:24px;font-weight:bold;letter-spacing:5px;color:#2ecc71;">{$safeBarcode}</div>
            </div>
            <p>Your PDF ticket is attached. Please present the QR code at the venue entrance.</p>
            <hr style="border:0;border-top:1px solid #eee;margin:20px 0;">
            <p style="font-size:12px;color:#999;text-align:center;">&copy; {$year} Eventra. All rights reserved.</p>
        </div>
        HTML;

        $attachments = ($pdfPath && file_exists($pdfPath)) ? [$pdfPath] : [];
        return self::sendEmail($to, $subject, $body, $attachments);
    }

    /* ─────────────────────────────────────────────────────────────
     *  PRIVATE HELPERS
     * ───────────────────────────────────────────────────────────── */

    private static function esc(string $v): string
    {
        return htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private static function detailRow(string $label, string $value, bool $priceStyle = false): string
    {
        $valueStyle = $priceStyle
            ? 'font-family:\'Barlow Condensed\',Arial,sans-serif;font-size:17px;font-weight:800;color:#d4af37;line-height:1.2;display:block;'
            : 'font-family:\'Barlow Condensed\',Arial,sans-serif;font-size:15px;font-weight:600;color:#d4af37;line-height:1.2;display:block;';

        return '<div style="margin-bottom:14px;">'
            . '<span style="display:block;font-family:\'Barlow Condensed\',Arial,sans-serif;'
            . 'font-size:9px;font-weight:700;letter-spacing:2px;text-transform:uppercase;'
            . 'color:rgba(255,255,255,0.30);margin-bottom:3px;">'
            . self::esc($label)
            . '</span>'
            . '<span style="' . $valueStyle . '">' . $value . '</span>'
            . '</div>';
    }

    /**
     * Build a modern concert‑style ticket HTML.
     * The design emphasises "LIVE CONCERT", large event title,
     * event image, and a clean QR/barcode area.
     */
    public static function buildTicketHtml(array $ticketData, string $downloadUrl = ''): string
    {
        /* ── 1. Sanitise text fields ──────────────────────────── */
        $barcode = self::esc($ticketData['barcode'] ?? '');
        $ticketId = self::esc($ticketData['ticket_id'] ?? ($ticketData['barcode'] ?? ''));
        $eventTitle = self::esc($ticketData['event_name'] ?? 'LIVE CONCERT');
        $userName = self::esc($ticketData['user_name'] ?? 'Attendee');
        $location = self::esc($ticketData['location'] ?? '—');
        $state = self::esc($ticketData['state'] ?? '');
        $organizer = self::esc($ticketData['organizer'] ?? '');
        $ticketType = self::esc($ticketData['ticket_type'] ?? '');
        $tickDispRaw = $ticketData['ticket_type_display']
            ?? ($ticketData['ticket_type'] ?? '');
        $tickDisp = self::esc($tickDispRaw);
        $year = date('Y');

        /* ── 2. Date & time ──────────────────────────────────── */
        $eventDate = !empty($ticketData['event_date'])
            ? self::esc(date('D, d M Y', strtotime((string) $ticketData['event_date'])))
            : 'TBC';
        $eventTime = !empty($ticketData['event_time'])
            ? self::esc(date('g:i A', strtotime((string) $ticketData['event_time'])))
            : 'TBC';

        /* ── 3. Price ─────────────────────────────────────────── */
        $amountDisplay = '';
        if (isset($ticketData['amount'])) {
            $amountFloat = (float) $ticketData['amount'];
            $amountDisplay = $amountFloat > 0
                ? '&#8358;' . number_format($amountFloat, 2)
                : 'Free';
        }

        /* ── 4. QR code ──────────────────────────────────────── */
        $qrRaw = trim((string) ($ticketData['qr_base64'] ?? ''));
        $qrSafe = (str_starts_with($qrRaw, 'data:image/') && strlen($qrRaw) > 100)
            ? $qrRaw
            : '';

        $qrHtml = $qrSafe !== ''
            ? '<img src="' . $qrSafe . '" alt="QR Code"
                   width="130" height="130"
                   style="width:130px;height:130px;display:block;image-rendering:pixelated;">'
            : '<div style="width:130px;height:130px;background:#e0e0e0;
                           display:flex;align-items:center;justify-content:center;
                           font-size:10px;color:#aaa;letter-spacing:1px;
                           border-radius:4px;">NO QR</div>';

        /* ── 5. Event banner image ────────────────────────────── */
        $imgRaw = trim((string) ($ticketData['event_image'] ?? ''));
        $imgSafe = ($imgRaw !== '' && (
            str_starts_with($imgRaw, 'data:image/') ||
            str_starts_with($imgRaw, 'http://') ||
            str_starts_with($imgRaw, 'https://')
        )) ? $imgRaw : '';

        $eventImgHtml = $imgSafe !== ''
            ? '<img src="' . $imgSafe . '" alt="Event" width="100%"
                   style="width:100%;height:100%;object-fit:cover;display:block;">'
            : '<div style="width:100%;height:100%;min-height:140px;
                           background:linear-gradient(135deg,#1a1a2e 0%,#0f3460 100%);
                           display:flex;align-items:center;justify-content:center;">
                   <span style="font-family:Arial,sans-serif;font-size:10px;
                                letter-spacing:3px;color:rgba(212,175,55,0.45);
                                text-transform:uppercase;">Event Image</span>
               </div>';

        /* ── 6. Ticket-type badge ─────────────────────────────── */
        $badgeBg = '#d4af37';
        $badgeFg = '#111111';
        if ($tickDisp !== '') {
            $lower = strtolower($tickDispRaw);
            if (str_contains($lower, 'vip')) {
                $badgeBg = '#c0392b';
                $badgeFg = '#ffffff';
            }
            if (str_contains($lower, 'free')) {
                $badgeBg = '#27ae60';
                $badgeFg = '#ffffff';
            }
        }
        $badgeWrapStyle = 'line-height:1;margin-bottom:16px;min-height:22px;';
        $badgeHtml = $tickDisp !== ''
            ? '<div style="' . $badgeWrapStyle . '">'
            . '<span style="display:inline-block;background:' . $badgeBg . ';color:' . $badgeFg . ';'
            . 'font-family:\'Barlow Condensed\',Arial,sans-serif;'
            . 'font-size:9px;font-weight:800;letter-spacing:2px;text-transform:uppercase;'
            . 'padding:4px 14px;border-radius:20px;">'
            . $tickDisp
            . '</span></div>'
            : '<div style="' . $badgeWrapStyle . '"></div>';

        /* ── 7. Build Col A (left detail column) ─────────────── */
        $colA = self::detailRow('Date', $eventDate);
        $colA .= self::detailRow('Time', $eventTime);
        $colA .= self::detailRow('Venue', $location);
        if ($amountDisplay !== '') {
            $colA .= self::detailRow('Price', $amountDisplay, true);
        }

        /* ── 8. Build Col B (right detail column) ────────────── */
        $colB = '';
        if ($state !== '')
            $colB .= self::detailRow('State', $state);
        if ($ticketType !== '')
            $colB .= self::detailRow('Ticket Type', $tickDisp ?: $ticketType);
        if ($organizer !== '')
            $colB .= self::detailRow('Organizer', $organizer);

        /* ── 9. Download button ──────────────────────────────── */
        $dlButtonHtml = '';
        if ($downloadUrl !== '') {
            $safeUrl = self::esc($downloadUrl);
            $dlButtonHtml = <<<BTN
            <div style="text-align:center;margin-top:28px;">
                <a href="{$safeUrl}" download="ticket.pdf"
                   style="display:inline-block;padding:13px 36px;
                          background:linear-gradient(135deg,#d4af37,#f5d87a);
                          color:#111111;text-decoration:none;border-radius:7px;
                          font-family:'Barlow Condensed',Arial,sans-serif;
                          font-size:14px;font-weight:800;letter-spacing:1.5px;
                          text-transform:uppercase;">
                    &#8675;&nbsp;Download PDF Ticket
                </a>
            </div>
            BTN;
        }

        /* ── 10. LIVE CONCERT header (bold, modern) ───────────── */
        $liveConcertLabel = 'LIVE CONCERT';

        // Full HTML with redesigned concert ticket layout
        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Concert Ticket &mdash; {$eventTitle}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Barlow+Condensed:wght@400;600;700;800;900&family=Barlow:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        @page { margin: 0; size: A4 landscape; }

        html, body {
            width: 100%;
            min-height: 100%;
            background: #1a1a1a;
            font-family: 'Barlow', Arial, sans-serif;
            padding: 40px 20px;
        }

        .ticket-wrapper {
            width: 100%;
            max-width: 900px;
            margin: 0 auto;
            filter: drop-shadow(0 20px 40px rgba(0,0,0,0.6));
        }

        .ticket-table {
            width: 100%;
            border-collapse: collapse;
            border-spacing: 0;
            border-radius: 16px;
            overflow: hidden;
            background: #0b0b0b;
        }

        .td-main {
            width: 60%;
            background: #111111;
            vertical-align: top;
            padding: 0;
        }

        .gold-accent {
            height: 6px;
            background: linear-gradient(90deg, #c9a227 0%, #f5d87a 50%, #c9a227 100%);
        }

        .main-inner {
            padding: 28px 28px 20px 28px;
        }

        .live-concert-badge {
            display: inline-block;
            font-family: 'Bebas Neue', cursive;
            font-size: 14px;
            letter-spacing: 6px;
            color: #d4af37;
            border: 1.5px solid #d4af37;
            padding: 6px 18px;
            margin-bottom: 20px;
            text-transform: uppercase;
        }

        .event-title {
            font-family: 'Bebas Neue', cursive;
            font-size: 52px;
            line-height: 1.0;
            color: #ffffff;
            letter-spacing: 2px;
            text-transform: uppercase;
            margin-bottom: 10px;
            word-break: break-word;
        }

        .details-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            margin-top: 20px;
        }
        .detail-col {
            flex: 1;
            min-width: 150px;
        }

        .holder-section {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid rgba(212,175,55,0.2);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .holder-info h4 {
            font-size: 9px;
            letter-spacing: 2px;
            text-transform: uppercase;
            color: rgba(255,255,255,0.3);
            margin-bottom: 4px;
        }
        .holder-info .name {
            font-family: 'Barlow Condensed', sans-serif;
            font-size: 20px;
            font-weight: 800;
            color: #fff;
        }
        .ticket-id {
            text-align: right;
        }
        .ticket-id .label {
            font-size: 9px;
            letter-spacing: 2px;
            text-transform: uppercase;
            color: rgba(255,255,255,0.3);
        }
        .ticket-id .value {
            font-family: 'Barlow Condensed', sans-serif;
            font-size: 14px;
            font-weight: 700;
            color: #d4af37;
        }

        /* Perforation */
        .td-perf {
            width: 2px;
            background: repeating-linear-gradient(
                to bottom,
                transparent 0px,
                transparent 8px,
                rgba(255,255,255,0.15) 8px,
                rgba(255,255,255,0.15) 16px
            );
            vertical-align: top;
        }

        /* Right side (image + QR) */
        .td-stub {
            width: 40%;
            background: #181818;
            vertical-align: top;
        }

        .stub-image {
            height: 180px;
            overflow: hidden;
            position: relative;
        }
        .stub-image::after {
            content: '';
            position: absolute;
            bottom: 0; left: 0; right: 0;
            height: 30px;
            background: linear-gradient(to top, #181818, transparent);
        }

        .qr-section {
            padding: 20px 20px 25px;
            text-align: center;
        }
        .qr-label {
            font-family: 'Barlow Condensed', sans-serif;
            font-size: 10px;
            letter-spacing: 3px;
            text-transform: uppercase;
            color: rgba(255,255,255,0.3);
            margin-bottom: 12px;
        }
        .qr-frame {
            width: 150px;
            height: 150px;
            background: white;
            border-radius: 12px;
            margin: 0 auto 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 8px;
        }
        .barcode-text {
            font-family: 'Barlow Condensed', sans-serif;
            font-size: 12px;
            font-weight: 700;
            color: #d4af37;
            letter-spacing: 1.5px;
        }

        @media (max-width: 600px) {
            .ticket-table, .ticket-table tbody, .ticket-table tr,
            .td-main, .td-perf, .td-stub {
                display: block !important;
                width: 100% !important;
            }
            .td-perf {
                height: 2px !important;
                background: repeating-linear-gradient(to right,
                    transparent 0px, transparent 8px,
                    rgba(255,255,255,0.15) 8px, rgba(255,255,255,0.15) 16px) !important;
            }
            .stub-image { height: 160px; }
            .event-title { font-size: 36px; }
        }

        @media print {
            html, body { background: white; padding: 0; }
            .ticket-wrapper { filter: none; }
            .dl-btn-wrap { display: none !important; }
        }
    </style>
</head>
<body>
<div class="ticket-wrapper">
    <table class="ticket-table" cellpadding="0" cellspacing="0" border="0" role="presentation">
        <tbody>
        <tr>
            <td class="td-main">
                <span class="gold-accent" style="display:block;"></span>
                <div class="main-inner">
                    <div class="live-concert-badge">{$liveConcertLabel}</div>
                    <h1 class="event-title">{$eventTitle}</h1>
                    {$badgeHtml}
                    <div class="details-grid">
                        <div class="detail-col">{$colA}</div>
                        <div class="detail-col">{$colB}</div>
                    </div>
                    <div class="holder-section">
                        <div class="holder-info">
                            <h4>Ticket Holder</h4>
                            <div class="name">{$userName}</div>
                        </div>
                        <div class="ticket-id">
                            <div class="label">Ticket ID</div>
                            <div class="value">{$ticketId}</div>
                        </div>
                    </div>
                </div>
            </td>
            <td class="td-perf"></td>
            <td class="td-stub">
                <div class="stub-image">
                    {$eventImgHtml}
                </div>
                <div class="qr-section">
                    <div class="qr-label">SCAN TO ENTER</div>
                    <div class="qr-frame">
                        {$qrHtml}
                    </div>
                    <div class="barcode-text">{$barcode}</div>
                </div>
            </td>
        </tr>
        </tbody>
    </table>
    {$dlButtonHtml}
</div>
</body>
</html>
HTML;
    }

    /* ─────────────────────────────────────────────────────────────
     *  FULL TICKET EMAIL SENDER
     * ───────────────────────────────────────────────────────────── */

    /**
     * Send a registration OTP email.
     *
     * @param string $to Recipient email
     * @param string $name Recipient name
     * @param string $otp 6-digit OTP
     * @return array
     */
    public static function sendRegistrationOTP(string $to, string $name, string $otp): array
    {
        $subject = "Verify your Eventra account — OTP: $otp";
        $safeName = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
        $year = date('Y');

        $body = <<<HTML
        <div style="font-family:'Plus Jakarta Sans',Arial,sans-serif;max-width:600px;margin:auto;padding:40px;background-color:#ffffff;border-radius:16px;box-shadow:0 4px 24px rgba(0,0,0,0.06);border:1px solid #f1f5f9;">
            <div style="text-align:center;margin-bottom:32px;">
                <h1 style="color:#2ecc71;margin:0;font-size:28px;font-weight:800;letter-spacing:-0.5px;">Eventra</h1>
                <p style="color:#64748b;margin-top:8px;font-size:14px;">Bringing your events to life</p>
            </div>
            
            <h2 style="color:#1e293b;font-size:20px;font-weight:700;margin-bottom:16px;">Confirm your email address</h2>
            <p style="color:#475569;font-size:16px;line-height:1.6;margin-bottom:24px;">Hi <strong>{$safeName}</strong>,</p>
            <p style="color:#475569;font-size:16px;line-height:1.6;margin-bottom:32px;">To complete your registration and start creating amazing events, please use the following verification code:</p>
            
            <div style="background:#f8fafc;padding:32px;text-align:center;border-radius:12px;margin:32px 0;border:1px solid #e2e8f0;">
                <p style="margin:0 0 12px 0;color:#64748b;font-size:12px;text-transform:uppercase;letter-spacing:2px;font-weight:700;">Verification Code</p>
                <div style="font-size:48px;font-weight:800;letter-spacing:8px;color:#1e293b;font-family:monospace;">{$otp}</div>
            </div>
            
            <p style="color:#64748b;font-size:14px;line-height:1.6;margin-bottom:32px;">This code will expire in 15 minutes. If you didn't request this email, you can safely ignore it.</p>
            
            <hr style="border:0;border-top:1px solid #f1f5f9;margin:32px 0;">
            
            <div style="text-align:center;">
                <p style="font-size:12px;color:#94a3b8;margin:0;">&copy; {$year} Eventra Inc. All rights reserved.</p>
                <p style="font-size:11px;color:#cbd5e1;margin-top:8px;">You are receiving this because you signed up for an Eventra account.</p>
            </div>
        </div>
        HTML;

        return self::sendEmail($to, $subject, $body);
    }

    /**
     * Send a rich ticket email with styled HTML ticket, PDF attachment,
     * and optional download button.
     *
     * This method fetches fresh, complete ticket data from the database
     * before sending so the email is always data-persistent and accurate.
     *
     * @param string       $to
     * @param array        $ticketData  See buildTicketHtml() for accepted keys.
     *                                  At minimum, 'barcode' must be present.
     * @param string|array $pdfPath     Absolute path(s) to the generated PDF(s)
     * @return array ['success' => bool, 'message' => string]
     */
    public static function sendTicketEmailFull(
        string $to,
        array $ticketData,
        string|array $pdfPath = ''
    ): array {
        /* ── 1. Sync from DB to ensure data persistence ───────── */
        $barcode = trim((string) ($ticketData['barcode'] ?? ''));

        if ($barcode !== '') {
            $dbConfigPath = __DIR__ . '/../../config/database.php';
            if (file_exists($dbConfigPath)) {
                require_once $dbConfigPath;
                $pdo = getPDO();

                if (isset($pdo) && $pdo instanceof \PDO) {
                    try {
                        $stmt = $pdo->prepare("
                            SELECT
                                t.barcode,
                                t.id         AS ticket_id,
                                t.status,
                                t.ticket_type,
                                e.event_name,
                                e.event_date,
                                e.event_time,
                                e.location,
                                e.address,
                                e.image_path AS event_image,
                                u.name       AS user_name,
                                p.amount,
                                p.id         AS order_id
                            FROM tickets t
                            JOIN events e  ON e.id = t.event_id
                            JOIN users  u  ON u.id = t.user_id
                            LEFT JOIN payments p ON p.id = t.payment_id
                            WHERE t.barcode = ?
                            LIMIT 1
                        ");
                        $stmt->execute([$barcode]);
                        $fresh = $stmt->fetch(\PDO::FETCH_ASSOC);

                        if ($fresh) {
                            $ticketData = array_merge(
                                $ticketData,
                                array_filter($fresh, static fn($v) => $v !== null)
                            );
                        }
                    } catch (\Throwable $dbEx) {
                        error_log('[EmailHelper] DB sync error: ' . $dbEx->getMessage());
                    }
                }
            }
        }

        /* ── 2. Subject & download URL ───────────────────────── */
        $eventName = htmlspecialchars(
            $ticketData['event_name'] ?? 'Your Event',
            ENT_QUOTES,
            'UTF-8'
        );
        $subject = "Your Ticket for {$eventName} — Eventra";

        $appUrl = rtrim((string) ($_ENV['APP_URL'] ?? ''), '/');
        $downloadUrl = ($appUrl !== '' && $barcode !== '')
            ? $appUrl . '/api/tickets/download-ticket.php?code=' . urlencode($barcode)
            : '';

        /* ── 3. Build HTML body ───────────────────────────────── */
        $body = self::buildTicketHtml($ticketData, $downloadUrl);

        /* ── 4. Resolve PDF attachments ──────────────────────── */
        $attachments = [];
        $rawPaths = is_array($pdfPath) ? $pdfPath : [$pdfPath];

        foreach ($rawPaths as $path) {
            $path = trim((string) $path);
            if ($path === '') {
                continue;
            }
            if (!file_exists($path)) {
                error_log("[EmailHelper] Attachment not found: {$path}");
                continue;
            }
            if (!in_array($path, $attachments, true)) {
                $attachments[] = $path;
            }
        }

        /* ── 5. Send ─────────────────────────────────────────── */
        return self::sendEmail($to, $subject, $body, $attachments);
    }
}

/* ─────────────────────────────────────────────────────────────────
 *  GLOBAL FUNCTION WRAPPERS
 * ───────────────────────────────────────────────────────────────── */
if (!function_exists('sendEmail')) {
    function sendEmail(string $to, string $subject, string $body, array $attachments = [], string $altBody = ''): array
    {
        return EmailHelper::sendEmail($to, $subject, $body, $attachments, $altBody);
    }
}

if (!function_exists('sendTicketEmail')) {
    function sendTicketEmail(string $to, string $userName, string $eventName, string $barcode, string $pdfPath = ''): array
    {
        return EmailHelper::sendTicketEmail($to, $userName, $eventName, $barcode, $pdfPath);
    }
}

if (!function_exists('sendTicketEmailFull')) {
    function sendTicketEmailFull(string $to, array $ticketData, string|array $pdfPath = ''): array
    {
        return EmailHelper::sendTicketEmailFull($to, $ticketData, $pdfPath);
    }
}

if (!function_exists('_detailCell')) {
    function _detailCell(string $label, string $value, string $class = ''): string
    {
        $classAttr = $class !== '' ? ' ' . htmlspecialchars($class, ENT_QUOTES, 'UTF-8') : '';
        $safeLabel = htmlspecialchars($label, ENT_QUOTES, 'UTF-8');
        return '<div class="detail-item' . $classAttr . '">'
            . '<span class="detail-label">' . $safeLabel . '</span>'
            . '<span class="detail-value">' . $value . '</span>'
            . '</div>';
    }
}