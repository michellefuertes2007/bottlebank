<?php
$c = mysqli_connect('127.0.0.1', 'root', '', 'bottlebank');
if (!$c) { die('conn fail '.mysqli_connect_error()); }
$pw = password_hash('password', PASSWORD_DEFAULT);
$stmt = mysqli_prepare($c, 'UPDATE user SET password = ? WHERE username = ?');
mysqli_stmt_bind_param($stmt, 'ss', $pw, $user);
$user = 'admin';
mysqli_stmt_execute($stmt);
echo "Updated admin password to 'password'\n";
mysqli_close($c);
