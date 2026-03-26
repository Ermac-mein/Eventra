<?php

/**
 * Email Helper using PHPMailer
 * Supports HTML emails with optional PDF attachments
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../config/email.php';

/**
 * Send an email with optional file attachments
 *
 * @param string $to         Recipient email address
 * @param string $subject    Email subject
 * @param string $body       Email HTML content
 * @param array  $attachments Array of absolute file paths to attach
 * @param string $altBody    Plain-text fallback (auto-generated if empty)
 * @return array ['success' => bool, 'message' => string]
 */
function sendEmail($to, $subject, $body, $attachments = [], $altBody = '')
{
    if (empty(SMTP_HOST) || empty(SMTP_USER) || empty(SMTP_PASS)) {
        error_log("[Email Helper] Error: SMTP credentials not configured. Check MAIL_HOST, MAIL_USERNAME, MAIL_PASSWORD in .env");
        return ['success' => false, 'message' => 'SMTP credentials not configured. Please contact the administrator.'];
    }

    $mail = new PHPMailer(true);

    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USER;
        $mail->Password   = SMTP_PASS;
        $mail->SMTPSecure = SMTP_SECURE;
        $mail->Port       = (int) SMTP_PORT;

        // Recipients
        $mail->setFrom(EMAIL_FROM, EMAIL_FROM_NAME);
        $mail->addAddress($to);

        // Attachments (e.g. PDF tickets)
        if (!empty($attachments) && is_array($attachments)) {
            foreach ($attachments as $filePath) {
                if (file_exists($filePath)) {
                    $mail->addAttachment($filePath);
                } else {
                    error_log("[Email Helper] Attachment not found: {$filePath}");
                }
            }
        }

        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $body;
        $mail->AltBody = $altBody ?: strip_tags($body);

        $mail->send();

        // Delivery logging
        error_log("[Email Helper] Email sent successfully to: {$to} | Subject: {$subject}");
        return ['success' => true, 'message' => 'Email sent successfully'];
    } catch (Exception $e) {
        error_log("[Email Helper] Mailer Error sending to {$to}: {$mail->ErrorInfo}");
        return ['success' => false, 'message' => "Email delivery failed: {$mail->ErrorInfo}"];
    }
}

/**
 * Send Ticket Purchase Confirmation Email (legacy wrapper)
 *
 * @param string $to
 * @param string $userName
 * @param string $eventName
 * @param string $barcode
 * @param string $pdfPath  Optional path to PDF ticket attachment
 * @return array
 */
function sendTicketEmail($to, $userName, $eventName, $barcode, $pdfPath = null)
{
    $subject = "Your Ticket for {$eventName}";

    $body = "
        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: auto; padding: 20px; border: 1px solid #eee;'>
            <h2 style='color: #7c3aed;'>Ticket Confirmation</h2>
            <p>Hi <strong>{$userName}</strong>,</p>
            <p>Thank you for your purchase! Your ticket for <strong>{$eventName}</strong> is ready.</p>
            <div style='background: #f9f9f9; padding: 20px; text-align: center; border-radius: 10px; margin: 20px 0;'>
                <p style='margin-bottom: 5px; color: #666;'>Ticket ID</p>
                <div style='font-size: 24px; font-weight: bold; letter-spacing: 5px; color: #7c3aed;'>{$barcode}</div>
            </div>
            <p>Your PDF ticket is attached to this email. Please present the QR code at the venue entrance.</p>
            <hr style='border: 0; border-top: 1px solid #eee; margin: 20px 0;'>
            <p style='font-size: 12px; color: #999; text-align: center;'>
                &copy; " . date('Y') . " Eventra. All rights reserved.
            </p>
        </div>
    ";

    $attachments = ($pdfPath && file_exists($pdfPath)) ? [$pdfPath] : [];
    return sendEmail($to, $subject, $body, $attachments);
}

/**
 * sendTicketEmailFull — Rich marketplace ticket email with full event details,
 * styled inline preview, PDF attachment, and a download link.
 *
 * @param string $to
 * @param array  $ticketData  Keys: barcode, event_name, event_date, event_time, location, user_name, order_id, amount
 * @param string $pdfPath     Absolute path to generated PDF ticket
 * @return array
 */
