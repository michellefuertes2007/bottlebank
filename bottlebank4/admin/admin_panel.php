<?php
session_start();
require '../db_connect.php';

// Only admin can access
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

// Check if the logged-in user is admin
$stmt = $conn->prepare("SELECT username FROM user WHERE user_id = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if ($user['username'] !== 'admin') {
    die("Access denied. Admins only.");
}

// =====================
// Handle User Updates
// =====================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_user'])) {
    $edit_user_id = intval($_POST['edit_user_id']);
    $new_username = trim($_POST['username']);
    $new_email    = trim($_POST['email']);
    $new_password = $_POST['password'];

    if ($new_password) {
        $hashed = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE user SET username=?, email=?, password=? WHERE user_id=?");
        $stmt->bind_param("sssi", $new_username, $new_email, $hashed, $edit_user_id);
    } else {
        $stmt = $conn->prepare("UPDATE user SET username=?, email=? WHERE user_id=?");
        $stmt->bind_param("ssi", $new_username, $new_email, $edit_user_id);
    }

    $stmt->execute();
    $stmt->close();
    $msg = "User updated successfully!";
}

// =====================
// Fetch all users (exclude admin)
// =====================
$users = $conn->query("SELECT * FROM user WHERE username != 'admin' ORDER BY created_at DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Panel | BottleBank</title>
<style>
body{font-family:Poppins,sans-serif;background:#f3f6f9;margin:0;padding:0;}
.container{max-width:1200px;margin:20px auto;padding:20px;background:#fff;border-radius:10px;box-shadow:0 5px 15px rgba(0,0,0,0.1);}
h1{color:#0077cc;text-align:center;margin-bottom:20px;}
table{width:100%;border-collapse:collapse;margin-bottom:20px;}
th,td{padding:10px;border:1px solid #ddd;text-align:left;font-size:14px;}
th{background:#0077cc;color:#fff;}
a.button{display:inline-block;padding:6px 12px;background:#0077cc;color:#fff;text-decoration:none;border-radius:6px;font-size:12px;}
a.button:hover{background:#005fa3;}
form input{padding:6px;width:100%;margin:4px 0;border-radius:6px;border:1px solid #ccc;}
form button{padding:8px 12px;background:#28a745;color:#fff;border:none;border-radius:6px;cursor:pointer;}
form button:hover{background:#218838;}
.msg{color:green;margin-bottom:10px;text-align:center;}
</style>
</head>
<body>
<div class="container">
<h1>Admin Panel • BottleBank</h1>

<?php if(isset($msg)) echo "<div class='msg'>$msg</div>"; ?>

<h2>Registered Users</h2>
<table>
<tr>
<th>ID</th>
<th>Username</th>
<th>Email</th>
<th>Joined</th>
<th>Actions</th>
</tr>
<?php while($row = $users->fetch_assoc()): ?>
<tr>
<td><?= $row['user_id'] ?></td>
<td><?= htmlspecialchars($row['username']) ?></td>
<td><?= htmlspecialchars($row['email']) ?></td>
<td><?= date("M d, Y", strtotime($row['created_at'])) ?></td>
<td>
<a href="#edit<?= $row['user_id'] ?>" class="button">Edit</a>
<a href="#history<?= $row['user_id'] ?>" class="button" style="background:#6c757d;">History</a>
</td>
</tr>

<tr id="edit<?= $row['user_id'] ?>">
<td colspan="5">
<form method="post">
<input type="hidden" name="edit_user_id" value="<?= $row['user_id'] ?>">
<input type="text" name="username" value="<?= htmlspecialchars($row['username']) ?>" placeholder="Username" required>
<input type="email" name="email" value="<?= htmlspecialchars($row['email']) ?>" placeholder="Email" required>
<input type="password" name="password" placeholder="New Password (leave blank to keep)">
<button type="submit" name="update_user">Update User</button>
</form>
</td>
</tr>

<tr id="history<?= $row['user_id'] ?>">
<td colspan="5">
<?php
$uid = $row['user_id'];
// Count deposits
$dep = $conn->query("SELECT COUNT(*) AS cnt, SUM(quantity) AS total FROM deposit WHERE user_id=$uid")->fetch_assoc();
$ret = $conn->query("SELECT COUNT(*) AS cnt, SUM(quantity) AS total FROM returns WHERE user_id=$uid")->fetch_assoc();
$ref = $conn->query("SELECT COUNT(*) AS cnt, SUM(amount) AS total FROM refund WHERE user_id=$uid")->fetch_assoc();
$logs = $conn->query("SELECT * FROM stock_log WHERE user_id=$uid ORDER BY date_logged DESC");
?>
<h3><?= htmlspecialchars($row['username']) ?> History</h3>
<p>Deposits: <?= $dep['cnt'] ?> (Total: <?= $dep['total'] ?? 0 ?> bottles)</p>
<p>Returns: <?= $ret['cnt'] ?> (Total: <?= $ret['total'] ?? 0 ?> bottles)</p>
<p>Refunds: <?= $ref['cnt'] ?> (Total: ₱<?= $ref['total'] ?? 0 ?>)</p>

<h4>Stock Logs</h4>
<table>
<tr>
<th>ID</th>
<th>Action</th>
<th>Customer</th>
<th>Bottle Type</th>
<th>Quantity</th>
<th>Amount</th>
<th>Date</th>
</tr>
<?php while($l = $logs->fetch_assoc()): ?>
<tr>
<td><?= $l['log_id'] ?></td>
<td><?= htmlspecialchars($l['action_type']) ?></td>
<td><?= htmlspecialchars($l['customer_name']) ?></td>
<td><?= htmlspecialchars($l['bottle_type']) ?></td>
<td><?= $l['quantity'] ?></td>
<td><?= $l['amount'] ?></td>
<td><?= date("M d, Y — h:i A", strtotime($l['date_logged'])) ?></td>
</tr>
<?php endwhile; ?>
</table>
</td>
</tr>

<?php endwhile; ?>
</table>
<p style="text-align:center"><a href="../index.php" class="button" style="background:#dc3545;">Back to Dashboard</a></p>
</div>
</body>
</html>
