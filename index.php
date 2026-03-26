<?php
// Handle static files for PHP built-in server
if (php_sapi_name() === 'cli-server') {
    $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $fullPath = __DIR__ . $path;

    // Serve actual static files (images, css, js, html, etc.) directly
    if (file_exists($fullPath) && is_file($fullPath)) {
        // Let PHP serve recognized static types directly
        $ext = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));
        $staticExts = ['css', 'js', 'png', 'jpg', 'jpeg', 'gif', 'svg', 'ico', 'woff', 'woff2', 'ttf', 'html', 'htm', 'webp', 'mp4', 'pdf', 'map'];
        if (in_array($ext, $staticExts)) {
            return false;
        }
    }
    // All other requests (including directories lik/api/admin/) fall through to the router
}


// Debug logging
file_put_contents(__DIR__ . '/logs/router.log', date('[Y-m-d H:i:s] ') . $_SERVER['REQUEST_METHOD'] . ' ' . $_SERVER['REQUEST_URI'] . PHP_EOL, FILE_APPEND);

require_once __DIR__ . '/includes/core/Autoloader.php';
require_once __DIR__ . '/config/database.php';


// Dispatch routing
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Legacy portal redirects
$legacyRedirects = [
    '/admin/login' => '/admin/pages/adminLogin.html',
    '/client/login' => '/client/pages/clientLogin.html',
    '/user/login' => '/public/pages/index.html'
];
if (isset($legacyRedirects[$uri])) {
    header('Location: ' . $legacyRedirects[$uri]);
    exit;
}

// Basic API Routing (Dynamic replacement for routes.php)
if (strpos($uri, '/api/') === 0) {
    $apiPath = substr($uri, 5); // Strip /api/
    
    // Safety: Strip .php to prevent .php.php resolution
    $cleanPath = preg_replace('/\.php$/', '', $apiPath);

    // Handle pluralization inconsistencies for main portals
    $mappings = [
        'client/login' => 'clients/login.php',
        'user/login'   => 'users/login.php',
        'admin/login'  => 'admin/login.php',
        'auth/login'   => 'auth/login.php',
        'admin/auth/check-session' => 'auth/check-session.php',
        'client/auth/check-session' => 'auth/check-session.php',
        'user/auth/google-login'   => 'auth/google-handler.php',
        'client/auth/google-login' => 'auth/google-handler.php',
        'admin/auth/google-login'  => 'auth/google-handler.php'
    ];

    $targetFile = $mappings[$cleanPath] ?? ($cleanPath . '.php');
    $fullPath = __DIR__ . '/api/' . $targetFile;

    if (file_exists($fullPath) && is_file($fullPath)) {
        require_once $fullPath;
        exit;
    }

    // fallback for other common paths if needed
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'API Route not found: ' . $uri]);
    exit;
}

// Fallback for non-API routes (e.g. root)
if ($uri === '/' || $uri === '/index.php') {
    header('Location: /public/pages/index.html');
    exit;
}

// If no route matches, let it fall through or 404
http_response_code(404);
echo "404 Not Found (" . htmlspecialchars($uri) . ")";
exit;
