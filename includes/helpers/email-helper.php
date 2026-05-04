<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception as MailerException;

/**
 * Email Helper using PHPMailer
 */

// ─── 1. ROBUST PHPMailer LOADING ─────────────────────────────────────────────
$GLOBALS['EVENTRA_AUTOLOADER_ERROR'] = null;

if (!file_exists(__DIR__ . '/../../vendor/autoload.php')) {
    $GLOBALS['EVENTRA_AUTOLOADER_ERROR'] = 'Composer autoloader missing — run "composer install".';
    error_log('[EmailHelper] ' . $GLOBALS['EVENTRA_AUTOLOADER_ERROR']);
}

if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
    $autoloadPath = __DIR__ . '/../../vendor/autoload.php';
    if (file_exists($autoloadPath)) {
        try {
            if (!(@include_once $autoloadPath)) {
                throw new \Exception("include_once returned false for {$autoloadPath}");
            }
        } catch (\Throwable $e) {
            $GLOBALS['EVENTRA_AUTOLOADER_ERROR'] = $e->getMessage();
            error_log('[EmailHelper] Composer autoloader failed: ' . $e->getMessage());
        }
    }

    // Manual fallback — load PHPMailer src files directly
    if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
        $phpmailerBase = __DIR__ . '/../../vendor/phpmailer/phpmailer/src/';
        foreach (['Exception.php', 'PHPMailer.php', 'SMTP.php'] as $file) {
            $p = $phpmailerBase . $file;
            if (file_exists($p)) {
                @include_once $p;
            }
        }
    }

    // Emergency alias so code below never throws a fatal class-not-found
    if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
        error_log('[EmailHelper] CRITICAL: PHPMailer not found — emails will fail gracefully.');
        if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
            class_alias('stdClass', 'PHPMailer\PHPMailer\PHPMailer');
        }
    }
}

// ─── 2. thecodingmachine/safe COMPATIBILITY ───────────────────────────────────
if (!function_exists('safe_file_get_contents')) {
    function safe_file_get_contents(string $filename): string|false
    {
        if (!file_exists($filename)) {
            return false;
        }
        return file_get_contents($filename);
    }
}

require_once __DIR__ . '/../../config/email.php';

// ─── 3. MAIN CLASS ───────────────────────────────────────────────────────────
class EmailHelper
{
    // ── Public API ────────────────────────────────────────────────────────────

    /**
     * Core send method — all other methods funnel through here.
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

        if (!class_exists('PHPMailer\PHPMailer\PHPMailer') ||
            !method_exists('PHPMailer\PHPMailer\PHPMailer', 'isSMTP')) {
            $msg = 'Email service unavailable (PHPMailer load failed)';
            if (!empty($GLOBALS['EVENTRA_AUTOLOADER_ERROR'])) {
                $msg .= ': ' . $GLOBALS['EVENTRA_AUTOLOADER_ERROR'];
            }
            error_log('[EmailHelper] ' . $msg);
            return ['success' => false, 'message' => $msg];
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
            $mail->SMTPDebug  = 0;
            $mail->Debugoutput = null;

            $mail->setFrom(EMAIL_FROM, EMAIL_FROM_NAME);
            $mail->addReplyTo($_ENV['MAIL_REPLY_TO'] ?? EMAIL_FROM, EMAIL_FROM_NAME);
            $mail->addAddress($to);

            // ── FIX #4: Verify each attachment exists AND is non-empty ────────
            foreach ($attachments as $filePath) {
                $filePath = trim((string) $filePath);
                if ($filePath === '') {
                    continue;
                }
                if (!file_exists($filePath)) {
                    error_log("[EmailHelper] Attachment not found: {$filePath}");
                    continue;
                }
                if (filesize($filePath) === 0) {
                    error_log("[EmailHelper] Attachment is empty (0 bytes): {$filePath}");
                    continue;
                }
                $mail->addAttachment($filePath);
            }

            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $body;
            $mail->AltBody = $altBody ?: strip_tags($body);

            $sent = @$mail->send();

            if ($sent) {
                error_log("[EmailHelper] Sent → {$to} | {$subject}");
                return ['success' => true, 'message' => 'Email sent successfully'];
            }

            error_log("[EmailHelper] Send failed → {$to}: " . $mail->ErrorInfo);
            return ['success' => false, 'message' => 'Email delivery failed: ' . $mail->ErrorInfo];

        } catch (MailerException $ex) {
            error_log("[EmailHelper] Mailer error → {$to}: " . $ex->getMessage());
            return ['success' => false, 'message' => 'Email delivery failed: ' . $ex->getMessage()];
        } catch (\Throwable $ex) {
            error_log("[EmailHelper] Critical error → {$to}: " . $ex->getMessage());
            return ['success' => false, 'message' => 'Email service encountered a critical configuration error.'];
        }
    }

    // ── Legacy simple ticket sender ───────────────────────────────────────────

    public static function sendTicketEmail(
        string $to,
        string $userName,
        string $eventName,
        string $barcode,
        string $pdfPath = ''
    ): array {
        $subject    = "Your Ticket for {$eventName}";
        $safeUser   = htmlspecialchars($userName, ENT_QUOTES, 'UTF-8');
        $safeEvent  = htmlspecialchars($eventName, ENT_QUOTES, 'UTF-8');
        $safeBarcode = htmlspecialchars($barcode,  ENT_QUOTES, 'UTF-8');
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

        $attachments = ($pdfPath !== '' && file_exists($pdfPath)) ? [$pdfPath] : [];
        return self::sendEmail($to, $subject, $body, $attachments);
    }

    // ── Private helpers ────────────────────────────────────────────────────────

    private static function esc(string $v): string
    {
        return htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * FIX #5: Normalise any path (Windows or Unix) to forward-slash form,
     * then convert to an absolute path the current OS understands.
     */
    private static function normalisePath(string $path): string
    {
        // Replace Windows backslashes
        return str_replace('\\', '/', $path);
    }

