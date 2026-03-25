<?php
require 'config/database.php';
$stmt = $pdo->prepare("SELECT id FROM auth_accounts WHERE email = 'adaeze.okonkwo@eventng.com'");
$stmt->execute();
$auth_id = $stmt->fetchColumn();

// Generate a token
$token = bin2hex(random_bytes(32));
$expires = date('Y-m-d H:i:s', strtotime('+24 hours'));

$stmt = $pdo->prepare("INSERT INTO auth_tokens (auth_id, token, expires_at) VALUES (?, ?, ?)");
$stmt->execute([$auth_id, hash('sha256', $token), $expires]);

echo $token;
