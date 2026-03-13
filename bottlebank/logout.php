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
  <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22%3E%3Ctext y=%2275%22 font-size=%2275%22 font-weight=%22bold%22 fill=%22%2326a69a%22%3EBB%3C/text%3E%3C/svg%3E" type="image/svg+xml">
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
    @media (max-width: 600px) {
      .box {
        padding: 15px 10px;
        font-size: 14px;
      }
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
