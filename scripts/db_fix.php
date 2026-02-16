<?php
require_once 'config/database.php';

try {
    $pdo->exec("ALTER TABLE clients AUTO_INCREMENT = 1");
    echo "Successfully reset clients table auto-increment.\n";
} catch (PDOException $e) {
    echo "Error resetting auto-increment: " . $e->getMessage() . "\n";
}
?>