<?php
session_start();
require 'includes/db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = intval($_SESSION['user_id']);
// check whether user is forced to change
$stmt = $conn->prepare("SELECT username, force_change FROM user WHERE user_id = ?");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    session_destroy();
    header('Location: login.php');
    exit();
}

// If not forced and no session flag, allow normal flow to dashboard
if (empty($user['force_change']) && empty($_SESSION['force_change'])) {
    // already ok
    header('Location: index.php');
    exit();
}

$error = '';
$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $p1 = $_POST['password'] ?? '';
    $p2 = $_POST['password_confirm'] ?? '';
    if (strlen($p1) < 6) {
        $error = 'Password must be at least 6 characters.';
    } elseif ($p1 !== $p2) {
        $error = 'Passwords do not match.';
    } else {
        $hash = password_hash($p1, PASSWORD_DEFAULT);
        $u = $conn->prepare("UPDATE user SET password = ?, force_change = 0 WHERE user_id = ?");
        $u->bind_param('si', $hash, $user_id);
        if ($u->execute()) {
            // log to stock_log for compatibility
            $action = 'Password Changed';
            $log = $conn->prepare("INSERT INTO stock_log (user_id, action_type, customer_name) VALUES (?, ?, ?)");
            $log->bind_param('iss', $user_id, $action, $user['username']);
            $log->execute(); $log->close();
            
            // log to password_log table
            $pw_log = $conn->prepare("INSERT INTO password_log (user_id, change_type) VALUES (?, 'Self')");
            $pw_log->bind_param('i', $user_id);
            $pw_log->execute(); $pw_log->close();

            // clear session flag
            unset($_SESSION['force_change']);
            $msg = 'Password updated. Redirecting...';
            header('Refresh:2; url=index.php');
        } else {
            $error = 'Update failed: ' . $u->error;
        }
        $u->close();
    }
}
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Change Password • BottleBank</title>
<link rel="stylesheet" href="asset/style.css">
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
    font-family: 'Poppins', 'Segoe UI', sans-serif;
    background: linear-gradient(135deg, #2d6a6a 0%, #26a69a 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: 100vh;
    padding: 20px;
  }

  .container {
    width: 100%;
    max-width: 400px;
    background: white;
    padding: 40px;
    border-radius: 12px;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
  }

  h2 {
    color: #2d6a6a;
    text-align: center;
    margin-bottom: 10px;
    font-size: 28px;
    font-weight: 700;
  }

  .subtitle {
    color: #666;
    text-align: center;
    font-size: 13px;
    margin-bottom: 25px;
    font-weight: 500;
  }

  .error {
    padding: 12px 15px;
    background: #ffecec;
    border-left: 4px solid #ef5350;
    border-radius: 6px;
    margin-bottom: 15px;
    color: #c62828;
    font-weight: 500;
    animation: slideDown 0.3s ease;
  }

  .msg {
    padding: 12px 15px;
    background: #e9fbf1;
    border-left: 4px solid #26a69a;
    border-radius: 6px;
    margin-bottom: 15px;
    color: #155724;
    font-weight: 500;
    animation: slideDown 0.3s ease;
  }

  form {
    display: flex;
    flex-direction: column;
    gap: 15px;
  }

  label {
    font-weight: 600;
    color: #2d6a6a;
    font-size: 13px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
  }

  input[type="password"] {
    width: 100%;
    padding: 12px;
    border: 2px solid #e0e0e0;
    border-radius: 6px;
    font-family: 'Poppins', sans-serif;
    font-size: 14px;
    transition: all 0.3s ease;
  }

  input[type="password"]:focus {
    outline: none;
    border-color: #26a69a;
    box-shadow: 0 0 8px rgba(38, 166, 154, 0.2);
  }

  button {
    padding: 12px;
    background: #26a69a;
    color: white;
    border: none;
    border-radius: 6px;
    font-weight: 600;
    font-size: 14px;
    cursor: pointer;
    transition: all 0.3s ease;
    margin-top: 10px;
  }

  button:hover {
    background: #2e7d7d;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(38, 166, 154, 0.3);
  }

  button:active {
    transform: translateY(0);
  }

  .info-text {
    color: #666;
    font-size: 12px;
    text-align: center;
    margin-top: 20px;
  }

  .info-text strong {
    color: #2d6a6a;
  }

  @keyframes slideDown {
    from {
      opacity: 0;
      transform: translateY(-10px);
    }
    to {
      opacity: 1;
      transform: translateY(0);
    }
  }

  /* Responsive Design */
  @media (max-width: 768px) {
    body {
      padding: 15px;
    }

    .container {
      padding: 30px 20px;
      max-width: 100%;
    }

    h2 {
      font-size: 24px;
      margin-bottom: 8px;
    }

    .subtitle {
      font-size: 12px;
      margin-bottom: 20px;
    }

    input[type="password"] {
      padding: 10px;
      font-size: 13px;
    }

    button {
      padding: 10px;
      font-size: 13px;
    }
  }

  @media (max-width: 480px) {
    body {
      padding: 10px;
    }

    .container {
      padding: 25px 15px;
      border-radius: 10px;
    }

    h2 {
      font-size: 20px;
      margin-bottom: 6px;
    }

    .subtitle {
      font-size: 11px;
      margin-bottom: 18px;
    }

    form {
      gap: 12px;
    }

    label {
      font-size: 12px;
    }

    input[type="password"] {
      padding: 9px;
      font-size: 12px;
    }

    button {
      padding: 9px;
      font-size: 12px;
      margin-top: 8px;
    }

    .info-text {
      font-size: 11px;
      margin-top: 15px;
    }
  }
</style>
</head>
<body>
<div class="container">
  <h2>Change Password</h2>
  <p class="subtitle">Enter your new password to continue</p>
  
  <?php if($error): ?><div class="error"><?=htmlspecialchars($error)?></div><?php endif; ?>
  <?php if($msg): ?><div class="msg"><?=htmlspecialchars($msg)?></div><?php endif; ?>
  
  <form method="post">
    <div>
      <label>New Password</label>
      <input type="password" name="password" placeholder="At least 6 characters" required>
    </div>
    
    <div>
      <label>Confirm Password</label>
      <input type="password" name="password_confirm" placeholder="Re-enter your password" required>
    </div>
    
    <button type="submit">Update Password</button>
  </form>

  <div class="info-text">
    <strong>Password Requirements:</strong><br>
    Minimum 6 characters
  </div>
</div>
</body>
</html>
