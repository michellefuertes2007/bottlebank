<?php
$url = 'http://bb.test/api/deposit.php?customer=justin';
$ctx = stream_context_create(['http' => ['timeout' => 5]]);
$response = @file_get_contents($url, false, $ctx);
if ($response === false) {
    echo "REQUEST FAILED\n";
    $err = error_get_last();
    print_r($err);
    exit(1);
}
header('Content-Type: text/plain');
echo $response;
