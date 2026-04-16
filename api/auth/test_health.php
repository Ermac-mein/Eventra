<?php
header('Content-Type: application/json');

$results = [
    'php_version' => PHP_VERSION,
    'includes' => []
];

function checkFile($path) {
    return [
        'path' => $path,
        'exists' => file_exists($path),
        'readable' => is_readable($path)
    ];
}

$results['includes']['config'] = checkFile(__DIR__ . '/../../config.php');
$results['includes']['database'] = checkFile(__DIR__ . '/../../config/database.php');
$results['includes']['vendor'] = checkFile(__DIR__ . '/../../vendor/autoload.php');
$results['includes']['email_helper'] = checkFile(__DIR__ . '/../../includes/helpers/email-helper.php');

echo json_encode($results, JSON_PRETTY_PRINT);
