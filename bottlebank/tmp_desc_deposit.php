<?php
$conn = mysqli_connect('localhost', 'root', '', 'bottlebank');
if (!$conn) die('DB conn fail: ' . mysqli_connect_error());
$res = $conn->query('DESCRIBE deposit');
while ($r = $res->fetch_assoc()) {
    echo $r['Field'] . ' - ' . $r['Type'] . "\n";
}
$conn->close();