    /**
     * Resolve an image path/URL to an inline base64 data-URI.
     *
     * Priority:
     *   1. Already a data-URI  → return as-is
     *   2. Absolute URL        → attempt to fetch (capped at 2 s)
     *   3. Absolute local path → read directly
     *   4. Relative path       → resolve against DOCUMENT_ROOT / APP_ROOT
     *
     * Returns empty string if the image cannot be read.
     */
    private static function imageToDataUri(string $path): string
    {
        $path = trim($path);
        if ($path === '') {
            return '';
        }

        // Already a data-URI
        if (str_starts_with($path, 'data:image/')) {
            return $path;
        }

        // Remote URL — fetch with a tight timeout so PDF generation never hangs
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            $ctx  = stream_context_create(['http' => ['timeout' => 2]]);
            $data = @file_get_contents($path, false, $ctx);
            if ($data !== false && $data !== '') {
                $mime = self::guessMime($path);
                return 'data:' . $mime . ';base64,' . base64_encode($data);
            }
            return '';
        }

        // Local path (normalise Windows slashes)
        $localPath = self::normalisePath($path);

        // Try the path as-is first
        if (!file_exists($localPath)) {
            // If it's not absolute (no drive letter on Windows)
            if (!preg_match('/^[a-zA-Z]:/', $localPath)) {
                // Resolve against the project root (two levels up from this helper)
                $projectRoot = rtrim(self::normalisePath(__DIR__ . '/../../'), '/');
                $localPath   = $projectRoot . '/' . ltrim($localPath, '/');
            }
        }

        if (!file_exists($localPath)) {
            error_log("[EmailHelper] imageToDataUri: file not found: {$path}");
            return '';
        }

        $data = @file_get_contents($localPath);
        if ($data === false || $data === '') {
            return '';
        }

