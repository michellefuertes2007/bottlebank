<?php
include 'includes/db_connect.php';
session_start();
$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);

    // Check duplicates
    $check = $conn->prepare("SELECT * FROM user WHERE username = ? OR email = ?");
    $check->bind_param("ss", $username, $email);
    $check->execute();
    $result = $check->get_result();

    if ($result->num_rows > 0) {
        $error = "Username or email already taken.";
    } else {
        $stmt = $conn->prepare("INSERT INTO user (username, email, password) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $username, $email, $password);
        if ($stmt->execute()) {
            header("Location: login.php?registered=1");
            exit();
        } else {
            $error = "Registration failed. Try again.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Register | BottleBank</title>
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
    <h2>Create Account</h2>
    <?php if($error): ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <form method="post">
      <div class="form-group">
        <label for="username">Username</label>
        <input type="text" id="username" name="username" placeholder="Enter a username" required>
      </div>
      <div class="form-group">
        <label for="email">Email</label>
        <input type="email" id="email" name="email" placeholder="Enter your email" required>
      </div>
      <div class="form-group">
        <label for="password">Password</label>
        <input type="password" id="password" name="password" placeholder="Enter a password" required>
      </div>
      <button type="submit">Register</button>
    </form>
    <div class="link">
      Already have an account? <a href="login.php">Login here</a>
    </div>
  </div>
</div>
</body>
</html>
