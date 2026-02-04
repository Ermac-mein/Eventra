<?php
require_once 'config/database.php';
try {
    $stmt = $pdo->query("DESCRIBE events");
    $columns = $stmt->fetchAll();
    header('Content-Type: application/json');
    echo json_encode($columns, JSON_PRETTY_PRINT);
} catch (Exception $e) {
    echo $e->getMessage();
}
?>