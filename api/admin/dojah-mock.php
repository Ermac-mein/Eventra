<?php
/**
 * Dojah Mock Verification API
 * Simulates NIN/BVN verification for local development
 */
header('Content-Type: application/json');

// Get request body
$data = json_decode(file_get_contents('php://input'), true);
$type = $data['type'] ?? 'nin';
$number = $data['number'] ?? '';

// Basic validation
if (empty($number)) {
    echo json_encode([
        'success' => false,
        'message' => 'Number is required'
    ]);
    exit;
}

// Artificial delay to simulate network latency
usleep(500000); // 500ms

// Mock verification logic
$last_digit = substr(trim($number), -1);
$verified = false;

if ($last_digit === '1') {
    $verified = true;
} elseif ($last_digit === '0') {
    $verified = false;
} else {
    // 80% success rate for other numbers
    $verified = (rand(1, 100) <= 80);
}

$type_upper = strtoupper($type);

echo json_encode([
    'success' => true,
    'data' => [
        'verified' => $verified,
        'message' => $verified ? "$type_upper verified successfully" : "Invalid $type_upper number",
        'status' => $verified ? 'success' : 'failed'
    ]
]);
