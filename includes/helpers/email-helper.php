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
        array  $attachments = [],
        string $altBody     = ''
    ): array {
        if (empty(SMTP_HOST) || empty(SMTP_USER) || empty(SMTP_PASS)) {
            error_log('[EmailHelper] SMTP credentials not configured.');
            return ['success' => false, 'message' => 'SMTP credentials not configured.'];
        }

        $mail = new PHPMailer(true);

        try {
            $mail->isSMTP();
            $mail->Host       = SMTP_HOST;
            $mail->SMTPAuth   = true;
            $mail->Username   = SMTP_USER;
            $mail->Password   = SMTP_PASS;
            $mail->SMTPSecure = SMTP_SECURE;
            $mail->Port       = (int) SMTP_PORT;

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
            $mail->Body    = $body;
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
        $subject     = "Your Ticket for {$eventName}";
        $safeUser    = htmlspecialchars($userName,  ENT_QUOTES, 'UTF-8');
        $safeEvent   = htmlspecialchars($eventName, ENT_QUOTES, 'UTF-8');
        $safeBarcode = htmlspecialchars($barcode,   ENT_QUOTES, 'UTF-8');
        $year        = date('Y');

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

    /**
     * Sanitise a string for safe HTML output.
     */
    private static function esc(string $v): string
    {
        return htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * Build one label + value row for the detail table cells.
     * Uses fully inline styles so it survives email clients and DOMPDF.
     */
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

    public static function buildTicketHtml(array $ticketData, string $downloadUrl = ''): string
    {
        /* ── 1. Sanitise text fields ──────────────────────────── */
        $barcode     = self::esc($ticketData['barcode']             ?? '');
        $ticketId    = self::esc($ticketData['ticket_id']           ?? ($ticketData['barcode'] ?? ''));
        $eventTitle  = self::esc($ticketData['event_name']          ?? 'Your Event');
        $userName    = self::esc($ticketData['user_name']           ?? 'Attendee');
        $location    = self::esc($ticketData['location']            ?? '—');
        $state       = self::esc($ticketData['state']               ?? '');
        $organizer   = self::esc($ticketData['organizer']           ?? '');
        $ticketType  = self::esc($ticketData['ticket_type']         ?? '');
        $tickDispRaw = $ticketData['ticket_type_display']
                       ?? ($ticketData['ticket_type'] ?? '');
        $tickDisp    = self::esc($tickDispRaw);
        $year        = date('Y');

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
        // IMPORTANT: Never run htmlspecialchars on a base64 data-URI —
        // it corrupts the payload.  Validate the prefix instead.
        $qrRaw  = trim((string) ($ticketData['qr_base64'] ?? ''));
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
        $imgRaw  = trim((string) ($ticketData['event_image'] ?? ''));
        // Accept data-URIs and absolute http(s) URLs
        $imgSafe = ($imgRaw !== '' && (
            str_starts_with($imgRaw, 'data:image/')  ||
            str_starts_with($imgRaw, 'http://')      ||
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
            if (str_contains($lower, 'vip'))  { $badgeBg = '#c0392b'; $badgeFg = '#ffffff'; }
            if (str_contains($lower, 'free')) { $badgeBg = '#27ae60'; $badgeFg = '#ffffff'; }
        }
        // Wrap in a block container so it never becomes a stretched flex child
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
        $colA = self::detailRow('Date',  $eventDate);
        $colA .= self::detailRow('Time',  $eventTime);
        $colA .= self::detailRow('Venue', $location);
        if ($amountDisplay !== '') {
            $colA .= self::detailRow('Price', $amountDisplay, true);
        }

        /* ── 8. Build Col B (right detail column) ────────────── */
        $colB = '';
        if ($state     !== '') $colB .= self::detailRow('State',       $state);
        if ($ticketType  !== '') $colB .= self::detailRow('Ticket Type',  $tickDisp ?: $ticketType);
        if ($organizer !== '') $colB .= self::detailRow('Organizer',    $organizer);

        /* ── 9. Download button ──────────────────────────────── */
        $dlButtonHtml = '';
        if ($downloadUrl !== '') {
            $safeUrl      = self::esc($downloadUrl);
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

        /* ── 10. Assemble HTML ───────────────────────────────── */
        // Layout uses HTML <table> for maximum compatibility with Gmail,
        // Outlook, Apple Mail and DOMPDF — all of which have incomplete
        // support for CSS flexbox/grid.
        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Event Ticket &mdash; {$eventTitle}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Barlow+Condensed:wght@400;600;700;800;900&family=Barlow:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        /* ── Reset ──────────────────────────────────────────── */
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        @page { margin: 0; size: A4 landscape; }

        html, body {
            width: 100%;
            min-height: 100%;
            background: #cfcfcf;
            font-family: 'Barlow', Arial, sans-serif;
            padding: 40px 20px;
        }

        /* ── Outer wrapper ──────────────────────────────────── */
        .ticket-wrapper {
            width: 100%;
            max-width: 860px;
            margin: 0 auto;
            filter: drop-shadow(0 20px 48px rgba(0,0,0,0.42));
        }

        /* ── Main ticket table ──────────────────────────────── */
        .ticket-table {
            width: 100%;
            border-collapse: collapse;
            border-spacing: 0;
            border-radius: 14px;
            overflow: hidden;
            /* Fallback: min-height on the row */
        }

        /* ─── LEFT BODY ─────────────────────────────────────── */
        .td-body {
            width: 65%;
            background: #111111;
            vertical-align: top;
            padding: 0;
            border-radius: 14px 0 0 14px;
            position: relative;
        }

        /* gold top bar — inside left cell */
        .gold-bar {
            height: 5px;
            background: linear-gradient(90deg, #c9a227 0%, #f5d87a 50%, #c9a227 100%);
            display: block;
        }

        /* inner scroll area */
        .body-inner {
            padding: 22px 28px 16px 28px;
            position: relative;
        }

        /* brand row */
        .brand-row {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 12px;
        }
        .brand-name {
            font-family: 'Bebas Neue', Arial, sans-serif;
            font-size: 15px;
            letter-spacing: 4px;
            color: #d4af37;
            white-space: nowrap;
            flex-shrink: 0;
        }
        .brand-sep {
            flex: 1;
            height: 1px;
            background: linear-gradient(90deg, rgba(212,175,55,0.5), transparent);
        }
        .admission-tag {
            font-family: 'Barlow Condensed', Arial, sans-serif;
            font-size: 9px;
            letter-spacing: 2px;
            text-transform: uppercase;
            color: rgba(212,175,55,0.50);
            white-space: nowrap;
            flex-shrink: 0;
        }

        /* event title */
        .event-title {
            font-family: 'Bebas Neue', Arial, sans-serif;
            font-size: 44px;
            line-height: 1.0;
            color: #ffffff;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            margin-bottom: 10px;
            word-break: break-word;
        }

        /* two-column detail table */
        .details-table {
            width: 100%;
            border-collapse: collapse;
            border-spacing: 0;
            margin-bottom: 0;
        }
        .details-table td {
            vertical-align: top;
            padding: 0;
            width: 50%;
        }
        .col-a {
            padding-right: 20px;
            border-right: 1px solid rgba(212,175,55,0.15);
        }
        .col-b {
            padding-left: 20px;
        }

        /* ── Holder bar ─────────────────────────────────────── */
        .holder-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 14px 28px;
            background: rgba(212,175,55,0.07);
            border-top: 1px solid rgba(212,175,55,0.18);
            margin-top: 18px;
        }
        .holder-left {
            display: flex;
            align-items: center;
            gap: 10px;
            min-width: 0;
        }
        .holder-avatar {
            width: 34px;
            height: 34px;
            border-radius: 50%;
            border: 1px solid rgba(212,175,55,0.38);
            background: rgba(212,175,55,0.06);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        .holder-avatar svg { width: 17px; height: 17px; fill: #d4af37; opacity: 0.72; }
        .holder-caption {
            font-size: 8px;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            color: rgba(255,255,255,0.25);
            margin-bottom: 2px;
            display: block;
        }
        .holder-name {
            font-family: 'Barlow Condensed', Arial, sans-serif;
            font-size: 17px;
            font-weight: 800;
            color: #ffffff;
            letter-spacing: 0.5px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            display: block;
        }
        .holder-id {
            text-align: right;
            flex-shrink: 0;
        }
        .holder-id-caption {
            font-size: 8px;
            letter-spacing: 1px;
            text-transform: uppercase;
            color: rgba(255,255,255,0.22);
            display: block;
            margin-bottom: 2px;
        }
        .holder-id-value {
            font-family: 'Barlow Condensed', Arial, sans-serif;
            font-size: 11px;
            font-weight: 700;
            color: rgba(212,175,55,0.70);
            letter-spacing: 1.2px;
            /* CRITICAL: prevent vertical stacking — keep on one line */
            white-space: nowrap;
            display: block;
            max-width: 180px;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        /* ─── PERFORATION CELL ──────────────────────────────── */
        .td-perf {
            width: 2px;
            background: repeating-linear-gradient(
                to bottom,
                transparent     0px,
                transparent     6px,
                rgba(255,255,255,0.13) 6px,
                rgba(255,255,255,0.13) 12px
            );
            vertical-align: top;
            position: relative;
        }
        /* top notch */
        .td-perf::before {
            content: '';
            position: absolute;
            top: -1px; left: 50%;
            transform: translateX(-50%);
            width: 22px; height: 11px;
            background: #cfcfcf;
            border-radius: 0 0 11px 11px;
            z-index: 5;
        }
        /* bottom notch */
        .td-perf::after {
            content: '';
            position: absolute;
            bottom: -1px; left: 50%;
            transform: translateX(-50%);
            width: 22px; height: 11px;
            background: #cfcfcf;
            border-radius: 11px 11px 0 0;
            z-index: 5;
        }

        /* ─── RIGHT STUB ────────────────────────────────────── */
        .td-stub {
            width: 35%;
            background: #1c1c1c;
            vertical-align: top;
            padding: 0;
            border-radius: 0 14px 14px 0;
        }

        /* inner stub layout — flex column */
        .stub-inner {
            display: flex;
            flex-direction: column;
            height: 100%;
            min-height: 300px;
        }

        /* event image: top section */
        .stub-image {
            flex: 0 0 48%;
            overflow: hidden;
            position: relative;
            min-height: 140px;
        }
        /* gradient fade at bottom of image */
        .stub-image::after {
            content: '';
            position: absolute;
            bottom: 0; left: 0; right: 0;
            height: 32px;
            background: linear-gradient(to top, #1c1c1c, transparent);
            z-index: 2;
        }

        /* QR section: bottom */
        .stub-qr {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 10px 16px 20px;
            gap: 8px;
            text-align: center;
            position: relative;
        }
        /* EVENTRA watermark */
        .stub-qr::before {
            content: 'EVENTRA';
            position: absolute;
            top: 6px; left: 50%;
            transform: translateX(-50%);
            font-family: 'Bebas Neue', Arial, sans-serif;
            font-size: 9px;
            letter-spacing: 3px;
            color: rgba(212,175,55,0.22);
            white-space: nowrap;
        }
        .scan-label {
            font-family: 'Barlow Condensed', Arial, sans-serif;
            font-size: 8px;
            font-weight: 700;
            letter-spacing: 2.5px;
            text-transform: uppercase;
            color: rgba(255,255,255,0.28);
            margin-top: 16px;
        }

        /* QR white frame with gold corner brackets */
        .qr-frame {
            width: 148px;
            height: 148px;
            background: #ffffff;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 9px;
            position: relative;
            flex-shrink: 0;
        }
        .qr-frame::before,
        .qr-frame::after {
            content: '';
            position: absolute;
            width: 16px; height: 16px;
            border-color: #d4af37;
            border-style: solid;
        }
        .qr-frame::before {
            top: -3px; left: -3px;
            border-width: 2px 0 0 2px;
            border-radius: 2px 0 0 0;
        }
        .qr-frame::after {
            bottom: -3px; right: -3px;
            border-width: 0 2px 2px 0;
            border-radius: 0 0 2px 0;
        }

        /* Barcode text — CRITICAL: must NOT go vertical */
        .ticket-code-text {
            font-family: 'Barlow Condensed', Arial, sans-serif;
            font-size: 9px;
            font-weight: 700;
            color: rgba(212,175,55,0.60);
            letter-spacing: 1px;
            text-align: center;
            /* prevent char-by-char vertical stacking */
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 180px;
            display: block;
        }
        .scan-note {
            font-size: 7px;
            color: rgba(255,255,255,0.18);
            letter-spacing: 1px;
            text-transform: uppercase;
        }

        /* ─── RESPONSIVE ────────────────────────────────────── */
        @media (max-width: 620px) {
            html, body { padding: 16px 10px; }

            /* Stack columns vertically on mobile */
            .ticket-table,
            .ticket-table tbody,
            .ticket-table tr,
            .td-body,
            .td-perf,
            .td-stub {
                display: block !important;
                width: 100% !important;
                border-radius: 0 !important;
            }
            .td-body  { border-radius: 12px 12px 0 0 !important; }
            .td-stub  { border-radius: 0 0 12px 12px !important; }

            /* horizontal perforation */
            .td-perf {
                height: 2px !important;
                background: repeating-linear-gradient(
                    to right,
                    transparent 0px, transparent 6px,
                    rgba(255,255,255,0.13) 6px, rgba(255,255,255,0.13) 12px
                ) !important;
            }
            .td-perf::before,
            .td-perf::after { display: none !important; }

            .stub-inner { flex-direction: row !important; min-height: 180px !important; }
            .stub-image { flex: 0 0 45% !important; min-height: 160px !important; }
            .stub-qr    { flex: 1 !important; padding: 12px 10px !important; }
            .qr-frame   { width: 110px !important; height: 110px !important; }

            .details-table,
            .details-table tbody,
            .details-table tr,
            .details-table td {
                display: block !important;
                width: 100% !important;
            }
            .col-a { border-right: none !important; padding-right: 0 !important;
                     border-bottom: 1px solid rgba(212,175,55,0.15) !important;
                     padding-bottom: 10px !important; margin-bottom: 10px !important; }
            .col-b { padding-left: 0 !important; }

            .event-title { font-size: 30px !important; }
            .holder-name { font-size: 15px !important; }
        }

        @media (max-width: 380px) {
            .event-title { font-size: 24px !important; }
            .qr-frame    { width: 90px !important; height: 90px !important; }
        }

        @media print {
            html, body      { background: white; padding: 0; }
            .ticket-wrapper { filter: none; }
            .dl-btn-wrap    { display: none !important; }
        }
    </style>
</head>
<body>
<div class="ticket-wrapper">

    <!-- ════════════════════════════════════════════════════
         TICKET  (table layout — works in Gmail, Outlook, DOMPDF)
         ════════════════════════════════════════════════════ -->
    <table class="ticket-table" cellpadding="0" cellspacing="0" border="0" role="presentation">
        <tbody>
        <tr>

            <!-- ══════════════════════
                 LEFT BODY  (65%)
                 ══════════════════════ -->
            <td class="td-body">

                <!-- Gold accent bar -->
                <span class="gold-bar"></span>

                <!-- Brand + title + badge + details -->
                <div class="body-inner">

                    <!-- 1. Brand row -->
                    <div class="brand-row">
                        <span class="brand-name">Eventra</span>
                        <span class="brand-sep"></span>
                        <span class="admission-tag">Admission Ticket</span>
                    </div>

                    <!-- 2. Event title -->
                    <h1 class="event-title">{$eventTitle}</h1>

                    <!-- 3. Badge — wrapped in block div so it never stretches -->
                    {$badgeHtml}

                    <!-- 4. Two-column detail grid (TABLE — email/PDF safe) -->
                    <table class="details-table" cellpadding="0" cellspacing="0" border="0" role="presentation">
                        <tbody>
                        <tr>
                            <!-- Col A : Date / Time / Venue / Price -->
                            <td class="col-a">
                                {$colA}
                            </td>
                            <!-- Col B : State / Ticket Type / Organizer -->
                            <td class="col-b">
                                {$colB}
                            </td>
                        </tr>
                        </tbody>
                    </table>

                </div><!-- /body-inner -->

                <!-- 5. Ticket holder bar -->
                <div class="holder-bar">
                    <div class="holder-left">
                        <div class="holder-avatar">
                            <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path d="M12 12c2.7 0 4.8-2.1 4.8-4.8S14.7 2.4 12 2.4
                                         7.2 4.5 7.2 7.2 9.3 12 12 12zm0 2.4c-3.2
                                         0-9.6 1.6-9.6 4.8v2.4h19.2v-2.4
                                         c0-3.2-6.4-4.8-9.6-4.8z"/>
                            </svg>
                        </div>
                        <div>
                            <span class="holder-caption">Ticket Holder</span>
                            <span class="holder-name">{$userName}</span>
                        </div>
                    </div>
                    <div class="holder-id">
                        <span class="holder-id-caption">Ticket ID</span>
                        <span class="holder-id-value">{$ticketId}</span>
                    </div>
                </div>

            </td><!-- /td-body -->

            <!-- ══════════════════════
                 PERFORATED DIVIDER
                 ══════════════════════ -->
            <td class="td-perf"></td>

            <!-- ══════════════════════
                 RIGHT STUB  (35%)
                 ══════════════════════ -->
            <td class="td-stub">
                <div class="stub-inner">

                    <!-- Event image (top ~48%) -->
                    <div class="stub-image">
                        {$eventImgHtml}
                    </div>

                    <!-- QR code section (bottom) -->
                    <div class="stub-qr">
                        <span class="scan-label">Scan to Enter</span>

                        <div class="qr-frame">
                            {$qrHtml}
                        </div>

                        <!-- Barcode text — white-space:nowrap prevents vertical stacking -->
                        <span class="ticket-code-text">{$barcode}</span>
                        <span class="scan-note">Present at venue entry</span>
                    </div>

                </div><!-- /stub-inner -->
            </td><!-- /td-stub -->

        </tr>
        </tbody>
    </table><!-- /ticket-table -->

    <!-- Download button (email view only, hidden on print) -->
    {$dlButtonHtml}

</div><!-- /ticket-wrapper -->
</body>
</html>
HTML;
    }

    /* ─────────────────────────────────────────────────────────────
     *  FULL TICKET EMAIL SENDER
     * ───────────────────────────────────────────────────────────── */

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
        string       $to,
        array        $ticketData,
        string|array $pdfPath = ''
    ): array {
        /* ── 1. Sync from DB to ensure data persistence ───────── */
        $barcode = trim((string) ($ticketData['barcode'] ?? ''));

        if ($barcode !== '') {
            $dbConfigPath = __DIR__ . '/../../config/database.php';
            if (file_exists($dbConfigPath)) {
                require_once $dbConfigPath;
                global $pdo;

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
                            // array_filter removes nulls so we don't overwrite
                            // caller-supplied values with empty DB columns
                            $ticketData = array_merge(
                                $ticketData,
                                array_filter($fresh, static fn($v) => $v !== null)
                            );
                        }
                    } catch (\Throwable $dbEx) {
                        error_log('[EmailHelper] DB sync error: ' . $dbEx->getMessage());
                        // Non-fatal — proceed with caller-supplied data
                    }
                }
            }
        }

        /* ── 2. Subject & download URL ───────────────────────── */
        $eventName = htmlspecialchars(
            $ticketData['event_name'] ?? 'Your Event', ENT_QUOTES, 'UTF-8'
        );
        $subject = "Your Ticket for {$eventName} — Eventra";

        $appUrl      = rtrim((string) ($_ENV['APP_URL'] ?? ''), '/');
        $downloadUrl = ($appUrl !== '' && $barcode !== '')
            ? $appUrl . '/api/tickets/download-ticket.php?code=' . urlencode($barcode)
            : '';

        /* ── 3. Build HTML body ───────────────────────────────── */
        $body = self::buildTicketHtml($ticketData, $downloadUrl);

        /* ── 4. Resolve PDF attachments ──────────────────────── */
        // BUG FIX: The previous code swapped .png paths for .pdf and silently
        // dropped attachments when the swapped path didn't exist. This caused
        // blank/missing PDFs. Fix: accept any valid file as-is; only skip
        // truly missing or empty paths.
        $attachments = [];
        $rawPaths    = is_array($pdfPath) ? $pdfPath : [$pdfPath];

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
 *  GLOBAL PROCEDURAL WRAPPER (backwards compatibility)
 *  Any code that calls the old standalone sendTicketEmail() function
 *  will continue to work without changes.
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

/* ─────────────────────────────────────────────────────────────────
 *  LEGACY GLOBAL HELPER (used by old detail-grid callers)
 *  Kept as a global function outside the class so any existing
 *  PHP files that call _detailCell() directly still work.
 * ───────────────────────────────────────────────────────────────── */
if (!function_exists('_detailCell')) {
    function _detailCell(string $label, string $value, string $class = ''): string
    {
        $classAttr  = $class !== '' ? ' ' . htmlspecialchars($class, ENT_QUOTES, 'UTF-8') : '';
        $safeLabel  = htmlspecialchars($label, ENT_QUOTES, 'UTF-8');
        return '<div class="detail-item' . $classAttr . '">'
             . '<span class="detail-label">' . $safeLabel . '</span>'
             . '<span class="detail-value">' . $value . '</span>'
             . '</div>';
    }
}