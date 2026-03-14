<?php
$url = 'http://bb.test/api/returns.php?customer=justin';
$response = @file_get_contents($url);
if ($response === false) {
    echo "REQUEST FAILED\n";
    var_dump(error_get_last());
    exit(1);
}
header('Content-Type: text/plain');
echo $response;
