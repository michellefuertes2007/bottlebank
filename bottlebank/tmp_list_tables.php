<?php
$conn = mysqli_connect('localhost', 'root', '', 'bottlebank');
if (!$conn) {
    die('DB conn fail: ' . mysqli_connect_error());
}
$res = $conn->query('SHOW TABLES');
while ($r = $res->fetch_row()) {
    echo $r[0] . "\n";
}
$conn->close();
