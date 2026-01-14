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
            // log
            $action = 'Password Changed';
            $log = $conn->prepare("INSERT INTO stock_log (user_id, action_type, customer_name) VALUES (?, ?, ?)");
            $log->bind_param('iss', $user_id, $action, $user['username']);
            $log->execute(); $log->close();

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
<title>Change Password</title>
<link rel="stylesheet" href="asset/style.css">
</head>
<body>
<div class="app" style="max-width:600px;margin:40px auto">
  <h2>Change Password</h2>
  <?php if($error): ?><div class="error"><?=htmlspecialchars($error)?></div><?php endif; ?>
  <?php if($msg): ?><div class="msg"><?=htmlspecialchars($msg)?></div><?php endif; ?>
  <form method="post">
    <label>New password</label>
    <input type="password" name="password" required>
    <label>Confirm password</label>
    <input type="password" name="password_confirm" required>
    <div style="margin-top:10px"><button type="submit">Save New Password</button></div>
  </form>
</div>
</body>
</html>
