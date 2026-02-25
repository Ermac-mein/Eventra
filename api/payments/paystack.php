<?php
/**
 * Paystack Configuration API
 * Returns Paystack Public Key for frontend inline popup use
 * Does NOT expose secret key
 */
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
require_once '../../config/env-loader.php';

try {
    $paystackConfig = [
        'success' => true,
        'public_key' => $_ENV['PAYSTACK_PUBLIC_KEY'] ?? ''
    ];

    if (empty($paystackConfig['public_key'])) {
        echo json_encode([
            'success' => false,
            'message' => 'Paystack Public Key is not configured'
        ]);
        exit;
    }

    echo json_encode($paystackConfig);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Failed to load Paystack configuration'
    ]);
}
