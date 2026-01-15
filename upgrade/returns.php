<?php
include 'includes/db_connect.php';
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit(); }

$user_id = intval($_SESSION['user_id']);
$is_admin = (isset($_SESSION['role']) && $_SESSION['role'] === 'admin');
$msg = '';
$error = '';

// Handle creation
if ($_SERVER["REQUEST_METHOD"] == "POST" && !isset($_POST['edit_id'])) {
  $customer_name = trim($_POST['customer_name'] ?? '');
  $bottle_type = trim($_POST['bottle_type'] ?? '');
  $quantity = intval($_POST['quantity'] ?? 0);

  if ($quantity <= 0) {
    $error = 'Please enter a valid quantity greater than zero.';
  } else {
    $stmt = $conn->prepare("INSERT INTO returns (user_id, customer_name, bottle_type, quantity, return_date) VALUES (?, ?, ?, ?, NOW())");
    $stmt->bind_param('sisi', $user_id, $customer_name, $bottle_type, $quantity);
    if ($stmt->execute()) {
      // log
      $log = $conn->prepare("INSERT INTO stock_log (user_id, action_type, customer_name, bottle_type, quantity, details) VALUES (?, 'Return', ?, ?, ?, 'Successfully recorded')");
      $log->bind_param('issi', $user_id, $customer_name, $bottle_type, $quantity);
      $log->execute(); $log->close();
      $msg = 'Return recorded!';
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
}

if ($is_admin && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_id'])) {
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
      $log = $conn->prepare("INSERT INTO stock_log (user_id, action_type, customer_name, bottle_type, quantity) VALUES (?, 'Correction', ?, ?, ?)");
      $note = "Correction for return #$edit_id";
      $log->bind_param('issi', $user_id, $note, $new_type, $new_qty);
      $log->execute(); $log->close();
      $msg = 'Return updated by admin.';
    } else {
      $error = 'Update failed: ' . $u->error;
    }
    $u->close();
  }
}

?>
<!DOCTYPE html>
<html>
<head><title>Return</title>
<link rel="stylesheet" href="asset/style.css">
<script>
function confirmSubmit(e){
  const qty = parseInt(document.getElementById('quantity').value) || 0;
  if(qty<=0){
    alert('Quantity must be greater than zero.');
    e.preventDefault(); return false;
  }
  if(!confirm('Are you sure you want to record this return?')){ e.preventDefault(); return false; }
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
        <a href="returns.php" class="active">🔁 Returns</a>
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
  <div class="brand"><div class="logo">BB</div><div><h1>Return</h1><p class="kv">Add new Return</p></div></div>
  <div class="menu-wrap"><a href="index.php" class="kv">← Back to Dashboard</a></div>
  </div>
  <?php if($msg): ?><div class="notice"><?=htmlspecialchars($msg)?></div><?php endif; ?>
  <?php if($error): ?><div class="error"><?=htmlspecialchars($error)?></div><?php endif; ?>
<?php if($is_admin && isset($editRow)): ?>
  <h3>Edit Return #<?= $editRow['return_id'] ?></h3>
  <form method="POST">
  <input type="hidden" name="edit_id" value="<?= $editRow['return_id'] ?>">
  Customer Name (optional): <input type="text" name="customer_name" value="<?=htmlspecialchars($editRow['customer_name'] ?? '')?>" required><br><br>
  Bottle Type: <select name="bottle_type" required>
    <option value="">Select type</option>
    <option value="Plastic Bottle (PET)" <?= ($editRow['bottle_type'] ?? '') === 'Plastic Bottle (PET)' ? 'selected' : '' ?>>Plastic Bottle (PET)</option>
    <option value="Glass Bottle" <?= ($editRow['bottle_type'] ?? '') === 'Glass Bottle' ? 'selected' : '' ?>>Glass Bottle</option>
    <option value="Can" <?= ($editRow['bottle_type'] ?? '') === 'Can' ? 'selected' : '' ?>>Can</option>
  </select><br><br>
  Quantity: <input type="number" name="quantity" value="<?=htmlspecialchars($editRow['quantity'])?>" id="quantity" min="1" required><br><br>
  <button type="submit">Save Correction</button>
  </form>
<?php else: ?>
<form method="POST" onsubmit="return confirmSubmit(event)">
  Customer Name (optional): <input type="text" name="customer_name" required><br><br>
  Bottle Type: <select name="bottle_type" required>
    <option value="">Select type</option>
    <option value="Plastic Bottle (PET)">Plastic Bottle (PET)</option>
    <option value="Glass Bottle">Glass Bottle</option>
    <option value="Can">Can</option>
  </select><br><br>
  Quantity: <input type="number" name="quantity" id="quantity" min="1" required><br><br>
  <button type="submit">Submit</button>
</form>
<?php endif; ?>
</div>
</body>
</html>
