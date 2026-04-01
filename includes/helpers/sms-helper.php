<?php

/**
 * SMS Helper using Twilio
 */

require_once __DIR__ . '/../../config/sms.php';

/**
 * Send an SMS using Twilio API
 *
 * @param string $phoneNumber Recipient phone number in E.164 format
 * @param string $message SMS message content
 * @return array ['success' => bool, 'message' => string, 'sid' => string|null]
 */
function sendSMS($phoneNumber, $message)
{
    if (empty(TWILIO_SID) || empty(TWILIO_TOKEN) || empty(TWILIO_FROM)) {
        return ['success' => false, 'message' => 'Twilio credentials not configured'];
    }

    // ── Phone Number Normalization ──────────────────────────────────────────
    // Strip all non-numeric characters EXCEPT the plus sign
    $phoneNumber = preg_replace('/[^\d+]/', '', $phoneNumber);

    if (strpos($phoneNumber, '0') === 0 && strlen($phoneNumber) === 11) {
        // standard Nigeria local format: 080... -> +23480...
        $phoneNumber = '+234' . substr($phoneNumber, 1);
    } elseif (strpos($phoneNumber, '234') === 0 && (strlen($phoneNumber) === 13 || strlen($phoneNumber) === 12)) {
        // standard Nigeria international format without +: 23480... -> +23480...
        $phoneNumber = '+' . $phoneNumber;
    } elseif (strpos($phoneNumber, '+') !== 0) {
        // Fallback: strictly ensure it starts with + for international routing
        $phoneNumber = '+' . ltrim($phoneNumber, '+');
    }
    // ──────────────────────────────────────────────────────────────────────────

    $data = [
        'To' => $phoneNumber,
        'From' => TWILIO_FROM,
        'Body' => $message
    ];

    $postData = http_build_query($data);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, TWILIO_SMS_URL);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
    curl_setopt($ch, CURLOPT_USERPWD, TWILIO_SID . ':' . TWILIO_TOKEN);
    curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);

    $response = curl_exec($ch);
    $error = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($error) {
        error_log("Twilio SMS Error: " . $error);
        return ['success' => false, 'message' => "CURL Error: " . $error];
    }

    $result = json_decode($response, true);

    if ($httpCode >= 200 && $httpCode < 300) {
        return [
            'success' => true,
            'message' => 'SMS sent successfully',
            'sid' => $result['sid'] ?? null
        ];
    } else {
        error_log("Twilio API Error: " . ($result['message'] ?? $response));
        return [
            'success' => false,
            'message' => $result['message'] ?? 'Unknown Twilio error',
            'code' => $result['code'] ?? null
        ];
    }
}
