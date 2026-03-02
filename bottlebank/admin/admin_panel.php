<?php
session_start();
require '../includes/db_connect.php';
// lightweight migration helper for early pages
function ensureColumn($conn, $table, $column, $definition) {
    $res = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
    if(!$res || $res->num_rows === 0) {
        $conn->query("ALTER TABLE `$table` ADD COLUMN $definition");
    }
}
// stock_log may not yet have size field on older installations
ensureColumn($conn, 'stock_log', 'bottle_size', "bottle_size VARCHAR(10) DEFAULT 'small'");

// Only admin can access
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

// ensure email column exists (migration helper)
ensureColumn($conn, 'user', 'email', "email VARCHAR(100) NOT NULL DEFAULT '' UNIQUE");

// Check if the logged-in user is admin
$stmt = $conn->prepare("SELECT username, role FROM user WHERE user_id = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if ($user['role'] !== 'admin') {
    // log access denied attempts
    $logFile = __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'auth.log';
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'cli';
    $ts = date('Y-m-d H:i:s');
    $uid = intval($_SESSION['user_id'] ?? 0);
    file_put_contents($logFile, "[$ts] [$ip] ACCESS DENIED: session_user_id={$uid}\n", FILE_APPEND | LOCK_EX);
    die("Access denied. Admins only.");
}

// =====================
// Handle User Updates and Creation
// =====================
// Ensure `force_change` column exists
$colCheck = $conn->query("SHOW COLUMNS FROM user LIKE 'force_change'");
if ($colCheck->num_rows === 0) {
    $conn->query("ALTER TABLE user ADD COLUMN force_change TINYINT(1) NOT NULL DEFAULT 0");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_user'])) {
    // create new regular user
    $new_username = trim($_POST['username'] ?? '');
    $new_email    = trim($_POST['email'] ?? '');
    $new_password = $_POST['password'] ?? '';
    $confirm_pass= $_POST['confirm_password'] ?? '';
    if (!$new_username || !$new_password || !$new_email) {
        $msg = 'Username, email and password are required.';
    } elseif ($new_password !== $confirm_pass) {
        $msg = 'Passwords do not match.';
    } else {
        // make sure username and email are unique
        $check = $conn->prepare("SELECT user_id FROM user WHERE username = ? OR email = ?");
        $check->bind_param("ss", $new_username, $new_email);
        $check->execute();
        $check->store_result();
        if ($check->num_rows > 0) {
            $msg = 'Username or email already in use.';
        } else {
            $hash = password_hash($new_password, PASSWORD_DEFAULT);
            // include email field when creating users; table schema requires it
            $stmt = $conn->prepare("INSERT INTO user (username, email, password, role) VALUES (?, ?, ?, 'user')");
            $stmt->bind_param("sss", $new_username, $new_email, $hash);
            if ($stmt->execute()) {
                $msg = "New user '$new_username' added.";
            } else {
                $msg = 'Failed to create user: ' . $stmt->error;
            }
            $stmt->close();
        }
        $check->close();
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_user'])) {
    $edit_user_id = intval($_POST['edit_user_id']);
    $new_username = trim($_POST['username']);
    $new_email    = trim($_POST['email']);
    $new_role     = trim($_POST['role']);
    $new_password = $_POST['password'];

    if ($new_password) {
        $hashed = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE user SET username=?, email=?, role=?, password=? WHERE user_id=?");
        $stmt->bind_param("ssssi", $new_username, $new_email, $new_role, $hashed, $edit_user_id);
    } else {
        $stmt = $conn->prepare("UPDATE user SET username=?, email=?, role=? WHERE user_id=?");
        $stmt->bind_param("sssi", $new_username, $new_email, $new_role, $edit_user_id);
    }

    $stmt->execute();
    $stmt->close();
    $msg = "User updated successfully!";
}

    // Handle password reset by admin
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_password'])) {
        $target_id = intval($_POST['reset_user_id']);
        // fetch username
        $s = $conn->prepare("SELECT username FROM user WHERE user_id = ?");
        $s->bind_param('i', $target_id);
        $s->execute();
        $res = $s->get_result()->fetch_assoc();
        $s->close();
        if (!$res) {
            $msg = 'User not found.';
        } else {
            // generate temporary password
            $temp = bin2hex(random_bytes(4)); // 8 hex chars
            $hash = password_hash($temp, PASSWORD_DEFAULT);
            $u = $conn->prepare("UPDATE user SET password = ?, force_change = 1 WHERE user_id = ?");
            $u->bind_param('si', $hash, $target_id);
            if ($u->execute()) {
                // log the reset to stock_log for compatibility
                $admin_id = intval($_SESSION['user_id']);
                $action = 'Password Reset';
                $target_username = $res['username'];
                $log = $conn->prepare("INSERT INTO stock_log (user_id, action_type, customer_name) VALUES (?, ?, ?)");
                $log->bind_param('iss', $admin_id, $action, $target_username);
                $log->execute(); $log->close();
                
                // log the reset to password_log table
                $pw_log = $conn->prepare("INSERT INTO password_log (user_id, changed_by_id, change_type) VALUES (?, ?, 'Admin Reset')");
                $pw_log->bind_param('ii', $target_id, $admin_id);
                $pw_log->execute(); $pw_log->close();

                $msg = "Temporary password for {$target_username}: <strong>" . htmlspecialchars($temp) . "</strong>. User will be forced to change on next login.";
            } else {
                $msg = 'Failed to reset password: ' . $u->error;
            }
            $u->close();
        }
    }

