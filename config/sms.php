<?php
// Twilio Production Integration Config + Validation
require_once __DIR__ . '/env-loader.php';

// Validate required env vars
if (empty($_ENV['TWILIO_SID']) || empty($_ENV['TWILIO_TOKEN']) || empty($_ENV['TWILIO_FROM'])) {
    error_log('[SMS Config] Twilio credentials missing. Set TWILIO_SID, TWILIO_TOKEN, TWILIO_FROM in .env');
    define('TWILIO_SMS_DISABLED', true);
} else {
    define('TWILIO_SMS_DISABLED', false);
}

define('TWILIO_SID', $_ENV['TWILIO_SID'] ?? '');
define('TWILIO_TOKEN', $_ENV['TWILIO_TOKEN'] ?? '');
define('TWILIO_FROM', $_ENV['TWILIO_FROM'] ?? '');

// Twilio API URL (fallback if SID empty)
define('TWILIO_SMS_URL', !empty(TWILIO_SID)
    ? "https://api.twilio.com/2010-04-01/Accounts/" . TWILIO_SID . "/Messages.json"
    : '');

// Usage check helper
function isSmsEnabled()
{
    return !defined('TWILIO_SMS_DISABLED') || !TWILIO_SMS_DISABLED;
}
?>