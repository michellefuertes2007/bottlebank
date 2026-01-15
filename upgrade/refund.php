<?php
include 'includes/db_connect.php';
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = intval($_SESSION['user_id']);
$is_admin = (isset($_SESSION['role']) && $_SESSION['role'] === 'admin');
$msg = '';
$error = '';

// Handle create refund
if ($_SERVER["REQUEST_METHOD"] == "POST" && !isset($_POST['edit_id'])) {
    $amount = floatval($_POST['amount'] ?? 0);
    $cust_name = trim($_POST['customer_name'] ?? '');
    // check if amount is positive
    if ($amount <= 0) {
        $error = 'Amount must be greater than zero.';
    } elseif (empty($cust_name)) {
        $error = 'Customer name is required.';
    } else {
        // insert into refund table
        $stmt = $conn->prepare("INSERT INTO refund (user_id, customer_name, amount, refund_date) VALUES (?, ?, ?, NOW())");
        $stmt->bind_param('isd', $user_id, $cust_name, $amount);
        if ($stmt->execute()) {
            // log the refund
            $log = $conn->prepare("INSERT INTO stock_log (user_id, action_type, customer_name, amount, details) VALUES (?, 'Refund', ?, ?, 'Successfully recorded')");
            $log->bind_param('isd', $user_id, $cust_name, $amount);
            $log->execute();
            $log->close();
            $msg = 'Refund recorded!';
        } else {
            $error = 'Database error: ' . $stmt->error;
        }
        $stmt->close();
    }
}

// Admin edit part
if ($is_admin && isset($_GET['edit_id'])) {
    $edit_id = intval($_GET['edit_id']);
    $e = $conn->prepare("SELECT refund_id, customer_name, amount FROM refund WHERE refund_id = ?");
    $e->bind_param('i', $edit_id);
    $e->execute();
    $editRow = $e->get_result()->fetch_assoc();
    $e->close();
}

if ($is_admin && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_id'])) {
    $edit_id = intval($_POST['edit_id']);
    $new_amount = floatval($_POST['amount'] ?? 0);
    $new_cust_name = trim($_POST['customer_name'] ?? '');
    if ($new_amount <= 0) {
        $error = 'Amount must be greater than zero.';
    } elseif (empty($new_cust_name)) {
        $error = 'Customer name is required.';
    } else {
        $u = $conn->prepare("UPDATE refund SET customer_name=?, amount=? WHERE refund_id=?");
        $u->bind_param('sdi', $new_cust_name, $new_amount, $edit_id);
        if ($u->execute()) {
            $log = $conn->prepare("INSERT INTO stock_log (user_id, action_type, customer_name, amount) VALUES (?, 'Correction', ?, ?)");
            $log->bind_param('isd', $user_id, $new_cust_name, $new_amount);
            $log->execute();
            $log->close();
            $msg = 'Refund updated by admin.';
        } else {
            $error = 'Update failed: ' . $u->error;
        }
        $u->close();
    }
}

?>
<!DOCTYPE html>
<html>
<head><title>Refund</title>
<link rel="stylesheet" href="asset/style.css">
<script>
function confirmRefund(e){
  const amt = parseFloat(document.getElementById('amount').value) || 0;
  if(amt<=0){ alert('Amount must be greater than zero.'); e.preventDefault(); return false; }
  if(!confirm('Are you sure you want to record this refund?')){ e.preventDefault(); return false; }
  return true;
}
</script>
</head>
<body>

<!-- Sidebar -->
<div class="sidebar">
    <div class="brand">
        <h1>BottleBank</h1>
    </div>
    <nav class="sidebar-nav">
        <a href="index.php">🏠 Dashboard</a>
        <a href="deposit.php">💰 Deposit</a>
        <a href="returns.php">🔁 Returns</a>
        <a href="refund.php" class="active">💸 Refund</a>
        <a href="stock_log.php">📦 Stock Log</a>
        <?php if($is_admin): ?>
        <a href="admin/admin_panel.php">⚙️ Admin Panel</a>
        <?php endif; ?>
        <a href="logout.php" class="logout">🚪 Logout</a>
    </nav>
</div>

  <div class="app">
  <div class="topbar">
  <div class="brand"><div class="logo">BB</div><div><h1>Refund</h1><p class="kv">Add new Refund</p></div></div>
  <div class="menu-wrap"><a href="index.php" class="kv">← Back to Dashboard</a></div>
  </div>
  <?php if($msg): ?><div class="notice"><?=htmlspecialchars($msg)?></div><?php endif; ?>
  <?php if($error): ?><div class="error"><?=htmlspecialchars($error)?></div><?php endif; ?>

<?php if($is_admin && isset($editRow)): ?>
  <h3>Edit Refund #<?= $editRow['refund_id'] ?></h3>
  <form method="POST">
  <input type="hidden" name="edit_id" value="<?= $editRow['refund_id'] ?>">
  Customer Name: <input type="text" name="customer_name" value="<?=htmlspecialchars($editRow['customer_name'])?>" required><br><br>
  Amount: <input type="number" step="0.01" min="0.01" name="amount" value="<?=htmlspecialchars($editRow['amount'])?>" required id="amount"><br><br>
  <button type="submit">Save Correction</button>
  </form>
<?php else: ?>
<h2>Refund Request</h2>
<form method="POST" onsubmit="return confirmRefund(event)">
  Customer Name: <input type="text" name="customer_name" required><br><br>
  Amount: <input type="number" name="amount" step="0.01" min="0.01" id="amount" required><br><br>
  <button type="submit">Submit</button>
</form>
<?php endif; ?>
</body>
</html>
