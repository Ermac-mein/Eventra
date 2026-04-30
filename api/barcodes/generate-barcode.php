<?php

/**
 * Generate QR Code API
 * Returns a PNG image of a QR code for the provided text.
 * GET ?text=...
 */

require_once '../../vendor/autoload.php';

use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

$text = $_GET['text'] ?? '';

if (empty($text)) {
    header('Content-Type: image/png');
    // Return a blank or error image if needed, but for now just exit
    exit;
}

try {
    $options = new QROptions([
        'version'    => QRCode::VERSION_AUTO,
        'outputType' => QRCode::OUTPUT_IMAGE_PNG,
        'eccLevel'   => QRCode::ECC_M,
        'scale'      => 10,
    ]);

    $qrcode = new QRCode($options);
    
    header('Content-Type: image/png');
    echo $qrcode->render($text);
} catch (Exception $e) {
    error_log('[generate-barcode.php] QR error: ' . $e->getMessage());
}

