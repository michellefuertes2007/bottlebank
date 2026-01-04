<?php
session_start();
require 'db_connect.php';
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }
$user_id = intval($_SESSION['user_id']);
$msg = '';

// handle submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $customer_name = trim($_POST['customer_name'] ?? '');
  $bottle_type   = trim($_POST['bottle_type'] ?? '');
  $quantity      = intval($_POST['quantity'] ?? 0);
  $amount        = floatval($_POST['amount'] ?? 0);

  if ($quantity <= 0) $msg = 'Please enter a valid quantity.';
  else {
    // insert deposit
    $ins = $conn->prepare("INSERT INTO deposit (user_id, customer_name, bottle_type, quantity, deposit_date) VALUES (?, ?, ?, ?, NOW())");
    $ins->bind_param("isss",$user_id, $customer_name, $bottle_type, $quantity);
    if ($ins->execute()) {
      // insert stock_log
      $log = $conn->prepare("INSERT INTO stock_log (user_id, action_type, customer_name, bottle_type, quantity, amount) VALUES (?, 'Deposit', ?, ?, ?, ?)");
      $log->bind_param("issid",$user_id, $customer_name, $bottle_type, $quantity, $amount);
      $log->execute(); $log->close();

      $msg = 'Deposit recorded successfully.';
    } else {
      $msg = 'Database error: '.$ins->error;
    }
    $ins->close();
  }
}

// fetch last 12 deposits for this user
$stmt = $conn->prepare("SELECT deposit_id, customer_name, bottle_type, quantity, deposit_date FROM deposit WHERE deposit_id = ? ORDER BY deposit_date DESC LIMIT 12");
$stmt->bind_param("i",$user_id);
$stmt->execute();
$deposits = $stmt->get_result();
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

      <form method="post" style="max-width:760px">
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
            <input type="number" step="0.01" name="amount" placeholder="Total money (₱)">
          </div>
        </div>

        <div style="display:flex;gap:12px">
          <button type="submit" class="primary">Save Deposit</button>
          <a href="index.php"><button type="button" class="ghost">Cancel</button></a>
        </div>
      </form>

      <h3 style="margin-top:22px">Recent Deposits</h3>
      <div class="table-wrap">
        <table class="table">
          <thead><tr><th>#</th><th>Customer</th><th>Type</th><th>Quantity</th><th>Date</th></tr></thead>
          <tbody>
            <?php while($r = $deposits->fetch_assoc()): ?>
              <tr>
                <td><?=htmlspecialchars($r['deposit_id'])?></td>
                <td><?=htmlspecialchars($r['customer_name']?:'-')?></td>
                <td><?=htmlspecialchars($r['bottle_type'])?></td>
                <td><?=htmlspecialchars($r['quantity'])?></td>
                <td><?=htmlspecialchars($r['deposit_date'])?></td>
              </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
      </div>
    </div>

    <div class="side" style="grid-column: span 4;">
      <h4 style="margin-top:0">Quick Info</h4>
      <p class="hint">Deposits are logged and added to your Stock Log automatically.</p>
      <p class="kv">Logged in as <? $username = htmlspecialchars($_SESSION['username'])?><strong> <?$username?> </strong></p>
    </div>
  </div>

  <div class="footer">© <?=date('Y')?> BottleBank</div>
</div>
</body>
</html>
