<?php
include 'db_connect.php';
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit(); }

$user_id = $_SESSION['user_id'];

$deposit = $conn->query("SELECT * FROM deposit WHERE user_id='$user_id'");
$returns = $conn->query("SELECT * FROM returns WHERE user_id='$user_id'");
$refund = $conn->query("SELECT * FROM refund WHERE user_id='$user_id'");
?>
<!DOCTYPE html>
<html>
<head><title>Stock Log</title>
<link rel="stylesheet" href="asset/style.css"></head>
<body>
     <div class="app">
  <div class="topbar">
    <div class="brand"><div class="logo">BB</div><div><h1>stock_log</h1></div></div>
    <div class="menu-wrap"><a href="index.php" class="kv">← Back to Dashboard</a></div>
  </div>

<h3>Deposits</h3>
<table border="1">
<tr><th>Bottle Type</th><th>Quantity</th><th>Date</th></tr>
<?php while($row=$deposit->fetch_assoc()): ?>
<tr><td><?= $row['bottle_type'] ?></td><td><?= $row['quantity'] ?></td><td><?= $row['deposit_date'] ?></td></tr>
<?php endwhile; ?>
</table>

<h3>Returns</h3>
<table border="1">
<tr><th>Bottle Type</th><th>Quantity</th><th>Date</th></tr>
<?php while($row=$returns->fetch_assoc()): ?>
<tr><td><?= $row['bottle_type'] ?></td><td><?= $row['quantity'] ?></td><td><?= $row['return_date'] ?></td></tr>
<?php endwhile; ?>
</table>

<h3>Refunds</h3>
<table border="1">
<tr><th>Amount</th><th>Date</th></tr>
<?php while($row=$refund->fetch_assoc()): ?>
<tr><td><?= $row['amount'] ?></td><td><?= $row['refund_date'] ?></td></tr>
<?php endwhile; ?>
</table>
</body>
</html>
