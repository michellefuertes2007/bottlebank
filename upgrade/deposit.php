<?php
session_start();
require 'includes/db_connect.php';
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }
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

// Handle adding new bottle type
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_bottle_type'])) {
  $new_type = trim($_POST['new_bottle_type'] ?? '');
  if ($new_type) {
    $stmt = $conn->prepare("INSERT INTO bottle_types (type_name, created_by) VALUES (?, ?)");
    $stmt->bind_param("si", $new_type, $user_id);
    if ($stmt->execute()) {
      $msg = "Bottle type '$new_type' added successfully!";
      header("refresh:1");
    } else {
      $error = "Error adding bottle type: " . $stmt->error;
    }
    $stmt->close();
  }
}

// Handle form submission for deposits
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['edit_id']) && !isset($_POST['add_bottle_type'])) {
  $customer_name = trim($_POST['customer_name'] ?? '');
  $bottle_type = trim($_POST['bottle_type'] ?? '');
  $quantity = intval($_POST['quantity'] ?? 0);
  $amount = floatval($_POST['amount'] ?? 0);
  
  // Validate single bottle entry
  if ($quantity <= 0) {
    $error = 'Please enter a quantity greater than zero.';
  } elseif ($amount < 0) {
    $error = 'Amount cannot be negative.';
  } elseif (!$bottle_type) {
    $error = 'Please select a bottle type.';
  } else {
    // insert single deposit
    $ins = $conn->prepare("INSERT INTO deposit (user_id, customer_name, bottle_type, quantity, deposit_date) VALUES (?, ?, ?, ?, NOW())");
    $ins->bind_param("issi", $user_id, $customer_name, $bottle_type, $quantity);
    if ($ins->execute()) {
      // insert stock_log
      $log = $conn->prepare("INSERT INTO stock_log (user_id, action_type, customer_name, bottle_type, quantity, amount) VALUES (?, 'Deposit', ?, ?, ?, ?)");
      $log->bind_param("issii", $user_id, $customer_name, $bottle_type, $quantity, $amount);
      $log->execute(); 
      $log->close();
      $msg = 'Deposit recorded successfully!';
      header("refresh:1;url=index.php");
    } else {
      $error = 'Database error: ' . $ins->error;
    }
    $ins->close();
  }
}

// Admin edit handling
if ($is_admin && isset($_GET['edit_id'])) {
  $edit_id = intval($_GET['edit_id']);
  $eSt = $conn->prepare("SELECT deposit_id, customer_name, bottle_type, quantity, deposit_date FROM deposit WHERE deposit_id = ?");
  $eSt->bind_param('i', $edit_id);
  $eSt->execute();
  $editRow = $eSt->get_result()->fetch_assoc();
  $eSt->close();
  if (!$editRow) {
    $error = 'Record not found for editing.';
  }
}

