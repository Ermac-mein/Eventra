<?php

/**
 * Email Helper using PHPMailer
 * Supports HTML emails with optional PDF attachments
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../config/email.php';

class EmailHelper
{
    /**
     * Send an email with optional file attachments
 *
 * @param string $to          Recipient email address
 * @param string $subject     Email subject
 * @param string $body        Email HTML content
 * @param array  $attachments Array of absolute file paths to attach
 * @param string $altBody     Plain-text fallback (auto-generated if empty)
 * @return array ['success' => bool, 'message' => string]
 */
    public static function sendEmail(string $to, string $subject, string $body, array $attachments = [], string $altBody = ''): array
{
    if (empty(SMTP_HOST) || empty(SMTP_USER) || empty(SMTP_PASS)) {
        error_log('[Email Helper] Error: SMTP credentials not configured.');
        return ['success' => false, 'message' => 'SMTP credentials not configured. Please contact the administrator.'];
    }

    $mail = new PHPMailer(true);

    try {
        // ── Server settings ───────────────────────────────────
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USER;
        $mail->Password   = SMTP_PASS;
        $mail->SMTPSecure = SMTP_SECURE;
        $mail->Port       = (int) SMTP_PORT;

        // ── Recipients ────────────────────────────────────────
        $mail->setFrom(EMAIL_FROM, EMAIL_FROM_NAME);
        $mail->addAddress($to);

        // ── Attachments ───────────────────────────────────────
        foreach ($attachments as $filePath) {
            if (file_exists($filePath)) {
                $mail->addAttachment($filePath);
            } else {
                error_log("[Email Helper] Attachment not found: {$filePath}");
            }
        }

        // ── Content ───────────────────────────────────────────
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $body;
        $mail->AltBody = $altBody ?: strip_tags($body);

        $mail->send();

        error_log("[Email Helper] Email sent to: {$to} | Subject: {$subject}");
        return ['success' => true, 'message' => 'Email sent successfully'];

    } catch (Exception $e) {
        error_log("[Email Helper] Mailer error to {$to}: {$mail->ErrorInfo}");
        return ['success' => false, 'message' => "Email delivery failed: {$mail->ErrorInfo}"];
    }
}

/**
 * Send Ticket Purchase Confirmation Email (simple/legacy wrapper)
 *
 * @param string $to
 * @param string $userName
 * @param string $eventName
 * @param string $barcode
 * @param string $pdfPath   Optional path to PDF ticket attachment
 * @return array
 */
    public static function sendTicketEmail(string $to, string $userName, string $eventName, string $barcode, string $pdfPath = ''): array
{
    $subject     = "Your Ticket for {$eventName}";
    $safeUser    = htmlspecialchars($userName,  ENT_QUOTES, 'UTF-8');
    $safeEvent   = htmlspecialchars($eventName, ENT_QUOTES, 'UTF-8');
    $safeBarcode = htmlspecialchars($barcode,   ENT_QUOTES, 'UTF-8');
    $year        = date('Y');

    $body = <<<HTML
    <div style="font-family: Arial, sans-serif; max-width: 600px; margin: auto; padding: 20px; border: 1px solid #eee;">
        <h2 style="color: #2ecc71;">Ticket Confirmation</h2>
        <p>Hi <strong>{$safeUser}</strong>,</p>
        <p>Thank you for your purchase! Your ticket for <strong>{$safeEvent}</strong> is ready.</p>
        <div style="background: #f9f9f9; padding: 20px; text-align: center; border-radius: 10px; margin: 20px 0;">
            <p style="margin-bottom: 5px; color: #666;">Ticket ID</p>
            <div style="font-size: 24px; font-weight: bold; letter-spacing: 5px; color: #2ecc71;">{$safeBarcode}</div>
        </div>
        <p>Your PDF ticket is attached to this email. Please present the QR code at the venue entrance.</p>
        <hr style="border: 0; border-top: 1px solid #eee; margin: 20px 0;">
        <p style="font-size: 12px; color: #999; text-align: center;">
            &copy; {$year} Eventra. All rights reserved.
        </p>
    </div>
    HTML;

    $attachments = ($pdfPath && file_exists($pdfPath)) ? [$pdfPath] : [];
    return self::sendEmail($to, $subject, $body, $attachments);
}

