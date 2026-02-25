<?php
/**
 * Barcode Helper
 * Generates secure unique barcodes for event tickets.
 */

function generateTicketBarcode($payment_id, $user_id, $event_id)
{
    // Generate a secure hash using payment details and a random salt
    $salt = bin2hex(random_bytes(16));
    $raw = "TKT-{$event_id}-{$user_id}-{$payment_id}-" . time() . "-{$salt}";

    // Using SHA256 for a reasonably short but secure string
    $hash = hash('sha256', $raw);

    // Return a format that's easy to read/scan but hard to guess
    return strtoupper(substr($hash, 0, 16));
}
