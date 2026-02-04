<?php
require_once 'config/database.php';

try {
    $pdo->exec("ALTER TABLE media ADD COLUMN folder_name VARCHAR(150) DEFAULT 'General' AFTER client_id");
    echo "Successfully added folder_name column to media table.\n";
} catch (PDOException $e) {
    if ($e->getCode() == '42S21') {
        echo "Column 'folder_name' already exists.\n";
    } else {
        echo "Error: " . $e->getMessage() . "\n";
    }
}
?>