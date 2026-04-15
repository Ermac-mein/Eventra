<?php

require_once __DIR__ . '/../config/database.php';

try {
    $pdo->beginTransaction();

    echo "Applying cascading deletion updates...\n";

    // 1. Update auth_logs foreign key
    echo "Updating auth_logs foreign key...\n";
    $pdo->exec("ALTER TABLE auth_logs DROP FOREIGN KEY IF EXISTS fk_auth_logs_auth");
    $pdo->exec("ALTER TABLE auth_logs 
               ADD CONSTRAINT fk_auth_logs_auth 
               FOREIGN KEY (auth_id) REFERENCES auth_accounts (id) 
               ON DELETE CASCADE ON UPDATE CASCADE");

    // 2. Add Trigger to clients table
    echo "Adding trigger tr_delete_client_auth...\n";
    $pdo->exec("DROP TRIGGER IF EXISTS tr_delete_client_auth");
    
    // Note: PDO exec doesn't support DELIMITER, we just pass the raw SQL
    $triggerSql = "
        CREATE TRIGGER tr_delete_client_auth
        BEFORE DELETE ON clients
        FOR EACH ROW
        BEGIN
            DELETE FROM auth_accounts WHERE id = OLD.client_auth_id;
        END
    ";
    $pdo->exec($triggerSql);

    $pdo->commit();
    echo "Successfully applied all updates.\n";

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "Error applying updates: " . $e->getMessage() . "\n";
    exit(1);
}
