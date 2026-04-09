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
            <h2 style='color: #2ecc71;'>Ticket Confirmation</h2>
            <p>Hi <strong>{$userName}</strong>,</p>
            <p>Thank you for your purchase! Your ticket for <strong>{$eventName}</strong> is ready.</p>
            <div style='background: #f9f9f9; padding: 20px; text-align: center; border-radius: 10px; margin: 20px 0;'>
                <p style='margin-bottom: 5px; color: #666;'>Ticket ID</p>
                <div style='font-size: 24px; font-weight: bold; letter-spacing: 5px; color: #2ecc71;'>{$barcode}</div>
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
function sendTicketEmailFull(string $to, array $ticketData, $pdfPath = ''): array
{
    $barcode   = $ticketData['barcode']   ?? '';
    $eventName = htmlspecialchars($ticketData['event_name'] ?? 'Your Event');
    $userName  = htmlspecialchars($ticketData['user_name']  ?? 'Attendee');
    $eventDate = !empty($ticketData['event_date']) ? date('D, d M Y', strtotime($ticketData['event_date'])) : 'TBC';
    $eventTime = !empty($ticketData['event_time']) ? date('g:i A', strtotime($ticketData['event_time'])) : 'TBC';
    $venue     = htmlspecialchars($ticketData['location'] ?? 'See event details');
    $amount    = '₦' . number_format((float)($ticketData['amount'] ?? 0), 2);
    $year      = date('Y');

    // Handle both single PDF and array of PDFs
    $attachments = [];
    if (!empty($pdfPath)) {
        if (is_array($pdfPath)) {
            $attachments = $pdfPath; // Array of paths
        } else {
            $attachments = [$pdfPath]; // Single path
        }
    }
    
    // Filter out non-existent files
    $attachments = array_filter($attachments, function($filePath) {
        return !empty($filePath) && file_exists($filePath);
    });

    // Build download URL from APP_URL env or fallback
    $appUrl      = rtrim($_ENV['APP_URL'] ?? '', '/');
    $downloadUrl = $appUrl ? "{$appUrl}/api/tickets/download-ticket.php?code={$barcode}" : '';
    $dlLink      = $downloadUrl ? "<br><br><a href='{$downloadUrl}' style='display:inline-block;padding:10px 20px;background:#f59e0b;color:white;text-decoration:none;border-radius:6px;font-weight:600;font-size:14px;text-align:center;'>Download PDF Ticket</a>" : '';

    $subject = "Your Ticket for {$eventName} — Eventra";

    $body = "
    <div style='font-family:Arial,sans-serif;background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);padding:40px;min-height:400px;'>
        <div style='background:white;max-width:600px;margin:auto;border-radius:24px;overflow:hidden;border:1px solid rgba(255,255,255,0.2);'>
            <!-- Header -->
            <div style='background:linear-gradient(135deg,#ff5a5f,#ff8a8e);color:white;padding:32px;text-align:center;'>
                <div style='font-size:32px;font-weight:800;letter-spacing:-0.02em;'>🎫 EVENTRA</div>
                <div style='font-size:14px;opacity:0.9;text-transform:uppercase;letter-spacing:1px;margin-top:8px;'>Digital Entry Pass</div>
            </div>
            
            <!-- Body -->
            <div style='padding:40px;'>
                <h1 style='font-size:28px;font-weight:800;margin-bottom:24px;color:#1f2937;line-height:1.2;'>{$eventName}</h1>
                
                <div style='margin-bottom:32px;'>
                    <table style='width:100%;border-spacing:0;font-size:14px;'>
                        <tr>
                            <td style='padding:16px;background:#f8fafc;border-radius:16px;border-left:4px solid #ff5a5f;width:48%;'>
                                <div style='font-size:12px;font-weight:600;color:#6b7280;text-transform:uppercase;margin-bottom:4px;'>Date</div>
                                <div style='font-size:16px;font-weight:700;color:#1f2937;'>{$eventDate}</div>
                            </td>
                            <td style='width:4%;'></td>
                            <td style='padding:16px;background:#f8fafc;border-radius:16px;border-left:4px solid #ff5a5f;width:48%;'>
                                <div style='font-size:12px;font-weight:600;color:#6b7280;text-transform:uppercase;margin-bottom:4px;'>Time</div>
                                <div style='font-size:16px;font-weight:700;color:#1f2937;'>{$eventTime}</div>
                            </td>
                        </tr>
                        <tr>
                            <td colspan='3' style='padding:16px;background:#f8fafc;border-radius:16px;border-left:4px solid #ff5a5f;margin-top:12px;display:block;'>
                                <div style='font-size:12px;font-weight:600;color:#6b7280;text-transform:uppercase;margin-bottom:4px;'>Location</div>
                                <div style='font-size:16px;font-weight:700;color:#1f2937;'>{$venue}</div>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <div style='background:linear-gradient(135deg,#10b981,#059669);color:white;padding:24px;border-radius:16px;text-align:center;margin-bottom:32px;'>
                    <div style='font-size:24px;font-weight:800;margin-bottom:4px;'>{$userName}</div>
                    <div style='opacity:0.9;font-size:14px;'>Ticket Holder</div>
                </div>
                
                <div style='text-align:center;margin-bottom:32px;'>
                    <div style='font-size:12px;color:#6b7280;text-transform:uppercase;letter-spacing:1px;margin-bottom:8px;'>Ticket ID</div>
                    <div style='font-size:20px;font-weight:800;color:#1f2937;letter-spacing:2px;font-family:monospace;'>{$barcode}</div>
                </div>
                
                <div style='text-align:center;padding:16px;background:linear-gradient(90deg,#f3f4f6,#e5e7eb);border-radius:12px;margin-bottom:32px;'>
                    <div style='font-size:32px;font-weight:800;font-family:monospace;letter-spacing:3px;color:#1f2937;'>{$barcode}</div>
                </div>
                
                <div style='background:#fef3c7;padding:16px;border-radius:12px;border-left:4px solid #f59e0b;font-size:14px;color:#92400e;line-height:1.5;'>
                    <strong>⚠️ Security Notice:</strong> Present this digital ticket at the venue. 
                    Do not share or screenshot. Tampered tickets will be rejected.
                    {$dlLink}
                </div>
            </div>
            
            <!-- Footer -->
            <div style='background:linear-gradient(135deg,#1f2937,#374151);color:white;padding:24px;text-align:center;font-size:12px;'>
                <div>Powered by <span style='opacity:0.8;font-weight:500;'>Eventra</span></div>
                <div style='opacity:0.7;font-size:11px;margin-top:4px;'>Valid for one-time entry &bull; Non-refundable &bull; &copy; {$year}</div>
            </div>
        </div>
    </div>
    ";

    return sendEmail($to, $subject, $body, $attachments);
}
