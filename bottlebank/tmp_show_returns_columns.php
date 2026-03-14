<?php
$mysqli = new mysqli('localhost', 'root', '', 'bottlebank');
if ($mysqli->connect_error) {
    echo "CONNECT ERROR: " . $mysqli->connect_error;
    exit(1);
}
$res = $mysqli->query('SHOW COLUMNS FROM returns');
if (!$res) {
    echo "QUERY ERROR: " . $mysqli->error;
    exit(1);
}
while ($row = $res->fetch_assoc()) {
    echo $row['Field'] . "\n";
}
$mysqli->close();
