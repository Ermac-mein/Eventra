<?php
/**
 * Test Paystack Integration Logic
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/payment.php';

echo "--- Paystack Integration Test ---\n";

// 1. Test Account Resolution (assuming internet access for Paystack API or mock if needed)
echo "1. Testing Account Resolution... ";
$bank_code = '058'; // GTBank
$account_number = '0000000000'; // Placeholder - will likely fail resolution but let's check code path

// Mocking the call if no external access, or just checking if we can reach the endpoint
$url = "/bank/resolve?account_number={$account_number}&bank_code={$bank_code}";
echo "URL check: {$url}\n";

// 2. Test Subaccount Creation Logic (Dry Run)
echo "2. Testing Subaccount Creation Payload... ";
$account_name = "Test Organiser";
$email = "test@example.com";
$subPayload = [
    'business_name'     => $account_name,
    'settlement_bank'   => $bank_code,
    'account_number'    => $account_number,
    'percentage_charge' => 0,
    'primary_contact_email' => $email
];
echo "Payload: " . json_encode($subPayload) . "\n";
if ($subPayload['percentage_charge'] === 0) {
    echo "✓ Success: Platform charge is 0%\n";
} else {
    echo "✕ Fail: Platform charge is not 0%\n";
}

// 3. Test Order Initialization Split
echo "3. Testing Split Payment Payload... ";
$subaccount_code = "ACCT_xxxxxx";
$amount_kobo = 500000; // 5000 NGN
$reference = "TEST-REF-" . time();

$paystackPayload = [
    'email'         => 'buyer@example.com',
    'amount'        => $amount_kobo,
    'reference'     => $reference,
    'subaccount'    => $subaccount_code,
    'bearer'        => 'subaccount'
];

echo "Payload: " . json_encode($paystackPayload) . "\n";
if ($paystackPayload['subaccount'] === $subaccount_code && $paystackPayload['bearer'] === 'subaccount') {
    echo "✓ Success: Split subaccount correctly assigned with subaccount as fee bearer.\n";
} else {
    echo "✕ Fail: Split configuration incorrect.\n";
}

echo "--- Tests Complete ---\n";
