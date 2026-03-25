<?php
/**
 * Delete Client Profile API
 * Deletes the client's auth_account, which cascades to delete all their records (clients, events, media, etc.).
 * Also attempts to delete their media directory.
 */
header('Content-Type: application/json');
require_once '../../config/database.php';
require_once '../../includes/middleware/auth.php';

// Check authentication
$auth_id = checkAuth('client');

if ($_SERVER['REQUEST_METHOD'] !== 'DELETE' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

try {
    $pdo->beginTransaction();

    // Get client id for directory deletion
    $stmt = $pdo->prepare("SELECT id FROM clients WHERE client_auth_id = ?");
    $stmt->execute([$auth_id]);
    $client_id = $stmt->fetchColumn();

    // Delete the auth_accounts record. Due to ON DELETE CASCADE constraints,
    // this will automatically delete from clients, events, media, tickets, orders, etc.
    $deleteStmt = $pdo->prepare("DELETE FROM auth_accounts WHERE id = ? AND role = 'client'");
    $deleteStmt->execute([$auth_id]);

    if ($deleteStmt->rowCount() > 0) {
        $pdo->commit();

        // 1. Delete physical files associated with the client
        if ($client_id) {
            $client_media_dir = "../../uploads/media/client_{$client_id}/";
            if (is_dir($client_media_dir)) {
                $iterator = new RecursiveDirectoryIterator($client_media_dir, RecursiveDirectoryIterator::SKIP_DOTS);
                $files = new RecursiveIteratorIterator($iterator, RecursiveIteratorIterator::CHILD_FIRST);
                foreach($files as $file) {
                    if ($file->isDir()){
                        @rmdir($file->getRealPath());
                    } else {
                        @unlink($file->getRealPath());
                    }
                }
                @rmdir($client_media_dir);
            }
        }

        // 2. Destroy session to log them out
        session_unset();
        session_destroy();

        echo json_encode([
            'success' => true,
            'message' => 'Profile deleted successfully.'
        ]);
    } else {
        $pdo->rollBack();
        echo json_encode([
            'success' => false,
            'message' => 'Profile not found or already deleted.'
        ]);
    }
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("[Delete Profile DB Error] " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error.']);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("[Delete Profile Server Error] " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error.']);
}
