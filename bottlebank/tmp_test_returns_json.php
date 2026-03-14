<?php
// Simulate a GET request to api/returns.php for debugging
$_SERVER['REQUEST_METHOD'] = 'GET';
$_GET['customer'] = 'justin';

// Run from api/ directory so relative includes match what the web server sees
chdir(__DIR__ . '/api');

// Capture output
ob_start();
include __DIR__ . '/api/returns.php';
$resp = ob_get_clean();

echo "OUTPUT:\n";
echo $resp;