// =====================
// Fetch all users with pagination (5 per page)
// =====================
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
 $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
 $per_page = isset($_GET['per_page']) && in_array((int)$_GET['per_page'], [5,10,20]) ? (int)$_GET['per_page'] : 5;
 $offset = ($page - 1) * $per_page;

$query = "SELECT * FROM user ORDER BY role DESC, created_at DESC LIMIT $offset, $per_page";
$count_query = "SELECT COUNT(*) as total FROM user";

if ($search) {
    // Search by username, email, or user_id
    $search = $conn->real_escape_string($search);
    $query = "SELECT * FROM user WHERE 
              username LIKE '%$search%' OR 
              email LIKE '%$search%' OR 
              user_id LIKE '%$search%' 
              ORDER BY role DESC, created_at DESC LIMIT $offset, $per_page";
    $count_query = "SELECT COUNT(*) as total FROM user WHERE 
                    username LIKE '%$search%' OR 
                    email LIKE '%$search%' OR 
                    user_id LIKE '%$search%'";
}

$users = $conn->query($query);
$count_result = $conn->query($count_query);
$total_users = $count_result->fetch_assoc()['total'];
$total_pages = ceil($total_users / $per_page);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Panel | BottleBank</title>
<link rel="stylesheet" href="../asset/style.css">
<style>
* {
  margin: 0;
  padding: 0;
  box-sizing: border-box;
}

html, body {
  width: 100%;
}

body {
  font-family: 'Poppins', 'Segoe UI', sans-serif;
  background: #f0f7f7;
  color: #333;
  display: flex;
}

/* sidebar styles are provided by asset/style.css to ensure
   responsive show/hide behaviour; avoid redeclaring them here */

.sidebar h2 {
  color: white;
  border: none;
  padding: 0;
  margin: 0 0 30px 0;
  font-size: 20px;
  text-align: center;
}



.main-content {
  flex: 1;
  overflow-y: auto;
  padding: 20px;
  /* margin-left omitted so mobile media query in asset/style.css can take effect */
}

.container {
  width: 100%;
  max-width: 1100px;
  margin: 0 auto;
  padding: 20px;
  background: transparent;
}

/* ensure margin-left resets on narrow screens */
@media (max-width: 768px) {
  .container, .app { margin-left: 0 !important; }
  /* sidebar positioning handled by global stylesheet */
}

h1 {
  color: #2d6a6a;
  text-align: center;
  margin-bottom: 30px;
  font-size: 28px;
  font-weight: 700;
}

h2 {
  color: #2d6a6a;
  margin-top: 30px;
  margin-bottom: 20px;
  font-size: 22px;
  border-bottom: 2px solid #26a69a;
  padding-bottom: 10px;
}

h3 {
  color: #2d6a6a;
  margin-top: 20px;
  margin-bottom: 15px;
  font-size: 18px;
}

h4 {
  color: #2d6a6a;
  margin-top: 15px;
  margin-bottom: 10px;
  font-size: 16px;
}

