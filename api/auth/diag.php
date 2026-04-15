<?php
header('Content-Type: application/json');

function diag_log($msg) {
    echo json_encode(['step' => $msg]) . "\n";
    flush();
}

diag_log("Starting Diagnostic...");

// 1. Check PHP Version and Extensions
diag_log("PHP Version: " . PHP_VERSION);
diag_log("PDO MySQL Extension: " . (extension_loaded('pdo_mysql') ? 'Loaded' : 'MISSING'));

// 2. Load Config and Database
try {
    diag_log("Requiring database.php...");
    require_once __DIR__ . '/../../config/database.php';
    diag_log("Database config loaded.");
    
    if (!isset($pdo)) {
        diag_log("ERROR: \$pdo variable not set after requiring database.php");
    } else {
        diag_log("PDO object exists.");
        $status = $pdo->getAttribute(PDO::ATTR_CONNECTION_STATUS);
        diag_log("Connection Status: " . $status);
    }
} catch (Throwable $e) {
    diag_log("CRITICAL ERROR during DB load: " . $e->getMessage());
}

// 3. Check Tables
$tables = ['auth_accounts', 'clients', 'admins', 'users', 'notifications', 'auth_logs'];
foreach ($tables as $table) {
    try {
        $stmt = $pdo->query("DESCRIBE $table");
        diag_log("Table '$table' exists.");
        $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
        diag_log("Columns in '$table': " . implode(', ', $columns));
    } catch (Throwable $e) {
        diag_log("ERROR: Table '$table' check failed: " . $e->getMessage());
    }
}

// 4. Check for require_once targets in register.php
$files = [
    __DIR__ . '/../../includes/helpers/entity-resolver.php',
    __DIR__ . '/../utils/id-generator.php',
    __DIR__ . '/../utils/notification-helper.php'
];

foreach ($files as $file) {
    if (file_exists($file)) {
        diag_log("File exists: " . basename($file));
        try {
            require_once $file;
            diag_log("File required successfully: " . basename($file));
        } catch (Throwable $e) {
            diag_log("ERROR requiring " . basename($file) . ": " . $e->getMessage());
        }
    } else {
        diag_log("MISSING FILE: " . $file);
    }
}

diag_log("Diagnostic Complete.");
