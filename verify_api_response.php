<?php
// Simulate Session
session_start();
$_SESSION['user_id'] = 2; // John Doe (Client)
$_SESSION['role'] = 'client';

// Buffer output
ob_start();
chdir('api/stats');
require 'get-client-dashboard-stats.php';
$output = ob_get_clean();

$data = json_decode($output, true);

echo "--- API Response Verification ---\n";
if ($data['success'] === true) {
    echo "Success: true\n";
    echo "Stats Keys: " . implode(', ', array_keys($data['stats'])) . "\n";

    if (!empty($data['recent_sales'])) {
        echo "Recent Sales Entry 0 Keys: " . implode(', ', array_keys($data['recent_sales'][0])) . "\n";
        if (isset($data['recent_sales'][0]['user_email'])) {
            echo "User Email Found: " . $data['recent_sales'][0]['user_email'] . "\n";
            echo "\033[32mPASSED\033[0m\n";
        } else {
            echo "User Email Missing!\n";
            echo "\033[31mFAILED\033[0m\n";
        }
    } else {
        echo "No recent sales found to verify email field.\n";
        echo "\033[33mSKIPPED (No Data)\033[0m\n";
    }
} else {
    echo "Success: false\n";
    echo "Message: " . ($data['message'] ?? 'Unknown') . "\n";
    echo "\033[31mFAILED\033[0m\n";
}
?>