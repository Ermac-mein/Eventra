<?php

header('Content-Type: application/json');
header("Cross-Origin-Opener-Policy: same-origin-allow-popups");
$auth_intent = 'user';
require_once __DIR__ . '/../../auth/google-handler.php';
