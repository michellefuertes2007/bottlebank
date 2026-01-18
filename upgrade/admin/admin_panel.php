<?php
session_start();
require '../includes/db_connect.php';

// Only admin can access
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

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
// Handle User Updates
// =====================
// Ensure `force_change` column exists
$colCheck = $conn->query("SHOW COLUMNS FROM user LIKE 'force_change'");
if ($colCheck->num_rows === 0) {
    $conn->query("ALTER TABLE user ADD COLUMN force_change TINYINT(1) NOT NULL DEFAULT 0");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_user'])) {
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
                // log the reset
                $admin_id = intval($_SESSION['user_id']);
                $action = 'Password Reset';
                $target_username = $res['username'];
                $log = $conn->prepare("INSERT INTO stock_log (user_id, action_type, customer_name) VALUES (?, ?, ?)");
                $log->bind_param('iss', $admin_id, $action, $target_username);
                $log->execute(); $log->close();

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
$per_page = 5;
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
  background: #f0f7f7;
  color: #333;
}

.container {
  max-width: 1200px;
  margin: 20px auto;
  padding: 20px;
  background: #fff;
  border-radius: 12px;
  box-shadow: 0 4px 15px rgba(0,0,0,0.05);
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
  background: #26a69a;
  color: #fff;
  text-decoration: none;
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

/* Responsive Design */
@media (max-width: 768px) {
  .container {
    padding: 15px;
    margin: 10px;
  }

  h1 {
    font-size: 22px;
    margin-bottom: 20px;
  }

  h2 {
    font-size: 18px;
  }

  h3 {
    font-size: 16px;
  }

  th, td {
    padding: 8px 10px;
    font-size: 12px;
  }

  .button {
    padding: 6px 10px;
    font-size: 11px;
    margin-right: 4px;
    margin-bottom: 4px;
  }

  form input,
  form select,
  form textarea {
    padding: 8px 10px;
    font-size: 13px;
  }

  form button {
    padding: 8px 12px;
    font-size: 13px;
  }

  .action-buttons {
    flex-direction: column;
  }

  .action-buttons .button {
    display: block;
    width: 100%;
    text-align: center;
    margin-right: 0;
    margin-bottom: 6px;
  }

  .stats {
    grid-template-columns: 1fr;
  }

  table {
    font-size: 12px;
  }

  table th,
  table td {
    padding: 8px;
  }
}

@media (max-width: 480px) {
  .container {
    padding: 12px;
    margin: 5px;
    border-radius: 8px;
  }

  h1 {
    font-size: 18px;
    margin-bottom: 15px;
  }

  h2 {
    font-size: 16px;
    margin-top: 20px;
  }

  h3 {
    font-size: 14px;
  }

  th, td {
    padding: 6px 8px;
    font-size: 11px;
  }

  .button {
    padding: 5px 8px;
    font-size: 10px;
  }

  form input,
  form select,
  form textarea {
    padding: 8px;
    font-size: 12px;
  }

  table {
    overflow-x: auto;
  }

  .stat-card {
    padding: 12px;
  }

  .stat-card .value {
    font-size: 20px;
  }
}
</style>
</head>
<body>
<div class="container">
<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
  <h1 style="margin: 0;">Admin Panel - BottleBank</h1>
  <a href="../index.php" class="button danger">← Back to Dashboard</a>
</div>

<?php if(isset($msg)) echo "<div class='msg'>{$msg}</div>"; ?>

<h2>Registered Users</h2>

<!-- Search Bar -->
<div style="margin-bottom: 20px; display: flex; gap: 10px; align-items: center;">
    <input 
        type="text" 
        id="userSearch" 
        placeholder="🔍 Search by username, email, or ID..." 
        style="flex: 1; padding: 10px; border: 2px solid #26a69a; border-radius: 6px; font-family: 'Poppins', sans-serif; font-size: 14px;">
    <button onclick="resetSearch()" class="button secondary" style="white-space: nowrap;">Clear Search</button>
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
<label style="font-weight: 600; display: block; margin-bottom: 6px; color: #2d6a6a;">Username</label>
<input type="text" name="username" value="<?= htmlspecialchars($row['username']) ?>" placeholder="Username" required>
</div>
<div>
<label style="font-weight: 600; display: block; margin-bottom: 6px; color: #2d6a6a;">Email</label>
<input type="email" name="email" value="<?= htmlspecialchars($row['email']) ?>" placeholder="Email" required>
</div>
<div>
<label style="font-weight: 600; display: block; margin-bottom: 6px; color: #2d6a6a;">Role</label>
<select name="role">
    <option value="user" <?= $row['role'] === 'user' ? 'selected' : '' ?>>User</option>
    <option value="admin" <?= $row['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
</select>
</div>
<div>
<label style="font-weight: 600; display: block; margin-bottom: 6px; color: #2d6a6a;">New Password</label>
<input type="password" name="password" placeholder="Leave blank to keep current password">
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
<th>Bottle Type</th>
<th>Qty</th>
<th>Amount</th>
<th>Date</th>
</tr>
<?php while($l = $logs->fetch_assoc()): ?>
<tr>
<td><?= $l['log_id'] ?></td>
<td><strong><?= htmlspecialchars($l['action_type']) ?></strong></td>
<td><?= !empty($l['customer_name']) ? htmlspecialchars($l['customer_name']) : '<span style="color: #999;">N/A</span>' ?></td>
<td><?= !empty($l['bottle_type']) ? htmlspecialchars($l['bottle_type']) : '<span style="color: #999;">N/A</span>' ?></td>
<td><?= !empty($l['quantity']) && $l['quantity'] > 0 ? $l['quantity'] : '<span style="color: #999;">N/A</span>' ?></td>
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

<script>
// Real-time user search functionality
const searchInput = document.getElementById('userSearch');
const usersTable = document.getElementById('usersTable');
const resultCounter = document.getElementById('resultCounter');
const visibleCount = document.getElementById('visibleCount');

searchInput.addEventListener('keyup', function() {
    const searchTerm = this.value.toLowerCase().trim();
    let visibleRows = 0;
    let totalRows = 0;

    // Get all user rows (every row with a user-data class)
    const rows = usersTable.querySelectorAll('tr[data-user-id]');
    
    rows.forEach(row => {
        totalRows++;
        const userId = row.getAttribute('data-user-id');
        const username = row.getAttribute('data-username').toLowerCase();
        const email = row.getAttribute('data-email').toLowerCase();
        
        // Match if search term is in username, email, or user ID
        const matches = !searchTerm || 
                       username.includes(searchTerm) || 
                       email.includes(searchTerm) || 
                       userId.includes(searchTerm);
        
        if (matches) {
            row.style.display = '';
            visibleRows++;
            
            // Also show the edit and history rows for visible users
            const nextEdit = row.nextElementSibling;
            const nextHistory = nextEdit ? nextEdit.nextElementSibling : null;
            if (nextEdit) nextEdit.style.display = '';
            if (nextHistory) nextHistory.style.display = '';
        } else {
            row.style.display = 'none';
            
            // Also hide the edit and history rows
            const nextEdit = row.nextElementSibling;
            const nextHistory = nextEdit ? nextEdit.nextElementSibling : null;
            if (nextEdit) nextEdit.style.display = 'none';
            if (nextHistory) nextHistory.style.display = 'none';
        }
    });

    // Update counter
    visibleCount.textContent = visibleRows;
    
    // Show message if no results
    if (searchTerm && visibleRows === 0) {
        resultCounter.innerHTML = `<span style="color: #e74c3c;">No users found matching "<strong>${searchTerm}</strong>"</span>`;
    } else if (visibleRows > 0) {
        resultCounter.textContent = `Showing ${visibleRows} user(s)`;
    } else {
        resultCounter.textContent = `Showing all users`;
    }
});

// Reset search function
function resetSearch() {
    searchInput.value = '';
    searchInput.dispatchEvent(new Event('keyup'));
    searchInput.focus();
}

// Auto-focus search input when page loads
window.addEventListener('load', function() {
    searchInput.focus();
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
</style>
</body>
</html>
