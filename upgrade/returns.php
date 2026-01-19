<?php
include 'includes/db_connect.php';
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit(); }

$user_id = intval($_SESSION['user_id']);
$is_admin = (isset($_SESSION['role']) && $_SESSION['role'] === 'admin');
$msg = '';
$error = '';

// Get all bottle types from database
$bottle_types_list = [];
$btResult = $conn->query("SELECT type_id, type_name FROM bottle_types ORDER BY type_name ASC");
if ($btResult) {
  while ($row = $btResult->fetch_assoc()) {
    $bottle_types_list[] = $row;
  }
}

// Handle creation
if ($_SERVER["REQUEST_METHOD"] == "POST" && !isset($_POST['edit_id'])) {
  $customer_name = trim($_POST['customer_name'] ?? '');
  $bottle_type = trim($_POST['bottle_type'] ?? '');
  
  $quantity = intval($_POST['quantity'] ?? 0);

  if ($quantity <= 0) {
    $error = 'Please enter a valid quantity greater than zero.';
  } elseif (!$bottle_type) {
    $error = 'Please select a bottle type.';
  } else {
    $stmt = $conn->prepare("INSERT INTO returns (user_id, customer_name, bottle_type, quantity, return_date) VALUES (?, ?, ?, ?, NOW())");
    $stmt->bind_param('sisi', $user_id, $customer_name, $bottle_type, $quantity);
    if ($stmt->execute()) {
      // log
      $log = $conn->prepare("INSERT INTO stock_log (user_id, action_type, customer_name, bottle_type, quantity, details) VALUES (?, 'Return', ?, ?, ?, 'Successfully recorded')");
      $log->bind_param('issi', $user_id, $customer_name, $bottle_type, $quantity);
      $log->execute(); $log->close();
      $msg = 'Return recorded!';
      // Redirect to index after 1 second
      header("refresh:1;url=index.php");
    } else {
      $error = 'Database error: ' . $stmt->error;
    }
    $stmt->close();
  }
}

// Admin edit handling
if ($is_admin && isset($_GET['edit_id'])) {
  $edit_id = intval($_GET['edit_id']);
  $e = $conn->prepare("SELECT return_id, customer_name, bottle_type, quantity FROM returns WHERE return_id = ?");
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
  $new_customer = trim($_POST['customer_name'] ?? '');
  $new_type = trim($_POST['bottle_type'] ?? '');
  $new_qty = intval($_POST['quantity'] ?? 0);

  if ($new_qty <= 0) {
    $error = 'Quantity must be greater than zero.';
  } else {
    $u = $conn->prepare("UPDATE returns SET customer_name=?, bottle_type=?, quantity=? WHERE return_id=?");
    $u->bind_param('ssii', $new_customer, $new_type, $new_qty, $edit_id);
    if ($u->execute()) {
      $log = $conn->prepare("INSERT INTO stock_log (user_id, action_type, customer_name, bottle_type, quantity) VALUES (?, 'Return Correction', ?, ?, ?)");
      $log->bind_param('issi', $user_id, $new_customer, $new_type, $new_qty);
      $log->execute(); $log->close();
      $msg = 'Return updated!';
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
  <title>Returns • BottleBank</title>
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
        <a href="returns.php" class="active">Returns</a>
        <a href="refund.php">Refund</a>
        <a href="stock_log.php">Stock Log</a>
        <?php if($is_admin): ?>
        <a href="admin/admin_panel.php">Admin Panel</a>
        <?php endif; ?>
        <a href="logout.php" class="logout">Logout</a>
    </nav>
</div>

<div class="app">
  <div class="topbar">
  <div class="brand"><button class="toggle-sidebar" onclick="toggleSidebar()">Menu</button><div class="logo">BB</div><div><h1>Return</h1><p class="kv">Record and manage bottle returns</p></div></div>
  <div class="menu-wrap"><a href="index.php" class="kv">← Back to Dashboard</a></div>
  </div>
  
  <div class="grid">
    <div class="panel" style="grid-column: span 12;">
      <h3>New Return</h3>
      <?php if($msg): ?><div class="notice"><?=htmlspecialchars($msg)?></div><?php endif; ?>
      <?php if($error): ?><div class="error"><?=htmlspecialchars($error)?></div><?php endif; ?>

      <?php if($is_admin && isset($editRow)): ?>
        <h3>Edit Return #<?= $editRow['return_id'] ?></h3>
        <form method="POST" style="max-width:600px">
          <input type="hidden" name="edit_id" value="<?= $editRow['return_id'] ?>">
          <div class="form-row">
            <div class="col">
              <label>Customer Name</label>
              <input type="text" name="customer_name" value="<?=htmlspecialchars($editRow['customer_name'])?>" required>
            </div>
          </div>
          <div class="form-row">
            <div class="col">
              <label>Bottle Type</label>
              <select name="bottle_type" required>
                <option value="">Select type</option>
                <?php foreach ($bottle_types_list as $type): ?>
                  <option value="<?= htmlspecialchars($type['type_name']) ?>" <?= ($editRow['bottle_type'] ?? '') === $type['type_name'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($type['type_name']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
          <div class="form-row">
            <div class="col">
              <label>Quantity</label>
              <input type="number" name="quantity" value="<?=htmlspecialchars($editRow['quantity'])?>" min="1" required>
            </div>
          </div>
          <div style="display:flex;gap:12px;margin-top:20px">
            <button type="submit" class="primary">Save Correction</button>
            <a href="returns.php"><button type="button" class="ghost">Cancel</button></a>
          </div>
        </form>
      <?php else: ?>
        <form method="POST" style="max-width:600px" onsubmit="return confirm('Record this return?')">
          <div class="form-row">
            <div class="col">
              <label>Customer Name (optional)</label>
              <input type="text" name="customer_name" placeholder="Name of customer">
            </div>
          </div>
          <div class="form-row">
            <div class="col">
              <label>Bottle Type</label>
              <select name="bottle_type" required>
                <option value="">Select type...</option>
                <?php foreach ($bottle_types_list as $type): ?>
                  <option value="<?= htmlspecialchars($type['type_name']) ?>">
                    <?= htmlspecialchars($type['type_name']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
          <div class="form-row">
            <div class="col">
              <label>Quantity</label>
              <input type="number" name="quantity" min="1" placeholder="Number of bottles" required>
            </div>
          </div>
          <div style="display:flex;gap:12px;margin-top:20px">
            <button type="submit" class="primary">Record Return</button>
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

const bottleSelect = document.getElementById('bottleTypeSelect');
const customInput = document.getElementById('customBottleInput');

if(bottleSelect){
  bottleSelect.addEventListener('change', function(){
    if(this.value === '__custom__'){
      customInput.style.display = 'block';
      customInput.required = true;
    } else {
      customInput.style.display = 'none';
      customInput.required = false;
      customInput.value = '';
    }
  });
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