        $mime = self::guessMime($localPath);
        return 'data:' . $mime . ';base64,' . base64_encode($data);
    }

    private static function guessMime(string $path): string
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        return match ($ext) {
            'jpg', 'jpeg' => 'image/jpeg',
            'gif'         => 'image/gif',
            'webp'        => 'image/webp',
            'svg'         => 'image/svg+xml',
            default       => 'image/png',
        };
    }

    /**
     *
     * @param  array  $ticketData  Full ticket data array
     * @param  string $staticPath  Absolute path to qrcode.png fallback image
     * @return string  data-URI  OR  https:// URL  OR  empty string
     */
    private static function generateQrDataUri(array $ticketData, string $staticPath = ''): string
    {
        // ── Strategy 0: Return pre-generated base64 if provided ──────────────────
        if (!empty($ticketData['qr_base64'])) {
            return $ticketData['qr_base64'];
        }

        // ── Build the payload the QR should encode ────────────────────────────
        $payload = self::buildQrPayload($ticketData);

        // ── Strategy A: chillerlan/php-qrcode (Preferred as it's in composer.json) ──
        if (class_exists('chillerlan\QRCode\QRCode') &&
            class_exists('chillerlan\QRCode\QROptions')) {
            try {
                $options = new \chillerlan\QRCode\QROptions([
                    'outputType'  => \chillerlan\QRCode\QRCode::OUTPUT_IMAGE_PNG,
                    'eccLevel'    => \chillerlan\QRCode\QRCode::ECC_H,
                    'imageBase64' => true,
                    'scale'       => 6,
                    'imageTransparent' => false,
                ]);
                $qr     = new \chillerlan\QRCode\QRCode($options);
                $result = $qr->render($payload);
                // chillerlan returns a full data-URI when imageBase64 = true
                return $result;
            } catch (\Throwable $e) {
                error_log('[EmailHelper] chillerlan/php-qrcode failed: ' . $e->getMessage());
            }
        }

        // ── Strategy B: endroid/qr-code (Fallback) ─────────────────────────
        if (class_exists('Endroid\QrCode\QrCode') &&
            class_exists('Endroid\QrCode\Writer\PngWriter') &&
            class_exists('Endroid\QrCode\Color\Color') &&
            class_exists('Endroid\QrCode\Encoding\Encoding') &&
            class_exists('Endroid\QrCode\ErrorCorrectionLevel')) {
            try {
                $qrCode = \Endroid\QrCode\QrCode::create($payload)
                    ->setEncoding(new \Endroid\QrCode\Encoding\Encoding('UTF-8'))
                    ->setErrorCorrectionLevel(\Endroid\QrCode\ErrorCorrectionLevel::High)
                    ->setSize(300)
                    ->setMargin(10)
                    ->setForegroundColor(new \Endroid\QrCode\Color\Color(0, 0, 0))
                    ->setBackgroundColor(new \Endroid\QrCode\Color\Color(255, 255, 255));

                $writer = new \Endroid\QrCode\Writer\PngWriter();
                $result = $writer->write($qrCode);
                return 'data:image/png;base64,' . base64_encode($result->getString());
            } catch (\Throwable $e) {
                error_log('[EmailHelper] endroid/qr-code failed: ' . $e->getMessage());
            }
        }

        // ── Strategy C: Static fallback PNG (user-supplied qrcode.png) ────
        //    We still embed it as base64 so PDFs never have a broken image.
        $staticPath = self::normalisePath(trim($staticPath));
        if ($staticPath === '') {
            // Default location relative to this file
            $staticPath = self::normalisePath(__DIR__ . '/../../public/assets/qrcode.png');
        }
        if (file_exists($staticPath)) {
            $data = @file_get_contents($staticPath);
            if ($data !== false && $data !== '') {
                error_log('[EmailHelper] QR: using static PNG fallback from: ' . $staticPath);
                return 'data:image/png;base64,' . base64_encode($data);
            }
        }

        // ── Strategy D: Google Charts API (email-only fallback) ───────────
        error_log('[EmailHelper] QR: falling back to Google Charts API. Install endroid/qr-code for PDF-safe QR codes.');
        return 'https://chart.googleapis.com/chart?cht=qr&chs=300x300&chld=H|2&chl=' . urlencode($payload);
    }

    /**
     * Build the string payload to encode in the QR code.
     * Contains all information a scanner needs to verify the ticket.
     */
    private static function buildQrPayload(array $d): string
    {
        $parts = [
            'TICKET:'    . ($d['barcode']     ?? $d['ticket_id'] ?? ''),
            'EVENT:'     . ($d['event_name']  ?? ''),
            'DATE:'      . ($d['event_date']  ?? ''),
            'VENUE:'     . ($d['address']     ?? ''),
            'HOLDER:'    . ($d['user_name']   ?? ''),
            'USER_ID:'   . ($d['user_id']     ?? ''),
            'EVENT_ID:'  . ($d['event_id']    ?? ''),
            'ORDER_ID:'  . ($d['order_id']    ?? ''),
            'TYPE:'      . ($d['ticket_type'] ?? ''),
            'AMOUNT:'    . ($d['amount']      ?? '0'),
            'STATUS:'    . (isset($d['amount']) && (float)$d['amount'] <= 0 ? 'FREE' : 'PAID'),
            'VERIFY:'    . strtoupper(substr(sha1(($d['barcode'] ?? '') . ($d['user_id'] ?? '')), 0, 10)),
        ];

        return implode('|', array_filter($parts, static fn($p) => $p !== substr($p, 0, strpos($p, ':') + 1)));
    }

    private static function detailRow(string $label, string $value, bool $priceStyle = false): string
    {
        $valueStyle = $priceStyle
            ? 'font-family:Arial,sans-serif;font-size:17px;font-weight:800;color:#d4af37;line-height:1.2;display:block;'
            : 'font-family:Arial,sans-serif;font-size:15px;font-weight:600;color:#d4af37;line-height:1.2;display:block;';

        return '<div style="margin-bottom:14px;word-break:break-word;">'
            . '<span style="display:block;font-family:Arial,sans-serif;'
            . 'font-size:9px;font-weight:700;letter-spacing:2px;text-transform:uppercase;'
            . 'color:rgba(255,255,255,0.30);margin-bottom:3px;">'
            . self::esc($label)
            . '</span>'
            . '<span style="' . $valueStyle . '">' . $value . '</span>'
            . '</div>';
    }

    // ── buildTicketHtml ────────────────────────────────────────────────────────
    public static function buildTicketHtml(array $ticketData, string $downloadUrl = ''): string
    {
        /* ── Sanitise text fields ─────────────────────────────── */
        $barcode    = self::esc($ticketData['barcode']   ?? '');
        $ticketId   = self::esc($ticketData['ticket_id'] ?? ($ticketData['barcode'] ?? ''));
        $eventTitle = self::esc($ticketData['event_name'] ?? 'LIVE CONCERT');
        $userName   = self::esc($ticketData['user_name'] ?? 'Attendee');
        $venue      = self::esc($ticketData['address']   ?? '—');
        $location   = self::esc($ticketData['location']  ?? '—');
        $organizer  = self::esc($ticketData['organizer'] ?? '');
        $ticketType = self::esc($ticketData['ticket_type'] ?? '');
        $year       = date('Y');

        $tickDispRaw = $ticketData['ticket_type_display'] ?? ($ticketData['ticket_type'] ?? '');
        if (isset($ticketData['amount']) && (float)$ticketData['amount'] <= 0) {
            $tickDispRaw = 'Free';
        }
        $tickDisp = self::esc($tickDispRaw);

        /* ── Date & time ─────────────────────────────────────── */
        $eventDate = !empty($ticketData['event_date'])
            ? self::esc(date('D, d M Y', strtotime((string) $ticketData['event_date'])))
            : 'TBC';
        $eventTime = !empty($ticketData['event_time'])
            ? self::esc(date('g:i A', strtotime((string) $ticketData['event_time'])))
            : 'TBC';

        /* ── Price ───────────────────────────────────────────── */
        $amountDisplay = '';
        if (isset($ticketData['amount'])) {
            $amountFloat   = (float) $ticketData['amount'];
            $amountDisplay = $amountFloat > 0
                ? '&#8358;' . number_format($amountFloat, 2)
                : 'Free';
        }

        /* ── FIX #1: Dynamic QR code ─────────────────────────── */
        // Path to the static qrcode.png the user provided (Windows path normalised)
        $staticQrPath = self::normalisePath(
            $ticketData['qr_path'] ?? (__DIR__ . '/../../public/assets/qrcode.png')
        );
        $qrDataUri = self::generateQrDataUri($ticketData, $staticQrPath);

        // Decide how to render: data-URI → <img src="data:...">
        //                       https:// URL → <img src="https://..."> (email only, not PDF)
        if ($qrDataUri !== '') {
            $qrHtml = '<img src="' . htmlspecialchars($qrDataUri, ENT_QUOTES, 'UTF-8') . '"'
                . ' alt="QR Code" width="130" height="130"'
                . ' style="width:130px;height:130px;display:block;margin:0 auto;border-radius:10px;">';
        } else {
            $qrHtml = '<div style="width:130px;height:130px;background:#222;'
                . 'text-align:center;line-height:130px;'
                . 'font-size:10px;color:#555;letter-spacing:1px;'
                . 'border-radius:10px;">NO QR</div>';
        }

        /* ── FIX #2: Event banner — embed as base64 ──────────── */
        $imgRaw    = trim((string) ($ticketData['event_image'] ?? ''));
        $imgBase64 = self::imageToDataUri($imgRaw);

        $eventImgHtml = $imgBase64 !== ''
            ? '<img src="' . $imgBase64 . '" alt="Event" width="100%" height="180"'
              . ' style="width:100%;height:180px;display:block;">'
            : '<div style="width:100%;height:180px;'
              . 'background:linear-gradient(135deg,#1a1a2e 0%,#0f3460 100%);'
              . 'text-align:center;line-height:180px;">'
              . '<span style="font-size:10px;letter-spacing:3px;'
              . 'color:rgba(212,175,55,0.45);text-transform:uppercase;">Event Image</span>'
              . '</div>';

        /* ── Ticket-type badge ───────────────────────────────── */
        $badgeBg = '#d4af37';
        $badgeFg = '#111111';
        if ($tickDisp !== '') {
            $lower = strtolower($tickDispRaw);
            if (str_contains($lower, 'vip'))     { $badgeBg = '#c0392b'; $badgeFg = '#ffffff'; }
            if (str_contains($lower, 'premium')) { $badgeBg = '#9b59b6'; $badgeFg = '#ffffff'; }
            if (str_contains($lower, 'free'))    { $badgeBg = '#27ae60'; $badgeFg = '#ffffff'; }
        }
        $badgeWrapStyle = 'line-height:1;margin-bottom:16px;min-height:22px;';
        $badgeHtml = $tickDisp !== ''
            ? '<div style="' . $badgeWrapStyle . '">'
              . '<span style="display:inline-block;background:' . $badgeBg . ';color:' . $badgeFg . ';'
              . 'font-family:Arial,sans-serif;font-size:9px;font-weight:800;letter-spacing:2px;'
              . 'text-transform:uppercase;padding:4px 14px;border-radius:20px;">'
              . $tickDisp . '</span></div>'
            : '<div style="' . $badgeWrapStyle . '"></div>';

        /* ── Detail columns ──────────────────────────────────── */
        $colA  = self::detailRow('Date',     $eventDate);
        $colA .= self::detailRow('Time',     $eventTime);

        /* ── Venue & Location logic ─────────────────────────── */
        $locations = $ticketData['locations'] ?? null;
        if (is_string($locations)) {
            $locations = json_decode($locations, true);
        }

        if (is_array($locations) && count($locations) > 1) {
            $colA .= '<div style="margin-bottom:14px;word-break:break-word;">'
                . '<span style="display:block;font-family:Arial,sans-serif;'
                . 'font-size:9px;font-weight:700;letter-spacing:2px;text-transform:uppercase;'
                . 'color:rgba(255,255,255,0.30);margin-bottom:3px;">Venue & Location</span>';
            foreach ($locations as $loc) {
                $s = self::esc($loc['state'] ?? '');
                $a = self::esc($loc['address'] ?? '');
                $colA .= '<span style="font-family:Arial,sans-serif;font-size:13px;font-weight:600;color:#d4af37;line-height:1.2;display:block;margin-bottom:2px;">' 
                    . $s . ' : ' . $a . '</span>';
            }
            $colA .= '</div>';
        } else {
            // Single state or fallback
            $st = $ticketData['state'] ?? '';
            $ad = $ticketData['address'] ?? $ticketData['location'] ?? '—';
            
            if (!empty($st) && strtolower($st) !== 'all states') {
                $colA .= self::detailRow($st, $ad);
            } else {
                $colA .= self::detailRow('Venue', $ad);
                if (!empty($st)) {
                    $colA .= self::detailRow('Location', $st);
                }
            }
        }

        $colB = '';
        if ($tickDisp !== '' || $ticketType !== '') {
            $colB .= self::detailRow('Ticket Type', $tickDisp ?: $ticketType);
        }
        if ($amountDisplay !== '') {
            $colB .= self::detailRow('Price', $amountDisplay, true);
        }
        if ($organizer !== '') {
            $colB .= self::detailRow('Organizer', $organizer);
        }

        /* ── FIX #3: Download button — only when URL is valid ─── */
        $dlButtonHtml = '';
        if ($downloadUrl !== '' && filter_var($downloadUrl, FILTER_VALIDATE_URL)) {
            $safeUrl      = self::esc($downloadUrl);
            $dlButtonHtml = <<<BTN
            <div style="text-align:center;margin-top:28px;" class="dl-btn-wrap">
                <a href="{$safeUrl}"
                   style="display:inline-block;padding:13px 36px;
                          background:linear-gradient(135deg,#d4af37,#f5d87a);
                          color:#111111;text-decoration:none;border-radius:7px;
                          font-family:Arial,sans-serif;font-size:14px;font-weight:800;
                          letter-spacing:1.5px;text-transform:uppercase;">
                    &#8675;&nbsp;Download PDF Ticket
                </a>
            </div>
            BTN;
        }

        /*
         * ── FIX #2 cont.: CSS font stack ────────────────────────
         * Google Fonts <link> removed entirely.
         * "Bebas Neue" → impact, "Barlow Condensed" → Arial Narrow / Arial.
         * These are built-in on every OS and every PDF renderer.
         */
        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Concert Ticket &mdash; {$eventTitle}</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        @page { margin: 0; size: A4 landscape; }

        html, body {
            width: 100%;
            min-height: 100%;
            background: #1a1a1a;
            font-family: Arial, 'Helvetica Neue', Helvetica, sans-serif;
            padding: 40px 20px;
        }

        .ticket-wrapper {
            width: 100%;
            max-width: 900px;
            margin: 0 auto;
            /* drop-shadow removed: unsupported in older PDF renderers */
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

        .main-inner { padding: 28px 28px 20px 28px; }

        .live-concert-badge {
            display: inline-block;
            font-family: Impact, 'Arial Narrow', Arial, sans-serif;
            font-size: 14px;
            letter-spacing: 6px;
            color: #d4af37;
            border: 1.5px solid #d4af37;
            padding: 6px 18px;
            margin-bottom: 20px;
            text-transform: uppercase;
        }

        .event-title {
            font-family: Impact, 'Arial Narrow', Arial, sans-serif;
            font-size: 42px;
            line-height: 1.1;
            color: #ffffff;
            letter-spacing: 1px;
            text-transform: uppercase;
            margin-bottom: 12px;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }

        .holder-info h4 {
            font-size: 9px;
            letter-spacing: 2px;
            text-transform: uppercase;
            color: rgba(255,255,255,0.3);
            margin-bottom: 4px;
        }
        .holder-info .name {
            font-family: 'Arial Narrow', Arial, sans-serif;
            font-size: 20px;
            font-weight: 800;
            color: #fff;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 250px;
        }
        .ticket-id { text-align: right; }
        .ticket-id .label {
            font-size: 9px;
            letter-spacing: 2px;
            text-transform: uppercase;
            color: rgba(255,255,255,0.3);
        }
        .ticket-id .value {
            font-family: 'Arial Narrow', Arial, sans-serif;
            font-size: 14px;
            font-weight: 700;
            color: #d4af37;
        }

        /* Perforation */
        .td-perf {
            width: 2px;
            background: repeating-linear-gradient(
                to bottom,
                transparent 0px, transparent 8px,
                rgba(255,255,255,0.15) 8px, rgba(255,255,255,0.15) 16px
            );
            vertical-align: top;
        }

        .td-stub { width: 40%; background: #181818; vertical-align: top; }

        .stub-image { height: 180px; overflow: hidden; position: relative; }

        .qr-section { padding: 20px 20px 25px; text-align: center; }
        .qr-label {
            font-family: Arial, sans-serif;
            font-size: 10px;
            letter-spacing: 3px;
            text-transform: uppercase;
            color: rgba(255,255,255,0.3);
            margin-bottom: 12px;
        }
        .qr-frame {
            width: 140px;
            height: 140px;
            background: white;
            border-radius: 10px;
            margin: 0 auto 12px;
            padding: 5px;
            border: 4px solid white;
            text-align: center;
        }
        .barcode-text {
            font-family: 'Courier New', Courier, monospace;
            font-size: 12px;
            font-weight: 700;
            color: #d4af37;
            letter-spacing: 1.5px;
        }

        @media (max-width: 600px) {
            .ticket-table, .ticket-table tbody, .ticket-table tr,
            .td-main, .td-perf, .td-stub { display:block !important; width:100% !important; }
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
            <!-- LEFT: main ticket body -->
            <td class="td-main">
                <span class="gold-accent" style="display:block;"></span>
                <div class="main-inner">
                    <div class="live-concert-badge">LIVE CONCERT</div>
                    <h1 class="event-title">{$eventTitle}</h1>
                    {$badgeHtml}
                    <table width="100%" cellpadding="0" cellspacing="0" border="0" role="presentation" style="margin-top:20px;">
                        <tr>
                            <td width="50%" valign="top">{$colA}</td>
                            <td width="50%" valign="top">{$colB}</td>
                        </tr>
                    </table>

                    <table width="100%" cellpadding="0" cellspacing="0" border="0" role="presentation"
                           style="margin-top:30px;padding-top:20px;border-top:1px solid rgba(212,175,55,0.2);">
                        <tr>
                            <td align="left" valign="middle">
                                <div class="holder-info">
                                    <h4>Ticket Holder</h4>
                                    <div class="name">{$userName}</div>
                                </div>
                            </td>
                            <td align="right" valign="middle">
                                <div class="ticket-id">
                                    <div class="label">Ticket ID</div>
                                    <div class="value">{$ticketId}</div>
                                </div>
                            </td>
                        </tr>
                    </table>
                </div>
            </td>

            <!-- PERFORATION -->
            <td class="td-perf"></td>

            <!-- RIGHT: event image + QR stub -->
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

    // ── OTP email ─────────────────────────────────────────────────────────────

    public static function sendRegistrationOTP(string $to, string $name, string $otp): array
    {
        $subject  = "Verify your Eventra account — OTP: {$otp}";
        $safeName = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
        $year     = date('Y');

        $body = <<<HTML
        <div style="font-family:Arial,sans-serif;max-width:600px;margin:auto;padding:40px;
                    background:#ffffff;border-radius:16px;
                    box-shadow:0 4px 24px rgba(0,0,0,0.06);border:1px solid #f1f5f9;">
            <div style="text-align:center;margin-bottom:32px;">
                <h1 style="color:#2ecc71;margin:0;font-size:28px;font-weight:800;">Eventra</h1>
                <p style="color:#64748b;margin-top:8px;font-size:14px;">Bringing your events to life</p>
            </div>
            <h2 style="color:#1e293b;font-size:20px;font-weight:700;margin-bottom:16px;">Confirm your email address</h2>
            <p style="color:#475569;font-size:16px;line-height:1.6;margin-bottom:24px;">Hi <strong>{$safeName}</strong>,</p>
            <p style="color:#475569;font-size:16px;line-height:1.6;margin-bottom:32px;">
                Use the code below to verify your account. It expires in 15 minutes.
            </p>
            <div style="background:#f8fafc;padding:32px;text-align:center;border-radius:12px;
                        margin:32px 0;border:1px solid #e2e8f0;">
                <p style="margin:0 0 12px 0;color:#64748b;font-size:12px;
                           text-transform:uppercase;letter-spacing:2px;font-weight:700;">Verification Code</p>
                <div style="font-size:48px;font-weight:800;letter-spacing:8px;
                            color:#1e293b;font-family:'Courier New',monospace;">{$otp}</div>
            </div>
            <p style="color:#64748b;font-size:14px;line-height:1.6;margin-bottom:32px;">
                If you didn't request this, you can safely ignore it.
            </p>
            <hr style="border:0;border-top:1px solid #f1f5f9;margin:32px 0;">
            <p style="font-size:12px;color:#94a3b8;text-align:center;margin:0;">
                &copy; {$year} Eventra Inc. All rights reserved.
            </p>
        </div>
        HTML;

        return self::sendEmail($to, $subject, $body);
    }

    // ── sendTicketEmailFull ────────────────────────────────────────────────────

    /**
     * Send a full rich ticket email.
     *
     * FIX SUMMARY applied here:
     * • DB sync unchanged — still fetches freshest data.
     * • buildTicketHtml() now produces a fully self-contained HTML (no
     *   external resources) so the same HTML can be handed to DomPDF /
     *   wkhtmltopdf / mPDF to generate a non-blank PDF.
     * • PDF paths validated for existence AND non-zero size before attaching.
     * • Download URL only appended if it passes FILTER_VALIDATE_URL.
     */
    public static function sendTicketEmailFull(
        string       $to,
        array        $ticketData,
        string|array $pdfPath = ''
    ): array {
        /* ── 1. DB sync ──────────────────────────────────────────── */
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
                                t.barcode        AS ticket_id,
                                t.status,
                                t.ticket_type,
                                t.user_id,
                                t.event_id,
                                e.event_name,
                                e.event_date,
                                e.event_time,
                                e.location,
                                e.address,
                                e.locations,
                                e.state,
                                e.image_path     AS event_image,
                                u.name           AS user_name,
                                p.amount,
                                p.id             AS order_id
                            FROM   tickets  t
                            JOIN   events   e ON e.id = t.event_id
                            JOIN   users    u ON u.id = t.user_id
                            LEFT JOIN payments p ON p.id = t.payment_id
                            WHERE  t.barcode = ?
                            LIMIT  1
                        ");
                        $stmt->execute([$barcode]);
                        $fresh = $stmt->fetch(\PDO::FETCH_ASSOC);

                        if ($fresh) {
                            // Merge: fresh DB data wins, but keep caller-supplied
                            // fields that DB doesn't have (e.g. organizer, qr_path)
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

        /* ── 2. Subject ──────────────────────────────────────────── */
        $eventName = htmlspecialchars(
            $ticketData['event_name'] ?? 'Your Event',
            ENT_QUOTES, 'UTF-8'
        );
        $subject = "Your Ticket for {$eventName} — Eventra";

        /* ── 3. Download URL ─────────────────────────────────────── */
        $appUrl      = rtrim((string) ($_ENV['APP_URL'] ?? ''), '/');
        $downloadUrl = '';
        if ($appUrl !== '' && $barcode !== '') {
            $candidate = $appUrl . '/api/tickets/download-ticket.php?code=' . urlencode($barcode);
            if (filter_var($candidate, FILTER_VALIDATE_URL)) {
                $downloadUrl = $candidate;
            }
        }

        /* ── 4. Build fully self-contained HTML ──────────────────── */
        $body = self::buildTicketHtml($ticketData, $downloadUrl);

        /* ── 5. Validate PDF attachments ─────────────────────────── */
        $attachments = [];
        $rawPaths    = is_array($pdfPath) ? $pdfPath : [$pdfPath];

        foreach ($rawPaths as $path) {
            $path = trim((string) $path);
            if ($path === '') {
                continue;
            }
            if (!file_exists($path)) {
                error_log("[EmailHelper] PDF not found, skipping attachment: {$path}");
                continue;
            }
            if (filesize($path) === 0) {
                error_log("[EmailHelper] PDF is empty (0 bytes), skipping: {$path}");
                continue;
            }
            if (!in_array($path, $attachments, true)) {
                $attachments[] = $path;
            }
        }

        /* ── 6. Send ─────────────────────────────────────────────── */
        return self::sendEmail($to, $subject, $body, $attachments);
    }
}

// ─── 4. GLOBAL FUNCTION WRAPPERS ─────────────────────────────────────────────
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
        $classAttr  = $class !== '' ? ' ' . htmlspecialchars($class, ENT_QUOTES, 'UTF-8') : '';
        $safeLabel  = htmlspecialchars($label, ENT_QUOTES, 'UTF-8');
        return '<div class="detail-item' . $classAttr . '">'
            . '<span class="detail-label">' . $safeLabel . '</span>'
            . '<span class="detail-value">' . $value . '</span>'
            . '</div>';
    }
}