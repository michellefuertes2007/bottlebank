<?php
session_start();
require 'includes/db_connect.php';
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }
$user_id = intval($_SESSION['user_id']);
$is_admin = (isset($_SESSION['role']) && $_SESSION['role'] === 'admin');
$msg = '';
$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['edit_id'])) {
  $customer_name = trim($_POST['customer_name'] ?? '');
  $bottle_type   = trim($_POST['bottle_type'] ?? '');
  $quantity      = intval($_POST['quantity'] ?? 0);
  $amount        = floatval($_POST['amount'] ?? 0);

  // Check if quantity is valid
  if ($quantity <= 0) {
    $error = 'Please enter a valid quantity greater than zero.';
  } elseif ($amount < 0) {
    $error = 'Amount cannot be negative.';
  } else {
    // insert deposit using prepared statement
    $ins = $conn->prepare("INSERT INTO deposit (user_id, customer_name, bottle_type, quantity, deposit_date) VALUES (?, ?, ?, ?, NOW())");
    $ins->bind_param("issi", $user_id, $customer_name, $bottle_type, $quantity);
    if ($ins->execute()) {
      // insert stock_log
      $log = $conn->prepare("INSERT INTO stock_log (user_id, action_type, customer_name, bottle_type, quantity, amount) VALUES (?, 'Deposit', ?, ?, ?, ?)");
      $log->bind_param("issid", $user_id, $customer_name, $bottle_type, $quantity, $amount);
      $log->execute(); $log->close();

      $msg = 'Deposit recorded successfully.';
    } else {
      $error = 'Database error: ' . $ins->error;
    }
    $ins->close();
  }
}

// fetch last 12 deposits for this user
$stmt = $conn->prepare("SELECT deposit_id, customer_name, bottle_type, quantity, deposit_date FROM deposit WHERE user_id = ? ORDER BY deposit_date DESC LIMIT 12");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$deposits = $stmt->get_result();

