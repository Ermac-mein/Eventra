<?php
// Mock session
session_start();
$_SESSION['user_id'] = 1;
$_SESSION['role'] = 'client';
$_SESSION['auth_token'] = 'dummy_token';

echo "Testing Search API...\n";
$_GET['q'] = 'test';
ob_start();
require_once 'api/utils/search.php';
$searchOutput = ob_get_clean();
echo "Search result: $searchOutput\n\n";

echo "Testing Chart Data API...\n";
$_GET['period'] = '7days';
ob_start();
require_once 'api/stats/get-chart-data.php';
$chartOutput = ob_get_clean();
echo "Chart result: $chartOutput\n";
?>