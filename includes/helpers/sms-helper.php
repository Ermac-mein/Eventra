<?php
/**
 * SMS Helper (Twilio Integration)
 * Handles sending OTPs, reminders and confirmations.
 */
require_once __DIR__ . '/../../config/sms.php';
require_once __DIR__ . '/../../config/database.php';

function sendSMS($to, $message, $type = 'admin_notification', $userId = null, $clientId = null)
{
    global $pdo;

    if (empty(TWILIO_SID) || empty(TWILIO_TOKEN) || empty(TWILIO_FROM)) {
        error_log("[Twilio] Configuration missing.");
        return ['success' => false, 'message' => 'SMS configuration missing.'];
    }

    $auth = base64_encode(TWILIO_SID . ":" . TWILIO_TOKEN);
    $data = [
        'To' => $to,
        'From' => TWILIO_FROM,
        'Body' => $message
    ];

    $ch = curl_init(TWILIO_SMS_URL);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Basic $auth",
        "Content-Type: application/x-www-form-urlencoded"
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));

    $response = curl_exec($ch);
    $info = curl_getinfo($ch);
    curl_close($ch);

    $responseData = json_decode($response, true);
    $status = ($info['http_code'] == 201) ? 'sent' : 'failed';

    // Log the SMS attempt
    try {
        $stmt = $pdo->prepare("INSERT INTO sms_logs (user_id, client_id, phone_number, message_type, message_body, twilio_sid, twilio_status, status, price, price_unit) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $userId,
            $clientId,
            $to,
            $type,
            $message,
            $responseData['sid'] ?? null,
            $responseData['status'] ?? null,
            $status,
            $responseData['price'] ?? null,
            $responseData['price_unit'] ?? null
        ]);
    } catch (PDOException $e) {
        error_log("Failed to log SMS: " . $e->getMessage());
    }

    return [
        'success' => ($status === 'sent'),
        'data' => $responseData
    ];
}
