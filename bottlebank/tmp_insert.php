<?php
$c = mysqli_connect('127.0.0.1', 'root', '', 'bottlebank');
if (!$c) {
    die('conn fail '.mysqli_connect_error());
}
$stmt = mysqli_prepare($c, 'INSERT INTO deposit (user_id, customer_name, bottle_type, quantity, with_case, case_quantity, amount, deposit_date, bottle_size) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), ?)');
if (!$stmt) {
    die('prepare failed: '.mysqli_error($c));
}
$user_id=1; $customer='TestCust'; $btype='coke'; $qty=5; $with_case=0; $case_qty=0; $amount=75.0; $bsize='8oz';
mysqli_stmt_bind_param($stmt,'issiiids',$user_id,$customer,$btype,$qty,$with_case,$case_qty,$amount,$bsize);
if (!mysqli_stmt_execute($stmt)) {
    die('execute failed: '.mysqli_stmt_error($stmt));
}
echo "Inserted id: " . mysqli_stmt_insert_id($stmt) . "\n";
mysqli_stmt_close($stmt);
mysqli_close($c);
