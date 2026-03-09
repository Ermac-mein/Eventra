<?php

header("Cross-Origin-Opener-Policy: same-origin-allow-popups");
header("Cross-Origin-Embedder-Policy: unsafe-none");

$uri = $_SERVER['REQUEST_URI'];
$path = parse_url($uri, PHP_URL_PATH);
$file = __DIR__ . '/../../' . ltrim($path, '/');

// If the file exists and is not a PHP file, serve it directly
if (file_exists($file) && !is_dir($file) && pathinfo($file, PATHINFO_EXTENSION) !== 'php') {
    $mime = [
        'css' => 'text/css',
        'js' => 'application/javascript',
        'svg' => 'image/svg+xml',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'html' => 'text/html',
        'json' => 'application/json'
    ];
    $ext = pathinfo($file, PATHINFO_EXTENSION);
    if (isset($mime[$ext])) {
        header("Content-Type: " . $mime[$ext]);
    }
    readfile($file);
    return true;
}

// Fallback to serving the PHP file or index.html
if (file_exists($file) && pathinfo($file, PATHINFO_EXTENSION) === 'php') {
    include $file;
    return true;
}

// Default fallback for clean URLs or missing files
return false;