.msg {
  color: #188038;
  background: #e9fbf1;
  padding: 12px 16px;
  margin-bottom: 20px;
  border-radius: 6px;
  border-left: 4px solid #26a69a;
  font-weight: 500;
  text-align: center;
}

table {
  width: 100%;
  border-collapse: collapse;
  margin-bottom: 20px;
  background: white;
  border-radius: 8px;
  overflow: hidden;
  box-shadow: 0 2px 8px rgba(0,0,0,0.05);
}

th, td {
  padding: 12px 14px;
  border-bottom: 1px solid #eee;
  text-align: left;
  font-size: 14px;
}

th {
  background: linear-gradient(135deg, #2d6a6a 0%, #1e4a4a 100%);
  color: #fff;
  font-weight: 600;
  position: sticky;
  top: 0;
}

tr:last-child td {
  border-bottom: none;
}

tr:hover {
  background: #f9fafb;
}

.button {
  display: inline-block;
  padding: 8px 14px;
  min-width: 100px;
  text-align: center;
  border-radius: 6px;
  font-size: 12px;
  font-weight: 600;
  cursor: pointer;
  border: none;
  transition: all 0.3s ease;
  margin-right: 6px;
  margin-bottom: 6px;
}

.button:hover {
  background: #2e7d7d;
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(38, 166, 154, 0.25);
}

.button.secondary {
  background: #80cbc4;
  color: #004d40;
}

.button.secondary:hover {
  background: #4db6ac;
}

.button.danger {
  background: #ef5350;
}

.button.danger:hover {
  background: #e53935;
}

.button.info {
  background: #42a5f5;
}

.button.info:hover {
  background: #1e88e5;
}

form input,
form select,
form textarea {
  padding: 10px 12px;
  width: 100%;
  margin: 8px 0;
  border: 1.5px solid #ddd;
  border-radius: 6px;
  font-size: 14px;
  font-family: 'Poppins', sans-serif;
  transition: all 0.3s ease;
}

form input:focus,
form select:focus,
form textarea:focus {
  outline: none;
  border-color: #26a69a;
  box-shadow: 0 0 6px rgba(38, 166, 154, 0.15);
  background: #fafbfc;
}

form button {
  padding: 10px 16px;
  background: #26a69a;
  color: #fff;
  border: none;
  border-radius: 6px;
  cursor: pointer;
  font-weight: 600;
  transition: all 0.3s ease;
  font-family: 'Poppins', sans-serif;
  font-size: 14px;
  margin-top: 8px;
}

form button:hover {
  background: #2e7d7d;
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(38, 166, 154, 0.25);
}

.edit-form,
.history-section {
  background: #f9fafb;
  padding: 16px;
  border-radius: 6px;
  margin: 10px 0;
}

.stats {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 15px;
  margin-bottom: 20px;
}

.stat-card {
  background: white;
  padding: 16px;
  border-radius: 8px;
  border-left: 4px solid #26a69a;
  box-shadow: 0 2px 8px rgba(0,0,0,0.05);
}

.stat-card h4 {
  color: #2d6a6a;
  margin: 0 0 8px 0;
  font-size: 12px;
  text-transform: uppercase;
  font-weight: 600;
}

.stat-card .value {
  color: #26a69a;
  font-size: 24px;
  font-weight: 700;
}

.action-buttons {
  display: flex;
  gap: 6px;
  flex-wrap: wrap;
}

.back-button {
  display: block;
  text-align: center;
  margin-top: 30px;
}

/* most responsive behaviour is handled by asset/style.css */
</style>
<script>
// early definition so inline onclicks work
function toggleSidebar(){
  const sidebar = document.querySelector('.sidebar');
  const overlay = document.querySelector('.sidebar-overlay');
  if(sidebar) sidebar.classList.toggle('active');
  if(overlay) overlay.classList.toggle('active');
}
</script>
</head>
<body>
<div class="sidebar-overlay" onclick="toggleSidebar()"></div>

<div class="sidebar">
  <div class="brand">
    <h1>BB</h1>
  </div>
  <nav class="sidebar-nav">
    <a href="../index.php">Dashboard</a>
    <a href="../deposit.php">Deposit</a>
    <a href="../returns.php">Returns</a>
    <a href="../stock_log.php">Stock Log</a>
    <?php if(isset($user) && ($user['role'] ?? '') === 'admin'): ?>
      <a href="admin_panel.php#users-section">Users</a>
    <?php endif; ?>
    <a href="../logout.php" class="logout">Logout</a>
  </nav>
</div>

<div class="app">
<div class="container">
  <!-- Topbar (shared with other pages) -->
  <div class="topbar">
    <div class="brand">
      <button class="toggle-sidebar" onclick="toggleSidebar()">Menu</button>
      <h1>Admin Panel</h1>
    </div>
    <div class="menu-wrap">
      <span>Signed in as <strong><?= htmlspecialchars($user['username'] ?? 'Admin') ?></strong></span>
    </div>
  </div>

<?php if(isset($msg)) echo "<div class='msg'>{$msg}</div>"; ?>

<div id="users-section" class="section" style="display: block;">
  <h1>Registered Users</h1>

<div style="display: flex; gap: 10px; margin-bottom: 20px; flex-wrap: wrap; align-items: center;">
  <button id="showAddUser" class="button" style="padding: 10px 16px;">+ Add User</button>
  <button id="toggleSearch" class="button" style="padding: 10px 16px;">🔍 Search</button>
</div>

<div id="searchBox" style="display: none; margin-bottom: 20px; padding: 15px; background: #f9fafb; border-radius: 6px;">
  <input 
      type="text" 
      id="userSearch" 
      placeholder="Search by username, email, or ID..." 
      style="padding: 10px; border: 2px solid #26a69a; border-radius: 6px; font-family: 'Poppins', sans-serif; font-size: 14px; width: 100%; max-width: 400px;">
</div>

<div id="addUserForm" style="display:none; margin-bottom:20px;">
  <form method="post" style="max-width:400px;">
    <h4>Add New User</h4>
    <div class="form-row">
      <div class="col">
        <label for="add_username">Username</label>
        <input id="add_username" type="text" name="username" required autocomplete="username">
      </div>
    </div>
    <div class="form-row">
      <div class="col">
        <label for="add_email">Email</label>
        <input id="add_email" type="email" name="email" required autocomplete="email">
      </div>
    </div>
    <div class="form-row">
      <div class="col">
        <label for="add_password">Password</label>
        <input id="add_password" type="password" name="password" required autocomplete="new-password">
      </div>
    </div>
    <div class="form-row">
      <div class="col">
        <label for="add_confirm_password">Confirm Password</label>
        <input id="add_confirm_password" type="password" name="confirm_password" required autocomplete="new-password">
      </div>
    </div>
    <button type="submit" name="create_user" class="button" style="padding: 10px 16px;">Add</button>
  </form>
</div>

<!-- Results counter -->
<div id="resultCounter" style="margin-bottom: 10px; color: #666; font-size: 14px;">
    Showing <span id="visibleCount"><?php echo $users->num_rows; ?></span> user(s) (Page <?= $page ?> of <?= max(1, $total_pages) ?>)
</div>

<!-- Pagination Controls -->
<?php if ($total_pages > 1): ?>
<div style="margin-bottom: 20px; display: flex; gap: 8px; flex-wrap: wrap;">
    <?php if ($page > 1): ?>
        <a href="?page=1<?= $search ? '&search=' . urlencode($search) : '' ?>" class="button secondary">« First</a>
        <a href="?page=<?= $page - 1 ?><?= $search ? '&search=' . urlencode($search) : '' ?>" class="button secondary">‹ Previous</a>
    <?php endif; ?>
    
    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
        <?php if ($i == $page): ?>
            <button class="button primary" style="font-weight: bold;"><?= $i ?></button>
        <?php else: ?>
            <a href="?page=<?= $i ?><?= $search ? '&search=' . urlencode($search) : '' ?>" class="button secondary"><?= $i ?></a>
        <?php endif; ?>
    <?php endfor; ?>
    
    <?php if ($page < $total_pages): ?>
        <a href="?page=<?= $page + 1 ?><?= $search ? '&search=' . urlencode($search) : '' ?>" class="button secondary">Next ›</a>
        <a href="?page=<?= $total_pages ?><?= $search ? '&search=' . urlencode($search) : '' ?>" class="button secondary">Last »</a>
    <?php endif; ?>
</div>
<?php endif; ?>

<div style="overflow-x: auto;">
<table id="usersTable">
<tr>
<th>ID</th>
<th>Username</th>
<th>Email</th>
<th>Role</th>
<th>Joined</th>
<th>Actions</th>
</tr>
<?php while($row = $users->fetch_assoc()): ?>
<tr data-user-id="<?= $row['user_id'] ?>" data-username="<?= htmlspecialchars($row['username']) ?>" data-email="<?= htmlspecialchars($row['email']) ?>">
<td><?= $row['user_id'] ?></td>
<td><?= htmlspecialchars($row['username']) ?></td>
<td><?= htmlspecialchars($row['email']) ?></td>
<td><strong><?= ucfirst($row['role']) ?></strong></td>
<td><?= date("M d, Y", strtotime($row['created_at'])) ?></td>
<td>
<div class="action-buttons">
<a href="#edit<?= $row['user_id'] ?>" class="button">Edit</a>
<a href="#history<?= $row['user_id'] ?>" class="button secondary">History</a>
<form method="post" style="display:inline;">
    <input type="hidden" name="reset_user_id" value="<?= $row['user_id'] ?>">
    <button type="submit" name="reset_password" class="button danger">Reset Pass</button>
</form>
</div>
</td>
</tr>

<tr id="edit<?= $row['user_id'] ?>">
<td colspan="6">
<div class="edit-form">
<h4>Edit User: <?= htmlspecialchars($row['username']) ?></h4>
<form method="post">
<input type="hidden" name="edit_user_id" value="<?= $row['user_id'] ?>">
<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
<div>
<label for="username_<?= $row['user_id'] ?>" style="font-weight: 600; display: block; margin-bottom: 6px; color: #2d6a6a;">Username</label>
<input id="username_<?= $row['user_id'] ?>" type="text" name="username" value="<?= htmlspecialchars($row['username']) ?>" placeholder="Username" required autocomplete="username">
</div>
<div>
<label for="email_<?= $row['user_id'] ?>" style="font-weight: 600; display: block; margin-bottom: 6px; color: #2d6a6a;">Email</label>
<input id="email_<?= $row['user_id'] ?>" type="email" name="email" value="<?= htmlspecialchars($row['email']) ?>" placeholder="Email" required autocomplete="email">
</div>
<div>
<label for="role_<?= $row['user_id'] ?>" style="font-weight: 600; display: block; margin-bottom: 6px; color: #2d6a6a;">Role</label>
<select id="role_<?= $row['user_id'] ?>" name="role">
  <option value="user" <?= $row['role'] === 'user' ? 'selected' : '' ?>>User</option>
  <option value="admin" <?= $row['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
</select>
</div>
<div>
<label for="password_<?= $row['user_id'] ?>" style="font-weight: 600; display: block; margin-bottom: 6px; color: #2d6a6a;">New Password</label>
<input id="password_<?= $row['user_id'] ?>" type="password" name="password" placeholder="Leave blank to keep current password" autocomplete="new-password">
</div>
</div>
<button type="submit" name="update_user">Update User</button>
</form>
</div>
</td>
</tr>

<tr id="history<?= $row['user_id'] ?>">
<td colspan="6">
<div class="history-section">
<?php
$uid = $row['user_id'];
// Count deposits
$dep = $conn->query("SELECT COUNT(*) AS cnt, SUM(quantity) AS total FROM deposit WHERE user_id=$uid")->fetch_assoc();
$ret = $conn->query("SELECT COUNT(*) AS cnt, SUM(quantity) AS total FROM returns WHERE user_id=$uid")->fetch_assoc();
$ref = $conn->query("SELECT COUNT(*) AS cnt, SUM(amount) AS total FROM refund WHERE user_id=$uid")->fetch_assoc();
$logs = $conn->query("SELECT * FROM stock_log WHERE user_id=$uid ORDER BY date_logged DESC");
?>
<h3><?= htmlspecialchars($row['username']) ?> - Transaction History</h3>

<div class="stats">
<div class="stat-card">
<h4>Deposits</h4>
<p><span class="value"><?= $dep['cnt'] ?></span></p>
<p style="font-size: 12px; color: #666; margin: 6px 0 0 0;">Total: <?= $dep['total'] ?? 0 ?> bottles</p>
</div>
<div class="stat-card">
<h4>Returns</h4>
<p><span class="value"><?= $ret['cnt'] ?></span></p>
<p style="font-size: 12px; color: #666; margin: 6px 0 0 0;">Total: <?= $ret['total'] ?? 0 ?> bottles</p>
</div>
<div class="stat-card">
<h4>Refunds</h4>
<p><span class="value">₱<?= $ref['total'] ?? 0 ?></span></p>
<p style="font-size: 12px; color: #666; margin: 6px 0 0 0;"><?= $ref['cnt'] ?> transactions</p>
</div>
</div>

<h4>Stock Logs</h4>
<div style="overflow-x: auto;">
<table>
<tr>
<th>Log ID</th>
<th>Action</th>
<th>Customer</th>
<th>Size</th>
<th>Bottle Type</th>
<th>Qty</th>
<th>With Case</th>
<th>Case Qty</th>
<th>Details</th>
<th>Amount</th>
<th>Date</th>
</tr>
<?php while($l = $logs->fetch_assoc()): ?>
<tr>
<td><?= $l['log_id'] ?></td>
<td><strong><?= htmlspecialchars($l['action_type']) ?></strong></td>
<td><?= !empty($l['customer_name']) ? htmlspecialchars($l['customer_name']) : '<span style="color: #999;">N/A</span>' ?></td>
<td><?= !empty($l['bottle_size']) ? htmlspecialchars($l['bottle_size']) : '<span style="color:#999;">N/A</span>' ?></td>
<td><?= !empty($l['bottle_type']) ? htmlspecialchars($l['bottle_type']) : '<span style="color: #999;">N/A</span>' ?></td>
<td><?= !empty($l['quantity']) && $l['quantity'] > 0 ? $l['quantity'] : '<span style="color: #999;">N/A</span>' ?></td>
<td><?= isset($l['with_case']) && $l['with_case'] ? '<strong>Yes</strong>' : '<span style="color: #999;">No</span>' ?></td>
<td><?= isset($l['case_quantity']) && $l['case_quantity'] > 0 ? $l['case_quantity'] : '<span style="color: #999;">0</span>' ?></td>
<td><?= !empty($l['details']) ? htmlspecialchars($l['details']) : '<span style="color:#999;">N/A</span>' ?></td>
<td><?= !empty($l['amount']) && $l['amount'] > 0 ? '₱' . number_format($l['amount'], 2) : '<span style="color: #999;">N/A</span>' ?></td>
<td><?= date("M d, Y - h:i A", strtotime($l['date_logged'])) ?></td>
</tr>
<?php endwhile; ?>
</table>
</div>
</div>
</td>
</tr>

<?php endwhile; ?>
</table>
</div>
</div>
</div>

<!-- Password Change Log Section -->
<div id="password-section" class="section" style="display: none;">
    <h1>Password Change History</h1>
    <p style="color: #666; font-size: 14px;">View all password changes made by users and admin resets</p>
    
    <?php
    // Fetch password change log
    $log_page = isset($_GET['log_page']) ? max(1, intval($_GET['log_page'])) : 1;
    $log_per_page = 10;
    $log_offset = ($log_page - 1) * $log_per_page;
    
    // First, check if password_log table exists
    $table_check = $conn->query("SHOW TABLES LIKE 'password_log'");
    if ($table_check && $table_check->num_rows > 0) {
        // Get total password log records
        $log_count_query = "SELECT COUNT(*) as total FROM password_log";
        $log_count_result = $conn->query($log_count_query);
        $log_total = $log_count_result->fetch_assoc()['total'];
        $log_total_pages = ceil($log_total / $log_per_page);
        
        // Get password log records
        $log_query = "SELECT pl.log_id, u.user_id, u.username, u.email, a.username as admin_username, pl.change_type, pl.changed_at 
                      FROM password_log pl
                      JOIN user u ON pl.user_id = u.user_id
                      LEFT JOIN user a ON pl.changed_by_id = a.user_id
                      ORDER BY pl.changed_at DESC
                      LIMIT $log_offset, $log_per_page";
        $log_result = $conn->query($log_query);
        
        if ($log_result && $log_result->num_rows > 0) {
    ?>
        <table class="password-log-table">
            <thead>
                <tr>
                    <th>User</th>
                    <th>Email</th>
                    <th>Change Type</th>
                    <th>Changed By</th>
                    <th>Date & Time</th>
                </tr>
            </thead>
            <tbody>
                <?php while($plog = $log_result->fetch_assoc()): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($plog['username']) ?></strong> (ID: <?= $plog['user_id'] ?>)</td>
                    <td><?= htmlspecialchars($plog['email']) ?></td>
                    <td>
                        <span class="change-type <?= strtolower($plog['change_type'] ?? 'self') ?>">
                            <?= htmlspecialchars($plog['change_type'] ?? 'Self') ?>
                        </span>
                    </td>
                    <td>
                        <?php if($plog['admin_username']): ?>
                            <strong><?= htmlspecialchars($plog['admin_username']) ?></strong> (Admin)
                        <?php else: ?>
                            <span style="color: #999;">User Self</span>
                        <?php endif; ?>
                    </td>
                    <td><?= date("M d, Y - h:i A", strtotime($plog['changed_at'])) ?></td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
        
        <!-- Pagination for Password Log -->
        <?php if($log_total_pages > 1): ?>
        <div style="margin-top: 20px; display: flex; gap: 8px; flex-wrap: wrap;">
            <?php if($log_page > 1): ?>
                <a href="?log_page=1" class="button secondary">« First</a>
                <a href="?log_page=<?= $log_page - 1 ?>" class="button secondary">‹ Previous</a>
            <?php endif; ?>
            
            <span style="display: flex; align-items: center; padding: 10px; color: #666; font-size: 14px;">
                Page <?= $log_page ?> of <?= $log_total_pages ?> (<?= $log_total ?> total changes)
            </span>
            
            <?php if($log_page < $log_total_pages): ?>
                <a href="?log_page=<?= $log_page + 1 ?>" class="button secondary">Next ›</a>
                <a href="?log_page=<?= $log_total_pages ?>" class="button secondary">Last »</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        <?php
        } else {
            echo "<p style='color: #888; text-align: center; padding: 20px;'>No password changes recorded yet.</p>";
        }
    } else {
        echo "<p style='color: #888; text-align: center; padding: 20px;'>Password log table not found. Please check your database schema.</p>";
    }
    ?>
</div>
</div>

</div>
</div>

<script>
// Handle section switching
function showSection(sectionId) {
    document.querySelectorAll('.section').forEach(s => s.style.display = 'none');
    const dest = document.getElementById(sectionId);
    if (dest) dest.style.display = 'block';
    window.history.replaceState({}, '', '#' + sectionId);
    // highlight corresponding sidebar link (if any)
    document.querySelectorAll('.sidebar-nav a').forEach(a => {
        a.classList.toggle('active', a.getAttribute('href').includes(sectionId));
    });
}

// Toggle search box visibility
const toggleSearchBtn = document.getElementById('toggleSearch');
const searchBox = document.getElementById('searchBox');
if(toggleSearchBtn && searchBox) {
    toggleSearchBtn.addEventListener('click', function(){
        if(searchBox.style.display === 'none' || searchBox.style.display === '') {
            searchBox.style.display = 'block';
            toggleSearchBtn.textContent = '✕ Close Search';
            document.getElementById('userSearch').focus();
        } else {
            searchBox.style.display = 'none';
            toggleSearchBtn.textContent = '🔍 Search';
            resetSearch();
        }
    });
}

// Real-time user search functionality
const searchInput = document.getElementById('userSearch');
const usersTable = document.getElementById('usersTable');
const resultCounter = document.getElementById('resultCounter');
const visibleCount = document.getElementById('visibleCount');

if(searchInput) {
    searchInput.addEventListener('keyup', function() {
        const searchTerm = this.value.toLowerCase().trim();
        let visibleRows = 0;
        let totalRows = 0;

        const rows = usersTable.querySelectorAll('tr[data-user-id]');
        
        rows.forEach(row => {
            totalRows++;
            const userId = row.getAttribute('data-user-id');
            const username = row.getAttribute('data-username').toLowerCase();
            const email = row.getAttribute('data-email').toLowerCase();
            
            const matches = !searchTerm || 
                           username.includes(searchTerm) || 
                           email.includes(searchTerm) || 
                           userId.includes(searchTerm);
            
            if (matches) {
                row.style.display = '';
                visibleRows++;
                
                const nextEdit = row.nextElementSibling;
                const nextHistory = nextEdit ? nextEdit.nextElementSibling : null;
                if (nextEdit) nextEdit.style.display = '';
                if (nextHistory) nextHistory.style.display = '';
            } else {
                row.style.display = 'none';
                
                const nextEdit = row.nextElementSibling;
                const nextHistory = nextEdit ? nextEdit.nextElementSibling : null;
                if (nextEdit) nextEdit.style.display = 'none';
                if (nextHistory) nextHistory.style.display = 'none';
            }
        });

        visibleCount.textContent = visibleRows;
        
        if (searchTerm && visibleRows === 0) {
            resultCounter.innerHTML = `<span style="color: #e74c3c;">No users found matching "<strong>${searchTerm}</strong>"</span>`;
        } else if (visibleRows > 0) {
            resultCounter.textContent = `Showing ${visibleRows} user(s)`;
        } else {
            resultCounter.textContent = `Showing all users`;
        }
    });
}

// toggle add user form
const showAddBtn = document.getElementById('showAddUser');
const addFormDiv = document.getElementById('addUserForm');
if(showAddBtn && addFormDiv) {
    showAddBtn.addEventListener('click', function(){
        if(addFormDiv.style.display === 'none' || addFormDiv.style.display === '') {
            addFormDiv.style.display = 'block';
            showAddBtn.textContent = '- Hide Form';
        } else {
            addFormDiv.style.display = 'none';
            showAddBtn.textContent = '+ Add User';
        }
    });
}

// Reset search function
function resetSearch() {
    if(searchInput) {
        searchInput.value = '';
        searchInput.dispatchEvent(new Event('keyup'));
    }
}

// Handle hash navigation for sections
window.addEventListener('load', function() {
    const hash = window.location.hash.substring(1);
    if(hash === 'password-section' || hash === 'users-section') {
        showSection(hash);
    }
});

// Listen for hash changes
window.addEventListener('hashchange', function() {
    const hash = window.location.hash.substring(1);
    if(hash === 'password-section' || hash === 'users-section') {
        showSection(hash);
    }
});
</script>

<style>
/* Search box styling */
#userSearch {
    transition: all 0.3s ease;
}

#userSearch:focus {
    outline: none;
    border-color: #2d6a6a;
    box-shadow: 0 0 8px rgba(38, 166, 154, 0.3);
}

