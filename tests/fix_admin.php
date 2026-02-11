<?php
require_once 'config/database.php';
$hash = password_hash('admin@@12345', PASSWORD_DEFAULT);
$pdo->prepare("UPDATE auth_accounts SET password_hash = ? WHERE id = 1")->execute([$hash]);
$pdo->prepare("UPDATE admins SET password = ? WHERE auth_id = 1")->execute([$hash]);
echo "Hardcoded Admin password updated successfully.\n";
