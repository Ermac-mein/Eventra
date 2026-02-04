<?php
/**
 * Admin Entry Point
 * Redirects to dashboard or authentication gate
 */
require_once '../config/session-config.php';

if (isset($_SESSION['role']) && strtolower($_SESSION['role']) === 'admin') {
    header('Location: pages/adminDashboard.html');
} else {
    // Redirect to the centralized role selection gate
    header('Location: ../public/pages/auth-gate.html');
}
exit();
?>