/**
 * Build the ticket HTML body for use in emails or PDF generation.
 * Extracted into its own function so it can be reused by both
 * sendTicketEmailFull() and any standalone PDF renderer.
 *
 * @param array  $ticketData  Keys: barcode, event_name, event_date, event_time,
 *                            location, state, user_name, order_id, amount,
 *                            ticket_type, ticket_type_display, qr_base64
 * @param string $downloadUrl Optional URL for the PDF download button
 * @return string             Complete HTML document as a string
 */
    public static function buildTicketHtml(array $ticketData, string $downloadUrl = ''): string
{
    // ── Extract & sanitise variables ──────────────────────────
    $barcode          = htmlspecialchars($ticketData['barcode']             ?? '',         ENT_QUOTES, 'UTF-8');
    $eventTitle       = htmlspecialchars($ticketData['event_name']          ?? 'Your Event', ENT_QUOTES, 'UTF-8');
    $userName         = htmlspecialchars($ticketData['user_name']           ?? 'Attendee', ENT_QUOTES, 'UTF-8');
    $location         = htmlspecialchars($ticketData['location']            ?? 'See event details', ENT_QUOTES, 'UTF-8');
    $state            = htmlspecialchars($ticketData['state']               ?? '',         ENT_QUOTES, 'UTF-8');
    $ticketType       = htmlspecialchars($ticketData['ticket_type']         ?? '',         ENT_QUOTES, 'UTF-8');
    $ticketTypeDisplay = htmlspecialchars($ticketData['ticket_type_display'] ?? '',        ENT_QUOTES, 'UTF-8');
    $qrBase64         = $ticketData['qr_base64'] ?? ''; // Already a base64 data URI — do NOT escape
    $amount           = !empty($ticketData['amount'])
                        ? '&#8358;' . number_format((float)$ticketData['amount'], 2)
                        : '';
    $year             = date('Y');

    // Format date / time safely
    $eventDate = !empty($ticketData['event_date'])
                 ? htmlspecialchars(date('D, d M Y', strtotime($ticketData['event_date'])), ENT_QUOTES, 'UTF-8')
                 : 'TBC';
    $eventTime = !empty($ticketData['event_time'])
                 ? htmlspecialchars(date('g:i A', strtotime($ticketData['event_time'])), ENT_QUOTES, 'UTF-8')
                 : 'TBC';

    // ── Badge class for ticket type ───────────────────────────
    $badgeClass = '';
    if (!empty($ticketTypeDisplay)) {
        $lower      = strtolower($ticketTypeDisplay);
        $badgeClass = str_contains($lower, 'vip')  ? 'vip'
                    : (str_contains($lower, 'free') ? 'free' : '');
    }
    $badgeHtml = !empty($ticketTypeDisplay)
        ? '<span class="event-type-badge ' . $badgeClass . '">' . $ticketTypeDisplay . '</span>'
        : '';

    // ── Optional extra detail rows ────────────────────────────
    $stateRow      = $state      ? '<div class="detail-item"><span class="detail-label">State</span><span class="detail-value">' . $state      . '</span></div>' : '';
    $amountRow     = $amount     ? '<div class="detail-item"><span class="detail-label">Price</span><span class="detail-value price">' . $amount . '</span></div>' : '';
    $ticketTypeRow = $ticketType ? '<div class="detail-item"><span class="detail-label">Ticket Type</span><span class="detail-value">' . $ticketType . '</span></div>' : '';

    // ── QR code img or placeholder ────────────────────────────
    $qrHtml = $qrBase64
        ? '<img src="' . $qrBase64 . '" class="qr-code" alt="QR Code">'
        : '<div class="qr-placeholder">QR</div>';

    // ── Download button (email only) ──────────────────────────
    $dlButton = $downloadUrl
        ? '<a href="' . htmlspecialchars($downloadUrl, ENT_QUOTES, 'UTF-8') . '" class="dl-button">Download PDF Ticket</a>'
        : '';

    // ── Build HTML via heredoc (no PHP tags inside the string) ─
    return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Event Ticket &mdash; {$eventTitle}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Barlow+Condensed:wght@400;600;700;800&family=Barlow:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        /* ── Reset ──────────────────────────────────────────── */
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        @page { margin: 0; size: A4 landscape; }

        html, body {
            width: 100%;
            min-height: 100%;
            background: #e8e8e8;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 30px 20px;
            font-family: 'Barlow', sans-serif;
        }

        /* ── Outer wrapper ──────────────────────────────────── */
        .ticket-wrapper {
            width: 100%;
            max-width: 820px;
            filter: drop-shadow(0 25px 50px rgba(0,0,0,0.35));
        }

        /* ── Ticket shell ───────────────────────────────────── */
        .ticket {
            display: flex;
            width: 100%;
            border-radius: 12px;
            overflow: hidden;
            background: #111111;
            min-height: 280px;
            position: relative;
        }

        /* ── Notch cutouts on the divider ───────────────────── */
        .ticket::before,
        .ticket::after {
            content: '';
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            width: 26px;
            height: 26px;
            background: #e8e8e8;
            border-radius: 50%;
            z-index: 10;
        }
        .ticket::before { left: calc(100% - 220px - 13px); }
        .ticket::after  { left: calc(100% - 220px - 13px); display: none; } /* single notch pair */

        /* ── Left: main ticket body ─────────────────────────── */
        .ticket-body {
            width: 70%;
            display: flex;
            flex-direction: column;
            padding: 0;
            background: #111111;
            position: relative;
            overflow: hidden;
        }

        /* Decorative diagonal accent stripe */
        .ticket-body::before {
            content: '';
            position: absolute;
            top: 0; right: 0;
            width: 180px; height: 100%;
            background: linear-gradient(135deg, transparent 40%, rgba(212,175,55,0.06) 100%);
            pointer-events: none;
        }

        /* Corner dots pattern */
        .ticket-body::after {
            content: '';
            position: absolute;
            bottom: 16px; left: 32px;
            width: 60px; height: 30px;
            background-image: radial-gradient(circle, rgba(212,175,55,0.4) 1.5px, transparent 1.5px);
            background-size: 10px 10px;
            pointer-events: none;
        }

        /* ── Gold top bar ───────────────────────────────────── */
        .gold-bar {
            height: 5px;
            background: linear-gradient(90deg, #d4af37, #f5d87a, #c9a227);
            flex-shrink: 0;
        }

        /* ── Body inner padding ─────────────────────────────── */
        .body-inner {
            padding: 28px 32px 24px;
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            position: relative;
            z-index: 2;
        }

        /* Brand line */
        .brand-line {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 14px;
        }
        .brand-name {
            font-family: 'Bebas Neue', sans-serif;
            font-size: 13px;
            letter-spacing: 4px;
            color: #d4af37;
        }
        .brand-divider {
            flex: 1;
            height: 1px;
            background: linear-gradient(90deg, rgba(212,175,55,0.4), transparent);
        }
        .admission-label {
            font-family: 'Barlow Condensed', sans-serif;
            font-size: 10px;
            letter-spacing: 2px;
            text-transform: uppercase;
            color: rgba(212,175,55,0.6);
        }

        /* Event title */
        .event-title {
            font-family: 'Bebas Neue', sans-serif;
            font-size: 40px;
            line-height: 1.1;
            color: #ffffff;
            letter-spacing: 1px;
            text-transform: uppercase;
            margin-bottom: 10px;
        }

        /* Badge */
        .event-type-badge {
            display: inline-block;
            background: #d4af37;
            color: #111111;
            font-family: 'Barlow Condensed', sans-serif;
            font-size: 9px;
            font-weight: 800;
            letter-spacing: 2px;
            text-transform: uppercase;
            padding: 4px 12px;
            border-radius: 20px;
            margin-bottom: 18px;
        }
        .event-type-badge.vip  { background: #e63946; color: #fff; }
        .event-type-badge.free { background: #2a9d8f; color: #fff; }

        /* Details grid */
        .details-grid {
            display: block;
            margin-bottom: 20px;
        }
        .detail-item {
            display: inline-block;
            vertical-align: top;
            width: 30%;
            min-width: 100px;
            margin-bottom: 14px;
            margin-right: 2%;
        }
        .detail-label {
            font-family: 'Barlow Condensed', sans-serif;
            font-size: 9px;
            font-weight: 700;
            letter-spacing: 2px;
            text-transform: uppercase;
            color: rgba(255,255,255,0.35);
            display: block;
            margin-bottom: 4px;
        }
        .detail-value {
            font-family: 'Barlow Condensed', sans-serif;
            font-size: 16px;
            font-weight: 600;
            color: #d4af37;
            line-height: 1.2;
            display: block;
        }
        .detail-value.price {
            font-size: 18px;
            font-weight: 700;
        }

        /* Attendee strip */
        .attendee-strip {
            display: flex;
            align-items: center;
            gap: 14px;
            padding-top: 16px;
            border-top: 1px solid rgba(212,175,55,0.2);
        }
        .attendee-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            border: 1px solid rgba(212,175,55,0.4);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        .attendee-avatar svg { width: 16px; height: 16px; fill: #d4af37; opacity: 0.7; }
        .attendee-info {}
        .attendee-label {
            font-size: 9px;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            color: rgba(255,255,255,0.3);
            margin-bottom: 2px;
        }
        .attendee-name {
            font-family: 'Barlow Condensed', sans-serif;
            font-size: 18px;
            font-weight: 700;
            color: #ffffff;
            letter-spacing: 0.5px;
        }

        /* ── Perforated divider ─────────────────────────────── */
        .perforation {
            width: 2px;
            background: repeating-linear-gradient(
                to bottom,
                transparent,
                transparent 6px,
                rgba(255,255,255,0.12) 6px,
                rgba(255,255,255,0.12) 12px
            );
            position: relative;
            flex-shrink: 0;
        }
        /* Semicircle notches cut into the ticket edges */
        .perforation::before,
        .perforation::after {
            content: '';
            position: absolute;
            left: 50%;
            transform: translateX(-50%);
            width: 22px;
            height: 11px;
            background: #e8e8e8;
            z-index: 5;
        }
        .perforation::before { top: -1px;  border-radius: 0 0 11px 11px; }
        .perforation::after  { bottom: -1px; border-radius: 11px 11px 0 0; }

        /* ── Right: stub / QR section ───────────────────────── */
        .ticket-stub {
            width: 30%;
            flex-shrink: 0;
            background: #1a1a1a;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 24px 20px;
            gap: 14px;
            text-align: center;
            position: relative;
        }

        /* Subtle corner accent */
        .ticket-stub::before {
            content: 'EVENTRA';
            position: absolute;
            top: 14px;
            left: 50%;
            transform: translateX(-50%);
            font-family: 'Bebas Neue', sans-serif;
            font-size: 10px;
            letter-spacing: 3px;
            color: rgba(212,175,55,0.35);
            white-space: nowrap;
        }

        .stub-title {
            font-family: 'Barlow Condensed', sans-serif;
            font-size: 9px;
            font-weight: 700;
            letter-spacing: 2.5px;
            text-transform: uppercase;
            color: rgba(255,255,255,0.35);
            margin-top: 12px;
        }

        /* QR frame */
        .qr-frame {
            width: 140px;
            height: 140px;
            background: #ffffff;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 8px;
            position: relative;
        }

        /* Corner decorators on QR frame */
        .qr-frame::before,
        .qr-frame::after {
            content: '';
            position: absolute;
            width: 14px;
            height: 14px;
            border-color: #d4af37;
            border-style: solid;
        }
        .qr-frame::before {
            top: -3px; left: -3px;
            border-width: 2px 0 0 2px;
        }
        .qr-frame::after {
            bottom: -3px; right: -3px;
            border-width: 0 2px 2px 0;
        }

        .qr-code {
            width: 124px;
            height: 124px;
            display: block;
            image-rendering: pixelated;
        }
        .qr-placeholder {
            width: 124px;
            height: 124px;
            background: #eee;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Bebas Neue', sans-serif;
            font-size: 13px;
            letter-spacing: 2px;
            color: #aaa;
        }

        /* Barcode / ticket ID */
        .ticket-code {
            font-family: 'Barlow Condensed', sans-serif;
            font-size: 11px;
            font-weight: 600;
            color: rgba(255,255,255,0.5);
            letter-spacing: 1.5px;
            word-break: break-all;
            line-height: 1.4;
        }
        .scan-note {
            font-size: 8px;
            color: rgba(255,255,255,0.2);
            letter-spacing: 1px;
            text-transform: uppercase;
        }

        /* ── Footer strip ───────────────────────────────────── */
        .ticket-footer {
            background: #0a0a0a;
            padding: 8px 32px;
            font-size: 8px;
            color: rgba(255,255,255,0.2);
            letter-spacing: 0.5px;
            text-align: center;
        }

        /* ── Download button (email view) ───────────────────── */
        .dl-button {
            display: inline-block;
            margin-top: 16px;
            padding: 11px 24px;
            background: linear-gradient(135deg, #d4af37, #f5d87a);
            color: #111;
            text-decoration: none;
            border-radius: 6px;
            font-family: 'Barlow Condensed', sans-serif;
            font-size: 13px;
            font-weight: 700;
            letter-spacing: 1px;
            text-transform: uppercase;
        }

        /* ── Responsive ─────────────────────────────────────── */
        @media (max-width: 640px) {
            html, body { padding: 16px 10px; }

            .ticket { flex-direction: column; }
            .ticket::before, .ticket::after { display: none; }

            .perforation {
                width: 100%;
                height: 2px;
                background: repeating-linear-gradient(
                    to right,
                    transparent,
                    transparent 6px,
                    rgba(255,255,255,0.12) 6px,
                    rgba(255,255,255,0.12) 12px
                );
            }
            .perforation::before, .perforation::after {
                top: 50%;
                left: auto;
                transform: translateY(-50%);
                width: 11px;
                height: 22px;
            }
            .perforation::before { left: -1px;  top: 50%; border-radius: 0 11px 11px 0; }
            .perforation::after  { right: -1px; top: 50%; left: auto; border-radius: 11px 0 0 11px; }

            .ticket-stub {
                width: 100%;
                border-left: none;
                border-top: none;
                padding: 20px;
                flex-direction: row;
                flex-wrap: wrap;
                justify-content: center;
                gap: 16px;
            }
            .ticket-stub::before { display: none; }
            .stub-title { width: 100%; margin-top: 0; }
            .qr-frame { width: 110px; height: 110px; }
            .qr-code, .qr-placeholder { width: 94px; height: 94px; }

            .details-grid { display: block; }
            .detail-item { width: 45%; margin-bottom: 12px; }
            .event-title { font-size: 32px; }
            .body-inner { padding: 20px; }
        }

        @media (max-width: 380px) {
            .detail-item { width: 100%; margin-right: 0; }
            .event-title { font-size: 26px; }
        }

        @media print {
            html, body { background: white; padding: 0; }
            .ticket-wrapper { filter: none; }
            .dl-button { display: none; }
        }
    </style>
</head>
<body>
<div class="ticket-wrapper">

    <!-- ── Main Ticket ─────────────────────────────────────── -->
    <div class="ticket">

        <!-- Left body -->
        <div class="ticket-body">
            <div class="gold-bar"></div>

            <div class="body-inner">
                <!-- Brand / admission label -->
                <div class="brand-line">
                    <span class="brand-name">Eventra</span>
                    <span class="brand-divider"></span>
                    <span class="admission-label">Admission Ticket</span>
                </div>

                <!-- Event name -->
                <h1 class="event-title">{$eventTitle}</h1>

                <!-- Ticket type badge (optional) -->
                {$badgeHtml}

                <!-- Event details grid -->
                <div class="details-grid">
                    <div class="detail-item">
                        <span class="detail-label">Date</span>
                        <span class="detail-value">{$eventDate}</span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Time</span>
                        <span class="detail-value">{$eventTime}</span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Venue</span>
                        <span class="detail-value">{$location}</span>
                    </div>
                    {$stateRow}
                    {$amountRow}
                    {$ticketTypeRow}
                </div>

                <!-- Attendee strip -->
                <div class="attendee-strip">
                    <div class="attendee-avatar">
                        <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path d="M12 12c2.7 0 4.8-2.1 4.8-4.8S14.7 2.4 12 2.4 7.2 4.5 7.2 7.2 9.3 12 12 12zm0 2.4c-3.2 0-9.6 1.6-9.6 4.8v2.4h19.2v-2.4c0-3.2-6.4-4.8-9.6-4.8z"/>
                        </svg>
                    </div>
                    <div class="attendee-info">
                        <div class="attendee-label">Ticket Holder</div>
                        <div class="attendee-name">{$userName}</div>
                    </div>
                </div>
            </div>

            <!-- Footer inside ticketOptional URL for the body -->
            <div class="ticket-footer">
                This ticket is valid only for the event date and time specified above &bull;
                Non-transferable without organiser approval &bull; &copy; {$year} Eventra
            </div>
        </div>

        <!-- Perforated divider -->
        <div class="perforation"></div>

        <!-- Right stub -->
        <div class="ticket-stub">
            <span class="stub-title">Scan to Enter</span>

            <div class="qr-frame">
                {$qrHtml}
            </div>

            <div class="ticket-code">{$barcode}</div>
            <div class="scan-note">Present at venue entry</div>
        </div>
    </div>
    {$dlButton}
</div>
</body>
</html>
HTML;
}

/**
 * sendTicketEmailFull — Rich marketplace ticket email with full event details,
 * styled inline preview, PDF attachment, and a download link.
 *
 * @param string       $to
 * @param array        $ticketData  Keys: barcode, event_name, event_date, event_time,
 *                                  location, state, user_name, order_id, amount,
 *                                  ticket_type, ticket_type_display, qr_base64
 * @param string|array $pdfPath     Absolute path(s) to generated PDF ticket(s)
 * @return array
 */
    public static function sendTicketEmailFull(string $to, array $ticketData, string|array $pdfPath = ''): array
{
    $eventName = htmlspecialchars($ticketData['event_name'] ?? 'Your Event', ENT_QUOTES, 'UTF-8');
    $subject   = "Your Ticket for {$eventName} — Eventra";

    // ── Build download URL ────────────────────────────────────
    $barcode     = $ticketData['barcode'] ?? '';
    $appUrl      = rtrim($_ENV['APP_URL'] ?? '', '/');
    $downloadUrl = $appUrl
        ? $appUrl . '/api/tickets/download-ticket.php?code=' . urlencode($barcode)
        : '';

    // ── Build ticket HTML body ────────────────────────────────
    $body = self::buildTicketHtml($ticketData, $downloadUrl);

    // ── Resolve attachments ───────────────────────────────────
    $attachments = [];
    if (!empty($pdfPath)) {
        $paths = is_array($pdfPath) ? $pdfPath : [$pdfPath];
        foreach ($paths as $path) {
            if (!empty($path) && file_exists($path)) {
                $attachments[] = $path;
            } else {
                error_log("[Email Helper] PDF attachment not found: {$path}");
            }
        }
    }

    return self::sendEmail($to, $subject, $body, $attachments);
}
}