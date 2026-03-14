<?php
$c = mysqli_connect('127.0.0.1', 'root', '', 'bottlebank');
if (!$c) { die('conn fail ' . mysqli_connect_error()); }
$customer = 'TestCustomer';
$r = mysqli_prepare($c, 'SELECT COUNT(*) AS cnt FROM deposit WHERE customer_name = ?');
mysqli_stmt_bind_param($r, 's', $customer);
mysqli_stmt_execute($r);
mysqli_stmt_bind_result($r, $cnt);
mysqli_stmt_fetch($r);
echo "deposits for $customer: $cnt\n";
mysqli_stmt_close($r);

$r = mysqli_prepare($c, 'SELECT COUNT(*) AS cnt FROM stock_log WHERE customer_name = ? AND action_type = "Deposit"');
mysqli_stmt_bind_param($r, 's', $customer);
mysqli_stmt_execute($r);
mysqli_stmt_bind_result($r, $cnt);
mysqli_stmt_fetch($r);
echo "stock_log deposit rows for $customer: $cnt\n";
mysqli_stmt_close($r);
mysqli_close($c);
