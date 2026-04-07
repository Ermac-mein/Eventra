<?php
/**
 * Database Connection Diagnostic Tool
 * This script displays the effective database configuration being used by the app.
 * IMPORTANT: Delete this file after use for security!
 */

// Enable error reporting for diagnostics
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../../config/env-loader.php';

echo "<h2>Eventra Database Connection Diagnostic</h2>";

echo "<h3>Environment Loading</h3>";
$env_file = __DIR__ . '/../../.env';
if (file_exists($env_file)) {
    echo "<p style='color: green;'>✅ .env file found at " . realpath($env_file) . "</p>";
} else {
    echo "<p style='color: red;'>❌ .env file NOT found at " . realpath($env_file) . "</p>";
    echo "<p>If you have hardcoded credentials in config/database.php, this is fine. If not, the app will use defaults.</p>";
}

// In some shared hosting, getenv() might return false even if putenv() was used in the same request,
// or $_ENV might be empty. That's why we improved get_env_var().
function check_var($key) {
    global $_ENV, $_SERVER;
    $sources = [];
    if (isset($_ENV[$key])) $sources[] = "\$_ENV";
    if (isset($_SERVER[$key])) $sources[] = "\$_SERVER";
    if (getenv($key) !== false) $sources[] = "getenv()";
    
    $val = (isset($_ENV[$key])) ? $_ENV[$key] : (isset($_SERVER[$key]) ? $_SERVER[$key] : getenv($key));
    
    if ($val === false) {
        echo "<b>$key</b>: <span style='color: red;'>Not set in any source</span><br>";
    } else {
        $display_val = ($key === 'DB_PASSWORD') ? (strlen($val) > 2 ? substr($val, 0, 2) . '****' : '****') : $val;
        echo "<b>$key</b>: <span style='color: green;'>$display_val</span> (Found in: " . implode(', ', $sources) . ")<br>";
    }
}

echo "<h3>Effective Configuration</h3>";
check_var('DB_HOST');
check_var('DB_PORT');
check_var('DB_DATABASE');
check_var('DB_USERNAME');
check_var('DB_PASSWORD');
check_var('APP_DEBUG');

echo "<h3>Attempting Connection</h3>";
// Now use the application's actual database configuration
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/classes/Database.php';

$test_result = Database::testConnection();

if ($test_result['success']) {
    echo "<h4 style='color: green;'>✅ SUCCESS: Connected to database!</h4>";
    echo "<p>MySQL Version: " . $test_result['version'] . "</p>";
} else {
    echo "<h4 style='color: red;'>❌ FAILURE: Could not connect to database</h4>";
    echo "<p><b>Error Message:</b> " . $test_result['message'] . "</p>";
    
    echo "<h4>Troubleshooting Tips for 'Connection refused':</h4>";
    echo "<ul>
        <li><b>Is the DB_HOST correct?</b> On InfinityFree, 'localhost' or '127.0.0.1' rarely work. It should be something like 'sqlXXX.infinityfree.com'.</li>
        <li><b>Is the DB_PORT correct?</b> Most MySQL servers use 3306, but check your hosting panel.</li>
        <li><b>Remote MySQL Access:</b> Some hosts require you to whitelist IPs, but for InfinityFree it usually just works if the host is correct.</li>
        <li><b>Is MySQL Running?</b> The 'Connection refused' error means something is physically blocking the connection or nothing is listening on that port/host.</li>
    </ul>";
}

echo "<hr><p style='font-size: 0.8em; color: #666;'>This script is for debugging only. PLEASE DELETE IT from your server once the connection is working.</p>";
