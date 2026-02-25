<?php
// Twilio Production Integration Config
require_once __DIR__ . '/env-loader.php';

define('TWILIO_SID', $_ENV['TWILIO_SID'] ?? '');
define('TWILIO_TOKEN', $_ENV['TWILIO_TOKEN'] ?? '');
define('TWILIO_FROM', $_ENV['TWILIO_FROM'] ?? '');

// Twilio API URL
define('TWILIO_SMS_URL', "https://api.twilio.com/2010-04-01/Accounts/" . TWILIO_SID . "/Messages.json");
