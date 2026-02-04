<?php
require_once 'config/database.php';

echo "--- auth_accounts ---\n";
$stmt = $pdo->query("SELECT id, email, role FROM auth_accounts");
while ($row = $stmt->fetch()) {
    print_r($row);
}

echo "\n--- clients ---\n";
$stmt = $pdo->query("SELECT * FROM clients");
while ($row = $stmt->fetch()) {
    print_r($row);
}

echo "\n--- users ---\n";
$stmt = $pdo->query("SELECT * FROM users");
while ($row = $stmt->fetch()) {
    print_r($row);
}
?>