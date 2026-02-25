<?php
require_once 'config/database.php';

echo "PHP timezone: " . date_default_timezone_get() . "\n";
echo "PHP local time: " . date('Y-m-d H:i:s') . "\n";

$stmt = $pdo->query("SELECT @@global.time_zone as global_tz, @@session.time_zone as session_tz, NOW() as db_time");
$row = $stmt->fetch();
print_r($row);
