<?php
// Paystack Production Integration Config
require_once __DIR__ . '/env-loader.php';

define('PAYSTACK_PUBLIC_KEY', $_ENV['PAYSTACK_PUBLIC_KEY'] ?? '');
define('PAYSTACK_SECRET_KEY', $_ENV['PAYSTACK_SECRET_KEY'] ?? '');
define('PAYSTACK_WEBHOOK_SECRET', $_ENV['PAYSTACK_WEBHOOK_SECRET'] ?? '');

function verifyPaystackSignature($payload, $signature_header)
{
    if (empty(PAYSTACK_WEBHOOK_SECRET))
        return false;
    return hash_equals(hash_hmac('sha512', $payload, PAYSTACK_WEBHOOK_SECRET), $signature_header);
}
