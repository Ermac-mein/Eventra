<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Test 1: Admin portal
$_SERVER['HTTP_X_EVENTRA_PORTAL'] = 'admin';
require 'config/session-config.php';
echo "Test 1 (Admin header): Session name = " . session_name() . ", Status = " . session_status() . "\n";
$firstSessionId = session_id();

// We need to reset everything for the next test
session_write_close();
session_name('PHPSESSID'); // Reset name
session_id(''); // Reset ID
unset($_SESSION);

?>