// Process admin update
if ($is_admin && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_id'])) {
  $edit_id = intval($_POST['edit_id']);
  $new_customer = trim($_POST['customer_name'] ?? '');
  $new_bottle = trim($_POST['bottle_type'] ?? '');
  $new_qty = intval($_POST['quantity'] ?? 0);
  $new_amount = floatval($_POST['amount'] ?? 0);

  if ($new_qty <= 0) {
    $error = 'Correction quantity must be greater than zero.';
  } elseif ($new_amount < 0) {
    $error = 'Amount cannot be negative.';
  } else {
    $up = $conn->prepare("UPDATE deposit SET customer_name=?, bottle_type=?, quantity=? WHERE deposit_id=?");
    $up->bind_param('ssii', $new_customer, $new_bottle, $new_qty, $edit_id);
    if ($up->execute()) {
      $log = $conn->prepare("INSERT INTO stock_log (user_id, action_type, customer_name, bottle_type, quantity, amount) VALUES (?, 'Correction', ?, ?, ?, ?)");
      $note = "Correction for deposit #$edit_id";
      $log->bind_param('issid', $user_id, $note, $new_bottle, $new_qty, $new_amount);
      $log->execute(); $log->close();
      $msg = 'Record updated by admin.';
    } else {
      $error = 'Update failed: ' . $up->error;
    }
    $up->close();
  }
}
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>Deposit • BottleBank</title>
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
    .topbar .toggle-sidebar { background:none; border:none; font-size:18px; cursor:pointer; color:#2d6a6a; font-weight:600; display:none; transition:0.3s; }
    .topbar .toggle-sidebar:hover { color:#00796b; }
    @media (max-width:768px) { .topbar .toggle-sidebar { display:block; } }
  </style>
</head>
<body>

<!-- Sidebar Overlay -->
<div class="sidebar-overlay" onclick="toggleSidebar()"></div>

<!-- Sidebar -->
<div class="sidebar">
    <div class="brand">
        <h1>BB</h1>
    </div>
    <nav class="sidebar-nav">
        <a href="index.php">Dashboard</a>
        <a href="deposit.php" class="active">Deposit</a>
        <a href="returns.php">Returns</a>
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
    <div class="brand">
        <button class="toggle-sidebar" onclick="toggleSidebar()">Menu</button><div class="logo">BB</div><div><h1>Deposit</h1><p class="kv">Record and manage bottle deposits</p></div></div>
    <div class="menu-wrap"><a href="index.php" class="kv">← Back to Dashboard</a></div>
  </div>

  <div class="grid" style="margin-top:8px;">
    <!-- Form -->
    <div class="panel" style="grid-column: span 12;">
      <h3 style="margin-top:0">New Deposit</h3>
      <?php if($msg): ?><div class="notice"><?=htmlspecialchars($msg)?></div><?php endif; ?>
      <?php if($error): ?><div class="error"><?=htmlspecialchars($error)?></div><?php endif; ?>
      
      <form method="post" style="max-width:760px" id="depositForm">
        <div class="bottle-entry" style="border:1px solid #e0e0e0;padding:15px;border-radius:6px;margin-bottom:15px;background:#fafafa;">
          
          <div class="form-row">
            <div class="col">
              <label>Customer Name</label>
              <input type="text" name="customer_name" placeholder="Enter customer name">
            </div>
          </div>

          <div class="form-row">
            <div class="col">
              <label>Bottle Type</label>
              <div style="display:flex;gap:8px;align-items:center;">
                <select name="bottle_type" required style="flex:1;">
                  <option value="">Select type...</option>
                  <?php foreach ($bottle_types_list as $type): ?>
                    <option value="<?= htmlspecialchars($type['type_name']) ?>"><?= htmlspecialchars($type['type_name']) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>
            <div class="col">
              <label>Quantity</label>
              <input type="number" name="quantity" min="1" placeholder="Number of bottles" required>
            </div>
          </div>

          <div class="form-row">
            <div class="col">
              <label>Amount (optional)</label>
              <input type="number" step="0.01" name="amount" min="0" placeholder="₱ 0.00">
            </div>
          </div>
        </div>

        <div style="display:flex;gap:12px;margin-top:20px;flex-wrap:wrap;align-items:center;">
          <button type="submit" class="primary">Save Deposit</button>
          <a href="index.php"><button type="button" class="ghost" style="background:#e0e0e0;color:#666;">Cancel</button></a>
        </div>
      </form>

      <!-- Add New Bottle Type Section -->
      <div style="margin-top:30px;padding:15px;background:#e9fbf1;border-radius:6px;border:1px solid #26a69a;">
        <h4 style="color:#2d6a6a;margin-top:0;">Add New Bottle Type</h4>
        <p style="color:#666;font-size:13px;margin-bottom:12px;">Don't see the bottle type you need? Add it here and it will be available for all deposits.</p>
        <form method="post" style="display:flex;gap:10px;">
          <input type="text" name="new_bottle_type" placeholder="Enter new bottle type (e.g., Glass Jug, Carton)" required style="flex:1;padding:10px;border:1px solid #26a69a;border-radius:6px;">
          <button type="submit" name="add_bottle_type" class="primary" style="white-space:nowrap;">Add Type</button>
        </form>
      </div>


      <script>
      function toggleSidebar(){
        document.querySelector('.sidebar').classList.toggle('show');
        document.querySelector('.sidebar-overlay').classList.toggle('show');
      }
      document.querySelector('.sidebar-overlay').addEventListener('click', toggleSidebar);
      </script>
    </div>
  </div>
  
  <?php if ($is_admin && isset($editRow)): ?>
  <div class="panel" style="margin-top:20px;">
    <h3>Edit Deposit #<?= $editRow['deposit_id'] ?></h3>
    <form method="POST" style="max-width:600px">
      <input type="hidden" name="edit_id" value="<?= $editRow['deposit_id'] ?>">
      <div class="form-row">
        <div class="col">
          <label>Customer Name</label>
          <input type="text" name="customer_name" value="<?=htmlspecialchars($editRow['customer_name'])?>" required>
        </div>
      </div>
      <div class="form-row">
        <div class="col">
          <label>Bottle Type</label>
          <input type="text" name="bottle_type" value="<?=htmlspecialchars($editRow['bottle_type'])?>" required>
        </div>
      </div>
      <div class="form-row">
        <div class="col">
          <label>Quantity</label>
          <input type="number" name="quantity" value="<?=htmlspecialchars($editRow['quantity'])?>" min="1" required>
        </div>
        <div class="col">
          <label>Amount</label>
          <input type="number" step="0.01" min="0" name="amount" placeholder="₱ 0.00" required>
        </div>
      </div>
      <div style="display:flex;gap:12px;margin-top:20px">
        <button type="submit" class="primary">Save Correction</button>
        <a href="deposit.php"><button type="button" class="ghost">Cancel</button></a>
      </div>
    </form>
  </div>
  <?php endif; ?>

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
