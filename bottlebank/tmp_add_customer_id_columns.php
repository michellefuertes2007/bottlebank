<?php
$mysqli = new mysqli('localhost', 'root', '', 'bottlebank');
if ($mysqli->connect_error) {
    echo "CONNECT ERROR: " . $mysqli->connect_error;
    exit(1);
}

$tables = ['deposit', 'stock_log'];
foreach ($tables as $t) {
    $res = $mysqli->query("SHOW COLUMNS FROM `$t` LIKE 'customer_id'");
    if ($res && $res->num_rows > 0) {
        echo "$t already has customer_id\n";
        continue;
    }

    $sql = "ALTER TABLE `$t` ADD COLUMN `customer_id` INT(11) DEFAULT NULL";
    if ($mysqli->query($sql) === false) {
        echo "Failed to add customer_id to $t: " . $mysqli->error . "\n";
    } else {
        echo "Added customer_id to $t\n";
    }
}

$mysqli->close();
