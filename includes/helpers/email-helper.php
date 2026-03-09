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