function sendTicketEmailFull(string $to, array $ticketData, string $pdfPath = ''): array
{
    $barcode   = $ticketData['barcode']   ?? '';
    $eventName = htmlspecialchars($ticketData['event_name'] ?? 'Your Event');
    $userName  = htmlspecialchars($ticketData['user_name']  ?? 'Attendee');
    $eventDate = !empty($ticketData['event_date']) ? date('D, d M Y', strtotime($ticketData['event_date'])) : 'TBC';
    $eventTime = !empty($ticketData['event_time']) ? date('g:i A', strtotime($ticketData['event_time'])) : 'TBC';
    $venue     = htmlspecialchars($ticketData['location'] ?? 'See event details');
    $amount    = '₦' . number_format((float)($ticketData['amount'] ?? 0), 2);
    $year      = date('Y');

    // Build download URL from APP_URL env or fallback
    $appUrl      = rtrim($_ENV['APP_URL'] ?? '', '/');
    $downloadUrl = $appUrl ? "{$appUrl}/api/tickets/download-ticket.php?code={$barcode}" : '';
    $dlLink      = $downloadUrl ? "<a href='{$downloadUrl}' style='display:inline-block;margin-top:12px;padding:10px 24px;background:#7c3aed;color:white;text-decoration:none;border-radius:6px;font-weight:600;font-size:14px;'>Download PDF Ticket</a>" : '';

    $subject = "Your Ticket for {$eventName} — Eventra";

    $body = "
    <div style='font-family:Arial,sans-serif;max-width:600px;margin:auto;background:#f9fafb;padding:20px;border-radius:12px;'>
        <!-- Header -->
        <div style='background:linear-gradient(135deg,#7c3aed,#4c1d95);padding:24px 30px;border-radius:10px 10px 0 0;text-align:center;'>
            <h1 style='color:white;margin:0;letter-spacing:3px;font-size:22px;'>EVENTRA</h1>
            <p style='color:rgba(255,255,255,0.8);margin:6px 0 0;font-size:13px;'>Official Ticket Confirmation</p>
        </div>

        <!-- Body -->
        <div style='background:white;padding:28px 30px;'>
            <p style='font-size:16px;color:#1f2937;'>Hi <strong>{$userName}</strong>,</p>
            <p style='color:#6b7280;font-size:15px;line-height:1.6;'>Your payment was successful and your ticket for <strong style='color:#7c3aed;'>{$eventName}</strong> has been issued. See you there!</p>

            <!-- Ticket Card -->
            <div style='border:2px solid #7c3aed;border-radius:12px;overflow:hidden;margin:24px 0;'>
                <div style='background:#faf5ff;padding:20px 24px;'>
                    <table style='width:100%;border-collapse:collapse;font-size:14px;'>
                        <tr><td style='padding:6px 0;color:#6b7280;width:100px;'>📅 Date</td><td style='color:#1f2937;font-weight:600;'>{$eventDate}</td></tr>
                        <tr><td style='padding:6px 0;color:#6b7280;'>⏰ Time</td><td style='color:#1f2937;font-weight:600;'>{$eventTime}</td></tr>
                        <tr><td style='padding:6px 0;color:#6b7280;'>📍 Venue</td><td style='color:#1f2937;font-weight:600;'>{$venue}</td></tr>
                        <tr><td style='padding:6px 0;color:#6b7280;'>💳 Amount</td><td style='color:#1f2937;font-weight:600;'>{$amount}</td></tr>
                    </table>
                </div>
                <div style='border-top:2px dashed #7c3aed;background:white;padding:16px 24px;display:flex;justify-content:space-between;align-items:center;'>
                    <div>
                        <div style='font-size:10px;color:#9ca3af;text-transform:uppercase;letter-spacing:1px;'>Ticket ID</div>
                        <div style='font-family:monospace;font-size:16px;font-weight:700;color:#7c3aed;letter-spacing:2px;'>{$barcode}</div>
                    </div>
                </div>
            </div>

            <p style='color:#6b7280;font-size:14px;line-height:1.6;'>Your PDF ticket is attached to this email. Please present the QR code at the venue entrance for entry.</p>

            {$dlLink}
        </div>

        <!-- Footer -->
        <div style='padding:16px 30px;text-align:center;font-size:12px;color:#9ca3af;'>
            Valid for one-time entry &bull; Non-refundable &bull; &copy; {$year} Eventra
        </div>
    </div>
    ";

    $attachments = ($pdfPath && file_exists($pdfPath)) ? [$pdfPath] : [];
    return sendEmail($to, $subject, $body, $attachments);
}
