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
            // Redirect to index after 1 second
            header("refresh:1;url=index.php");
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
    if (!$editRow) {
        $error = 'Record not found.';
    }
}

// Admin update
if ($is_admin && $_SERVER['REQUEST_METHOD'] == "POST" && isset($_POST['edit_id'])) {
    $edit_id = intval($_POST['edit_id']);
    $new_cust = trim($_POST['customer_name'] ?? '');
    $new_amt = floatval($_POST['amount'] ?? 0);

    if ($new_amt <= 0) {
        $error = 'Amount must be greater than zero.';
    } elseif (empty($new_cust)) {
        $error = 'Customer name is required.';
    } else {
        $u = $conn->prepare("UPDATE refund SET customer_name=?, amount=? WHERE refund_id=?");
        $u->bind_param('sdi', $new_cust, $new_amt, $edit_id);
        if ($u->execute()) {
            $log = $conn->prepare("INSERT INTO stock_log (user_id, action_type, customer_name, amount) VALUES (?, 'Refund Correction', ?, ?)");
            $log->bind_param('isd', $user_id, $new_cust, $new_amt);
            $log->execute(); $log->close();
            $msg = 'Refund updated!';
            unset($editRow);
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
  <title>Refund • BottleBank</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link rel="stylesheet" href="asset/style.css">
  <style>
    .topbar .logo { width:40px; height:40px; background:#26a69a; color:white; border-radius:8px; display:flex; align-items:center; justify-content:center; font-weight:700; margin-right:10px; }
    .kv { color:#26a69a; text-decoration:none; }
    .kv:hover { text-decoration:underline; }
    .form-row { display:flex; gap:15px; margin-bottom:15px; }
    .form-row .col { flex:1; }
    .form-row .col label { display:block; font-weight:600; margin-bottom:5px; color:#333; }
    .form-row .col input, .form-row .col select { width:100%; padding:10px; border:1px solid #ddd; border-radius:6px; font-size:14px; }
    .form-row .col input:focus, .form-row .col select:focus { outline:none; border-color:#26a69a; box-shadow:0 0 4px rgba(38,166,154,0.2); }
    button { padding:10px 15px; border:none; border-radius:6px; cursor:pointer; font-weight:600; transition:0.3s; }
    button.primary { background:#26a69a; color:white; }
    button.primary:hover { background:#2e7d7d; }
    button.ghost { background:#80cbc4; color:#004d40; border:1px solid #80cbc4; }
    button.ghost:hover { background:#4db6ac; }
    .notice { padding:12px 15px; background:#e9fbf1; border-left:4px solid #26a69a; border-radius:6px; margin-bottom:15px; color:#155724; font-weight:500; }
    .error { padding:12px 15px; background:#ffecec; border-left:4px solid #ef5350; border-radius:6px; margin-bottom:15px; color:#c62828; font-weight:500; }
    .toggle-sidebar { background:none; border:none; font-size:18px; cursor:pointer; color:#2d6a6a; font-weight:600; display:none; transition:0.3s; }
    @media (max-width:768px) { .toggle-sidebar { display:block; } }
  </style>
</head>
<body>

<div class="sidebar-overlay" onclick="toggleSidebar()"></div>

<div class="sidebar">
    <div class="brand">
        <h1>BB</h1>
    </div>
    <nav class="sidebar-nav">
        <a href="index.php">Dashboard</a>
        <a href="deposit.php">Deposit</a>
        <a href="returns.php">Returns</a>
        <a href="refund.php" class="active">Refund</a>
        <a href="stock_log.php">Stock Log</a>
        <?php if($is_admin): ?>
        <a href="admin/admin_panel.php">Admin Panel</a>
        <?php endif; ?>
        <a href="logout.php" class="logout">Logout</a>
    </nav>
</div>

  <div class="app">
  <div class="topbar">
  <div class="brand"><button class="toggle-sidebar" onclick="toggleSidebar()">Menu</button><div class="logo">BB</div><div><h1>Refund</h1><p class="kv">Record and manage refunds</p></div></div>
  <div class="menu-wrap"><a href="index.php" class="kv">← Back to Dashboard</a></div>
  </div>

  <div class="grid">
    <div class="panel" style="grid-column: span 12;">
      <h3 style="margin-top:0">New Refund</h3>

      <?php if($msg): ?>
        <div class="notice"><?=htmlspecialchars($msg)?></div>
      <?php endif; ?>

      <?php if($error): ?><div class="error"><?=htmlspecialchars($error)?></div><?php endif; ?>

      <?php if($is_admin && isset($editRow)): ?>
        <h3>Edit Refund #<?= $editRow['refund_id'] ?></h3>
        <form method="POST" style="max-width:600px">
          <input type="hidden" name="edit_id" value="<?= $editRow['refund_id'] ?>">
          <div class="form-row">
            <div class="col">
              <label>Customer Name</label>
              <input type="text" name="customer_name" value="<?=htmlspecialchars($editRow['customer_name'])?>" required>
            </div>
          </div>
          <div class="form-row">
            <div class="col">
              <label>Amount</label>
              <input type="number" step="0.01" min="0.01" name="amount" value="<?=htmlspecialchars($editRow['amount'])?>" required id="amount">
            </div>
          </div>
          <div style="display:flex;gap:12px;margin-top:20px">
            <button type="submit" class="primary">Save Correction</button>
            <a href="refund.php"><button type="button" class="ghost">Cancel</button></a>
          </div>
        </form>
      <?php else: ?>
        <form method="POST" style="max-width:600px" onsubmit="return confirm('Record this refund?')">
          <div class="form-row">
            <div class="col">
              <label>Customer Name</label>
              <input type="text" name="customer_name" placeholder="Name of customer" required>
            </div>
          </div>
          <div class="form-row">
            <div class="col">
              <label>Amount (₱)</label>
              <input type="number" step="0.01" min="0.01" name="amount" placeholder="₱ 0.00" required>
            </div>
          </div>
          <div style="display:flex;gap:12px;margin-top:20px">
            <button type="submit" class="primary">Record Refund</button>
            <a href="index.php"><button type="button" class="ghost">Cancel</button></a>
          </div>
        </form>
      <?php endif; ?>
    </div>
  </div>
  </div>

<script>
function toggleSidebar(){
  const sidebar = document.querySelector('.sidebar');
  const overlay = document.querySelector('.sidebar-overlay');
  sidebar.classList.toggle('active');
  overlay.classList.toggle('active');
}

document.querySelectorAll('.sidebar-nav a').forEach(link => {
  link.addEventListener('click', function(){
    if(window.innerWidth <= 768){
      toggleSidebar();
    }
  });
});
</script>
</body>
</html>
