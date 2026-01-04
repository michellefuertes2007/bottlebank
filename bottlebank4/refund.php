<?php
include 'db_connect.php';
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit(); }

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $amount = $_POST['amount'];
    $user_id = $_SESSION['user_id'];

    $sql = "INSERT INTO refund (user_id, amount, refund_date) VALUES ('$user_id', '$amount', NOW())";
    $conn->query($sql);
    echo "<script>alert('Refund recorded!');</script>";
}
?>
<!DOCTYPE html>
<html>
<head><title>Refund</title>
<link rel="stylesheet" href="asset/style.css"></head>
<body>
    <div class="app">
  <div class="topbar">
    <div class="brand"><div class="logo">BB</div><div><h1>Refund</h1><p class="kv">Add new Refund</p></div></div>
    <div class="menu-wrap"><a href="index.php" class="kv">← Back to Dashboard</a></div>
  </div>
<h2>Refund Request</h2>
<form method="POST">
    Amount: <input type="number" name="amount" required><br><br>
    <button type="submit">Submit</button>
</form>
</body>
</html>
