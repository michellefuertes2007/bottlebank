<?php
include 'db_connect.php';
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit(); }

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $bottle_type = $_POST['bottle_type'];
    $quantity = $_POST['quantity'];
    $user_id = $_SESSION['user_id'];

    $sql = "INSERT INTO returns (user_id, bottle_type, quantity, return_date) 
            VALUES ('$user_id', '$bottle_type', '$quantity', NOW())";
    $conn->query($sql);
    echo "<script>alert('Return recorded!');</script>";
}
?>
<!DOCTYPE html>
<html>
<head><title>Return</title>
<link rel="stylesheet" href="asset/style.css"></head>
<body>
 <div class="app">
  <div class="topbar">
    <div class="brand"><div class="logo">BB</div><div><h1>Return</h1><p class="kv">Add new Return</p></div></div>
    <div class="menu-wrap"><a href="index.php" class="kv">← Back to Dashboard</a></div>
  </div>
<form method="POST">
    Bottle Type: <input type="text" name="bottle_type" required><br><br>
    Quantity: <input type="number" name="quantity" required><br><br>
    <button type="submit">Submit</button>
</form>
</body>
</html>
