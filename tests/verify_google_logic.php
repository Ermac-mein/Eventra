<?php
// Mock test to verify logic in google-handler.php
// This doesn't run the actual handler but verifies the logic we applied.

function simulateGooglePayload($email_verified)
{
    echo "Simulating payload with email_verified = " . ($email_verified ? 'true' : 'false') . "\n";

    // The logic we applied in google-handler.php:
    $payload = [
        'email' => 'test@example.com',
        'email_verified' => $email_verified
    ];

    if (!isset($payload['email_verified']) || $payload['email_verified'] !== true) {
        // Log as a warning but proceed
        echo "LOGGED WARNING: Google Auth Warning: Email " . $payload['email'] . " is not marked as verified by Google.\n";
    }

    echo "Result: PROCEEDED TO LOGIN FLOW\n";
    return true;
}

echo "--- Test 1: Verified Email ---\n";
simulateGooglePayload(true);

echo "\n--- Test 2: Unverified Email ---\n";
simulateGooglePayload(false);

echo "\n--- Test 3: Missing verified field ---\n";
simulateGooglePayload(null);
?>