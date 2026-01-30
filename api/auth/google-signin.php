<?php
/**
 * Google Sign-In Redirect Handler
 * Handles the OAuth callback from Google
 */
header('Content-Type: application/json');
require_once '../../config/env-loader.php';
require_once '../../config/database.php';

// This endpoint would typically handle the OAuth callback
// For now, we're using client-side Google Sign-In with credential response
// This file serves as a placeholder for server-side OAuth flow if needed

echo json_encode([
    'success' => false,
    'message' => 'This endpoint is for server-side OAuth flow. Please use client-side Google Sign-In.'
]);
?>