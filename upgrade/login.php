<?php
session_start();
require 'includes/db_connect.php';
// Ensure log timestamps use local timezone (change if different)
date_default_timezone_set('Asia/Manila');

$error = "";
$success = "";
if (isset($_GET['registered'])) {
    $success = "Account created successfully. Please login.";
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username = trim($_POST['username']);
    $password = $_POST['password'];


    $hasForce = false;
    $col = $conn->query("SHOW COLUMNS FROM user LIKE 'force_change'");
    if ($col && $col->num_rows > 0) {
        $hasForce = true;
    }

    if ($hasForce) {
        $stmt = $conn->prepare("SELECT user_id, password, username, force_change, role FROM user WHERE username = ?");
    } else {
        $stmt = $conn->prepare("SELECT user_id, password, username, role FROM user WHERE username = ?");
    }
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    // helper logger
    $logFile = __DIR__ . DIRECTORY_SEPARATOR . 'auth.log';
    $log = function($line) use ($logFile) {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'cli';
        $ts = date('Y-m-d H:i:s');
        file_put_contents($logFile, "[$ts] [$ip] " . $line . PHP_EOL, FILE_APPEND | LOCK_EX);
    };

    if ($result->num_rows === 1) {
        $row = $result->fetch_assoc();

        if (password_verify($password, $row['password'])) {
            // Password is correct
            session_regenerate_id(true);
            $_SESSION['user_id'] = $row['user_id'];
            $_SESSION['username'] = $row['username'];
            $_SESSION['role'] = $row['role'];

            $log("LOGIN SUCCESS: user={$row['username']} id={$row['user_id']}");

            // If user is required to change password, redirect to change page
            if (!empty($row['force_change']) && $row['force_change'] == 1) {
                $_SESSION['force_change'] = 1;
                $log("FORCE_CHANGE: user={$row['username']} id={$row['user_id']}");
                header("Location: change_password.php");
                exit();
            }

            // Admin check
            if ($row['role'] === 'admin') {
                header("Location: admin/admin_panel.php"); // Admin goes to admin panel
            } else {
                header("Location: index.php"); // Regular users go to dashboard
            }
            exit();
        } else {
            $error = "Invalid password.";
            $log("LOGIN FAILED: invalid password for user={$username}");
        }
    } else {
        $error = "User not found.";
        $log("LOGIN FAILED: user not found user={$username}");
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Login | BottleBank</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<style>
* {
  margin: 0;
  padding: 0;
  box-sizing: border-box;
}

html, body {
  width: 100%;
  height: 100%;
}

body {
  display: flex;
  justify-content: center;
  align-items: center;
  min-height: 100vh;
  font-family: 'Poppins', 'Segoe UI', sans-serif;
  background: linear-gradient(135deg, #0077cc 0%, #00c16e 100%);
  padding: 20px;
}

.container {
  width: 100%;
  max-width: 400px;
  display: flex;
  justify-content: center;
  align-items: center;
}

.box {
  background: #ffffff;
  padding: 40px;
  width: 100%;
  border-radius: 14px;
  box-shadow: 0 10px 40px rgba(0, 0, 0, 0.15);
}

.box h2 {
  color: #0077cc;
  margin-bottom: 30px;
  font-size: 28px;
  font-weight: 700;
  letter-spacing: -0.5px;
  text-align: center;
}

.form-group {
  margin-bottom: 16px;
}

.form-group label {
  display: block;
  text-align: left;
  margin-bottom: 8px;
  color: #333;
  font-weight: 500;
  font-size: 14px;
}

input {
  width: 100%;
  padding: 12px 14px;
  border: 1.5px solid #ddd;
  border-radius: 8px;
  font-size: 14px;
  font-family: 'Poppins', sans-serif;
  transition: all 0.3s ease;
}

input:focus {
  outline: none;
  border-color: #0077cc;
  box-shadow: 0 0 8px rgba(0, 119, 204, 0.3);
  background: #f9fafb;
}

button {
  width: 100%;
  padding: 12px;
  margin-top: 20px;
  background: #0077cc;
  color: white;
  border: none;
  border-radius: 8px;
  font-size: 15px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s ease;
}

button:hover {
  background: #005fa3;
  transform: translateY(-2px);
  box-shadow: 0 5px 15px rgba(0, 119, 204, 0.3);
}

button:active {
  transform: translateY(0);
}

.link {
  text-align: center;
  margin-top: 20px;
  font-size: 14px;
  color: #666;
}

.link a {
  text-decoration: none;
  color: #0077cc;
  font-weight: 600;
  transition: all 0.3s ease;
}

.link a:hover {
  text-decoration: underline;
  color: #005fa3;
}

.error {
  color: #d93025;
  text-align: center;
  margin-bottom: 16px;
  background: #ffecec;
  padding: 12px 14px;
  border-radius: 6px;
  border-left: 4px solid #d93025;
  font-weight: 500;
}

.success {
  color: #188038;
  text-align: center;
  margin-bottom: 16px;
  background: #e9fbf1;
  padding: 12px 14px;
  border-radius: 6px;
  border-left: 4px solid #188038;
  font-weight: 500;
}

@media (max-width: 768px) {
  body {
    padding: 15px;
  }

  .box {
    padding: 30px 20px;
  }

  .box h2 {
    font-size: 24px;
    margin-bottom: 25px;
  }

  input {
    padding: 11px 12px;
    font-size: 13px;
  }

  button {
    padding: 11px;
    font-size: 14px;
    margin-top: 18px;
  }

  .form-group {
    margin-bottom: 14px;
  }
}

@media (max-width: 480px) {
  body {
    padding: 10px;
  }

  .box {
    padding: 25px 18px;
    border-radius: 10px;
  }

  .box h2 {
    font-size: 22px;
    margin-bottom: 20px;
  }

  input {
    padding: 10px 11px;
    font-size: 12px;
  }

  button {
    padding: 10px;
    font-size: 13px;
    margin-top: 16px;
  }

  .form-group {
    margin-bottom: 12px;
  }

  .link {
    font-size: 12px;
  }
}
</style>
</head>
<body>
<div class="container">
  <div class="box">
    <h2>BottleBank Login</h2>

    <?php if($error): ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <?php if($success): ?><div class="success"><?= htmlspecialchars($success) ?></div><?php endif; ?>

    <form method="post">
      <div class="form-group">
        <label for="username">Username</label>
        <input type="text" id="username" name="username" placeholder="Enter your username" required>
      </div>
      <div class="form-group">
        <label for="password">Password</label>
        <input type="password" id="password" name="password" placeholder="Enter your password" required>
      </div>
      <button type="submit">Login</button>
    </form>

    <div class="link">
      Don't have an account? <a href="register.php">Sign up here</a>
    </div>
  </div>
</div>
</body>
</html>
