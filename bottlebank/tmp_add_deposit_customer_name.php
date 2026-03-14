<?php
$mysqli = new mysqli('localhost','root','','bottlebank');
if ($mysqli->connect_error) {
    die('Connect fail: '.$mysqli->connect_error);
}
$res = $mysqli->query("SHOW COLUMNS FROM deposit LIKE 'customer_name'");
if ($res && $res->num_rows>0) {
    echo "customer_name already exists\n";
    exit(0);
}
$sql = "ALTER TABLE deposit ADD COLUMN customer_name VARCHAR(100) DEFAULT NULL";
if ($mysqli->query($sql) === false) {
    die('ALTER failed: '.$mysqli->error);
}
echo "Added customer_name to deposit\n";
$mysqli->close();
