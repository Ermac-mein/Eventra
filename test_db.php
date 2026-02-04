<?php
require_once 'config/database.php';
echo "Database connection successful!\n";
try {
    $stmt = $pdo->query("SELECT 1");
    echo "Query successful!\n";
} catch (Exception $e) {
    echo "Query failed: " . $e->getMessage() . "\n";
}
?>