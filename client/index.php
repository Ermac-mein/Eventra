<?php
/**
 * Client Entry Point
 * Redirects to dashboard or authentication gate
 */
require_once '../config/session-config.php';

if (isset($_SESSION['role']) && strtolower($_SESSION['role']) === 'client') {
    header('Location: pages/clientDashboard.html');
} else {
    // Redirect to the centralized role selection gate
    header('Location: pages/clientLogin.html');
}
exit();
?>