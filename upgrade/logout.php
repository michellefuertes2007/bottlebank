<?php
session_start();
session_unset();
session_destroy();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta http-equiv="refresh" content="2;url=login.php">
  <title>Logging Out...</title>
  <style>
    body {
      font-family: 'Poppins', sans-serif;
      background: #f0f5f4;
      color: #333;
      display: flex;
      align-items: center;
      justify-content: center;
      height: 100vh;
      flex-direction: column;
    }
    .box {
      background: white;
      padding: 25px 40px;
      border-radius: 10px;
      box-shadow: 0 5px 15px rgba(0,0,0,0.1);
      text-align: center;
    }
  </style>
</head>
<body>
  <div class="box">
    <h2>Logging out...</h2>
    <p>You’ll be redirected shortly.</p>
  </div>
</body>
</html>
