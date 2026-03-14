<?php
$c = mysqli_connect('127.0.0.1', 'root', '', 'bottlebank');
if (!$c) {
    die('conn fail '.mysqli_connect_error());
}
$tables = ['deposit', 'stock_log', 'bottle_types'];

// Show existing users for login test
echo "\n=== user accounts ===\n";
$ru = mysqli_query($c, 'SELECT user_id, username, role FROM user');
while ($u = mysqli_fetch_assoc($ru)) {
    echo "{$u['user_id']}\t{$u['username']}\t{$u['role']}\n";
}

foreach ($tables as $table) {
    echo "\n=== $table ===\n";
    $r = mysqli_query($c, "SHOW COLUMNS FROM $table");
    while ($row = mysqli_fetch_assoc($r)) {
        echo $row['Field'] . "\t" . $row['Type'] . "\n";
    }
    $count = mysqli_query($c, "SELECT COUNT(*) AS cnt FROM $table");
    $cntRow = mysqli_fetch_assoc($count);
    echo "TOTAL ROWS: " . ($cntRow['cnt'] ?? 0) . "\n";
}
mysqli_close($c);