#resultCounter {
    font-weight: 500;
    animation: fadeIn 0.3s ease;
}

@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(-5px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Mobile search box */
@media (max-width: 768px) {
    #userSearch {
        padding: 8px 10px;
        font-size: 12px;
    }
    
    #resultCounter {
        font-size: 12px;
    }
}

@media (max-width: 480px) {
    #userSearch {
        padding: 8px;
        font-size: 12px;
    }
}

/* Password Log Styles */
.password-log-container {
    margin-top: 40px;
    padding-top: 20px;
    border-top: 2px solid #26a69a;
}

.password-log-container h2 {
    margin-top: 0;
}

.password-log-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 15px;
}

.password-log-table th,
.password-log-table td {
    padding: 12px;
    text-align: left;
    border-bottom: 1px solid #ddd;
}

.password-log-table th {
    background: #e0f2f1;
    color: #00796b;
    font-weight: 700;
    position: sticky;
    top: 0;
}

.password-log-table tr:hover {
    background: rgba(38,166,154,0.08);
}

.change-type {
    display: inline-block;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 12px;
    font-weight: 600;
}

.change-type.self {
    background: #e9fbf1;
    color: #155724;
}

.change-type.admin {
    background: #fff3cd;
    color: #856404;
}

.section {
  display: none;
}

.section.active {
  display: block;
}
</style>

<script>
// control menu button visibility (extra safety on top of CSS rules)
function refreshToggle() {
    const toggles = document.querySelectorAll('.toggle-sidebar');
    const show = window.innerWidth <= 768;
    toggles.forEach(b => b.style.display = show ? 'block' : 'none');
}
window.addEventListener('DOMContentLoaded', refreshToggle);
window.addEventListener('resize', refreshToggle);
</script>

</body>
</html>