// Handle admin correction (edit)
if ($is_admin && isset($_GET['edit_id'])) {
  $edit_id = intval($_GET['edit_id']);
  $eSt = $conn->prepare("SELECT deposit_id, customer_name, bottle_type, quantity, amount FROM deposit WHERE deposit_id = ?");
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
</head>
<body>

<!-- Sidebar -->
<div class="sidebar">
    <div class="brand">
        <h1>BottleBank</h1>
    </div>
    <nav class="sidebar-nav">
        <a href="index.php">🏠 Dashboard</a>
        <a href="deposit.php" class="active">💰 Deposit</a>
        <a href="returns.php">🔁 Returns</a>
        <a href="refund.php">💸 Refund</a>
        <a href="stock_log.php">📦 Stock Log</a>
        <?php if($is_admin): ?>
        <a href="admin/admin_panel.php">⚙️ Admin Panel</a>
        <?php endif; ?>
        <a href="logout.php" class="logout">🚪 Logout</a>
    </nav>
</div>
  
<div class="app">
  <div class="topbar">
    <div class="brand"><div class="logo">BB</div><div><h1>Deposit</h1><p class="kv">Add new deposit</p></div></div>
    <div class="menu-wrap"><a href="index.php" class="kv">← Back to Dashboard</a></div>
  </div>

  <div class="grid" style="margin-top:8px;">
    <div class="panel" style="grid-column: span 8;">
      <h3 style="margin-top:0">New Deposit</h3>

      <?php if($msg): ?>
        <div style="padding:12px;background:#e9fbf1;border-left:4px solid var(--accent2);border-radius:8px;margin-bottom:12px;"><?=htmlspecialchars($msg)?></div>
      <?php endif; ?>

      <?php if($error): ?><div class="error" style="margin-bottom:12px;padding:8px;background:#ffecec;border-left:4px solid #f5c6cb"><?=htmlspecialchars($error)?></div><?php endif; ?>
      <form method="post" style="max-width:760px" onsubmit="return confirmDeposit(event)">
        <div class="form-row">
          <div class="col">
            <label>Customer Name</label>
            <input type="text" name="customer_name" placeholder="Customer or client name">
          </div>
          <div class="col">
            <label>Bottle Type</label>
            <select name="bottle_type" required>
              <option value="">Select type</option>
              <option>Plastic Bottle (PET)</option>
              <option>Glass Bottle</option>
              <option>Can</option>
            </select>
          </div>
        </div>

        <div class="form-row">
          <div class="col">
            <label>Quantity</label>
            <input type="number" name="quantity" min="1" required>
          </div>
          <div class="col">
            <label>Amount (optional)</label>
            <input type="number" step="0.01" name="amount" min="0" placeholder="Total money (₱)">
          </div>
        </div>

        <div style="display:flex;gap:12px">
          <button type="submit" class="primary">Save Deposit</button>
          <a href="index.php"><button type="button" class="ghost">Cancel</button></a>
        </div>
      </form>

      <script>
      function confirmDeposit(e){
        const qty = parseInt(document.querySelector('[name="quantity"]').value) || 0;
        const amt = parseFloat(document.querySelector('[name="amount"]').value) || 0;
        if(qty<=0){ alert('Quantity must be greater than zero.'); e.preventDefault(); return false; }
        if(amt<0){ alert('Amount cannot be negative.'); e.preventDefault(); return false; }
        return confirm('Confirm save deposit?');
      }
      </script>
      
      <?php if($is_admin && isset($editRow)): ?>
        <h3>Edit Deposit #<?= $editRow['deposit_id'] ?></h3>
        <form method="POST" onsubmit="return confirmCorrection(event)">
          <input type="hidden" name="edit_id" value="<?= $editRow['deposit_id'] ?>">
          <label>Customer Name</label>
          <input type="text" name="customer_name" value="<?=htmlspecialchars($editRow['customer_name'])?>">
          <label>Bottle Type</label>
          <input type="text" name="bottle_type" value="<?=htmlspecialchars($editRow['bottle_type'])?>">
          <label>Quantity</label>
          <input type="number" name="quantity" min="1" value="<?=htmlspecialchars($editRow['quantity'])?>" required>
          <label>Amount (optional)</label>
          <input type="number" step="0.01" name="amount" min="0" value="<?=htmlspecialchars($editRow['amount'] ?? '')?>">
          <div style="margin-top:8px"><button type="submit">Save Correction</button></div>
        </form>
        <script>
          function confirmCorrection(e){
            const amtInput = document.querySelector('form input[name="amount"]');
            const amt = parseFloat(amtInput && amtInput.value ? amtInput.value : '0') || 0;
            if(amt < 0){ alert('Amount cannot be negative.'); e.preventDefault(); return false; }
            return confirm('Apply this correction?');
          }
        </script>
      <?php endif; ?>
      </form>

      <h3 style="margin-top:22px">Recent Deposits</h3>
      <div class="table-wrap">
        <table class="table">
          <thead><tr><th>#</th><th>Customer</th><th>Type</th><th>Quantity</th><th>Date</th><?php if($is_admin) echo '<th>Actions</th>'; ?></tr></thead>
          <tbody>
            <?php while($r = $deposits->fetch_assoc()): ?>
              <tr>
                <td><?=htmlspecialchars($r['deposit_id'])?></td>
                <td><?=htmlspecialchars($r['customer_name']?:'-')?></td>
                <td><?=htmlspecialchars($r['bottle_type'])?></td>
                <td><?=htmlspecialchars($r['quantity'])?></td>
                <td><?=htmlspecialchars($r['deposit_date'])?></td>
                <?php if($is_admin): ?>
                  <td><a href="?edit_id=<?=htmlspecialchars($r['deposit_id'])?>">Edit</a></td>
                <?php endif; ?>
              </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="footer">© <?=date('Y')?> BottleBank</div>
</div>
</body>
</html>
