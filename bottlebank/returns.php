<?php
include 'includes/db_connect.php';

// helper to determine if a table exists in the current database
function tableExists($conn, $table) {
    $res = $conn->query("SHOW TABLES LIKE '$table'");
    return ($res && $res->num_rows > 0);
}

// helper to add column if missing, mirrors deposit.php logic
// skips if the table itself doesn't exist yet
function ensureColumn($conn, $table, $column, $definition) {
    if (!tableExists($conn, $table)) {
        return;
    }
    $res = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
    if(!$res || $res->num_rows === 0) {
        $conn->query("ALTER TABLE `$table` ADD COLUMN $definition");
    }
}
// ensure necessary size column exists
ensureColumn($conn, 'returns', 'bottle_size', "bottle_size VARCHAR(10) DEFAULT 'small'");
ensureColumn($conn, 'customers', 'bottle_size', "bottle_size VARCHAR(10) DEFAULT 'small'");
// log table may eventually store size too
ensureColumn($conn, 'stock_log', 'bottle_size', "bottle_size VARCHAR(10) DEFAULT 'small'");

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

// Validation function: Get customer's available bottles (deposits - returns)
function getCustomerAvailableBottles($conn, $customer_name) {
  $available_bottles = [];
  
  // Get all deposits for this customer from stock_log
  $deposits_query = $conn->prepare("SELECT details FROM stock_log WHERE action_type='Deposit' AND customer_name = ? ORDER BY date_logged DESC");
  if (!$deposits_query) return [];
  
  $deposits_query->bind_param('s', $customer_name);
  $deposits_query->execute();
  $deposits_result = $deposits_query->get_result();
  
  // Parse deposits to extract bottle info
  $deposited = [];
  while ($row = $deposits_result->fetch_assoc()) {
    if (preg_match_all('/(\d+)x\s+(\w+(?:\s+\w+)?)\s+([^(]+)\s*\(₱([\d,]+\.\d{2})\)/', $row['details'], $matches)) {
      for ($i = 0; $i < count($matches[0]); $i++) {
        $bottle_type = trim($matches[2][$i]);
        $bottle_size = trim($matches[3][$i]);
        $qty = intval($matches[1][$i]);
        
        if (!isset($deposited[$bottle_type])) {
          $deposited[$bottle_type] = ['size' => $bottle_size, 'qty' => 0];
        }
        $deposited[$bottle_type]['qty'] += $qty;
      }
    }
  }
  $deposits_query->close();
  
  // Get all returns for this customer
  $returns_query = $conn->prepare("SELECT bottle_type, SUM(quantity) as total_returned FROM stock_log WHERE action_type='Return' AND customer_name = ? AND bottle_type IS NOT NULL GROUP BY bottle_type");
  if (!$returns_query) return $available_bottles;
  
  $returns_query->bind_param('s', $customer_name);
  $returns_query->execute();
  $returns_result = $returns_query->get_result();
  
  $returned = [];
  while ($row = $returns_result->fetch_assoc()) {
    $returned[$row['bottle_type']] = intval($row['total_returned']);
  }
  $returns_query->close();
  
  // Calculate available bottles
  foreach ($deposited as $bottle_type => $info) {
    $available_qty = $info['qty'] - ($returned[$bottle_type] ?? 0);
    if ($available_qty > 0) {
      $available_bottles[$bottle_type] = [
        'bottle_size' => $info['size'],
        'available_qty' => $available_qty,
        'deposited_qty' => $info['qty'],
        'returned_qty' => $returned[$bottle_type] ?? 0
      ];
    }
  }
  return $available_bottles;
}

// Validation function: Check if a specific return is valid
function validateReturnBottle($conn, $customer_name, $bottle_type, $bottle_size, $quantity) {
  $available = getCustomerAvailableBottles($conn, $customer_name);
  
  // Check if bottle type exists in customer's deposits
  if (!isset($available[$bottle_type])) {
    return ['valid' => false, 'error' => "Customer did not deposit " . htmlspecialchars($bottle_type) . ". Available: " . (count($available) > 0 ? implode(', ', array_keys($available)) : 'none')];
  }
  
  // Check if requested quantity is available
  $available_qty = $available[$bottle_type]['available_qty'];
  if ($quantity > $available_qty) {
    return ['valid' => false, 'error' => "Only " . $available_qty . " of " . htmlspecialchars($bottle_type) . " available (" . $available[$bottle_type]['deposited_qty'] . " deposited, " . $available[$bottle_type]['returned_qty'] . " returned)"];
  }
  
  return ['valid' => true];
}

// Prepare customer list: only show customers with unreturned deposits
$deposit_customers = [];
$cR = $conn->query("SELECT DISTINCT customer_name FROM stock_log WHERE action_type='Deposit' AND customer_name IS NOT NULL AND customer_name != '' ORDER BY customer_name ASC");
if ($cR) {
  while ($r = $cR->fetch_assoc()) {
    $customer = $r['customer_name'];
    // Check if they have available bottles (not all returned)
    $available = getCustomerAvailableBottles($conn, $customer);
    if (count($available) > 0) {
      $deposit_customers[] = $customer;
    }
  }
}

// Handle creation (return or refund)
if ($_SERVER["REQUEST_METHOD"] == "POST" && !isset($_POST['edit_id'])) {
  $customer_name = trim($_POST['customer_name'] ?? '');
  $amount = floatval($_POST['amount'] ?? 0);
  
  if (empty($customer_name)) {
    $error = 'Customer name is required.';
  } else if ($amount <= 0) {
    $error = 'Refund amount is required and must be greater than 0.';
  } else {
    $success = true;
    
    // Check if we have multi-bottle returns data
    $return_rows = isset($_POST['return_bottles']) ? json_decode($_POST['return_bottles'], true) : [];
    
    if (!empty($return_rows)) {
      // Process each return - VALIDATE first
      foreach ($return_rows as $bottle) {
        $bottle_type = trim($bottle['bottle_type'] ?? '');
        $quantity = intval($bottle['quantity'] ?? 0);
        $bottle_size = trim($bottle['bottle_size'] ?? 'small');
        
        if (empty($bottle_type) || $quantity <= 0) {
          continue; // Skip empty entries
        }
        
        // VALIDATE: Check if customer can return this bottle type with this quantity
        $validation = validateReturnBottle($conn, $customer_name, $bottle_type, $bottle_size, $quantity);
        if (!$validation['valid']) {
          $error = $validation['error'];
          $success = false;
          break;
        }
        
        // Get price per bottle from bottle_types
        $price_query = $conn->prepare("SELECT price_per_bottle FROM bottle_types WHERE type_name = ?");
        $price_query->bind_param('s', $bottle_type);
        $price_query->execute();
        $price_result = $price_query->get_result();
        $price_row = $price_result->fetch_assoc();
        $price_per_bottle = $price_row ? floatval($price_row['price_per_bottle']) : 0;
        $return_amount = $quantity * $price_per_bottle;
        $price_query->close();
        
        $stmt = $conn->prepare("INSERT INTO returns (user_id, customer_name, bottle_type, quantity, bottle_size, return_date) VALUES (?, ?, ?, ?, ?, NOW())");
        $stmt->bind_param('issis', $user_id, $customer_name, $bottle_type, $quantity, $bottle_size);
        if ($stmt->execute()) {
          $details = "Return — " . $quantity . " x " . $bottle_type . " " . $bottle_size . " (₱" . number_format($price_per_bottle, 2) . ") | Return: ₱" . number_format($return_amount, 2, '.', ',');
          $log = $conn->prepare("INSERT INTO stock_log (user_id, action_type, customer_name, bottle_type, quantity, amount, details) VALUES (?, 'Return', ?, ?, ?, ?, ?)");
          $log->bind_param('issids', $user_id, $customer_name, $bottle_type, $quantity, $return_amount, $details);
          $log->execute();
          $log->close();
        } else {
          $error = 'Database error: ' . $stmt->error;
          $success = false;
          break;
        }
        $stmt->close();
      }
    }
    
    // Process REFUND (amount is now required)
    if ($amount > 0 && $success) {
      $stmt = $conn->prepare("INSERT INTO refund (user_id, customer_name, amount, refund_date) VALUES (?, ?, ?, NOW())");
      $stmt->bind_param('isd', $user_id, $customer_name, $amount);
      if ($stmt->execute()) {
        $details = "Refund — ₱" . number_format($amount, 2, '.', ',');
        $log = $conn->prepare("INSERT INTO stock_log (user_id, action_type, customer_name, amount, details) VALUES (?, 'Refund', ?, ?, ?)");
        $log->bind_param('isds', $user_id, $customer_name, $amount, $details);
        $log->execute();
        $log->close();
      } else {
        $error = 'Database error: ' . $stmt->error;
        $success = false;
      }
      $stmt->close();
    }
    
    if ($success && (!empty($return_rows) || $amount > 0)) {
      $msg = 'Transaction recorded!';
      header("refresh:1;url=index.php");
    } elseif (!empty($return_rows) || $amount > 0) {
      // Already have error from above
    }
  }
}

// Admin edit handling
if ($is_admin && isset($_GET['edit_id'])) {
  $edit_id = intval($_GET['edit_id']);
  $e = $conn->prepare("SELECT return_id, customer_name, bottle_type, quantity, with_case, case_quantity FROM returns WHERE return_id = ?");
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
  $new_size = trim($_POST['bottle_size'] ?? 'small');
  $new_qty = intval($_POST['quantity'] ?? 0);
  $new_with_case = isset($_POST['with_case']) ? 1 : 0;
  $new_case_quantity = isset($_POST['case_quantity']) ? intval($_POST['case_quantity']) : 0;
  if ($new_with_case && $new_case_quantity <= 0) {
    $new_case_quantity = 1;
  }

  if ($new_qty <= 0) {
    $error = 'Quantity must be greater than zero.';
  } else {
    $u = $conn->prepare("UPDATE returns SET customer_name=?, bottle_type=?, quantity=?, with_case=?, case_quantity=? WHERE return_id=?");
    $u->bind_param('ssiiiii', $new_customer, $new_type, $new_qty, $new_with_case, $new_case_quantity, $edit_id);
    if ($u->execute()) {
      $log = $conn->prepare("INSERT INTO stock_log (user_id, action_type, customer_name, bottle_type, quantity, with_case, case_quantity) VALUES (?, 'Return Correction', ?, ?, ?, ?, ?)");
      $log->bind_param('issiii', $user_id, $new_customer, $new_type, $new_qty, $new_with_case, $new_case_quantity);
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
  <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22%3E%3Ctext y=%2275%22 font-size=%2275%22 font-weight=%22bold%22 fill=%22%2326a69a%22%3EBB%3C/text%3E%3C/svg%3E" type="image/svg+xml">
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
    .notice { position:fixed; top:50%; left:50%; transform:translate(-50%, -50%); padding:20px 30px; background:#e9fbf1; border-left:4px solid #26a69a; border-radius:6px; color:#155724; font-weight:500; z-index:9999; box-shadow:0 4px 12px rgba(0,0,0,0.15); min-width:300px; text-align:center; margin-top:0 !important; }
    .error { position:fixed; top:50%; left:50%; transform:translate(-50%, -50%); padding:20px 30px; background:#ffecec; border-left:4px solid #ef5350; border-radius:6px; color:#c62828; font-weight:500; z-index:9999; box-shadow:0 4px 12px rgba(0,0,0,0.15); min-width:300px; text-align:center; margin-top:0 !important; }
    @keyframes slideDown { from { opacity:0; transform:translate(-50%, -50%) translateY(-20px); } to { opacity:1; transform:translate(-50%, -50%) translateY(0); } }
    @keyframes slideUp { from { opacity:1; transform:translate(-50%, -50%) translateY(0); } to { opacity:0; transform:translate(-50%, -50%) translateY(-20px); } }
    .toggle-sidebar { background:none; border:none; font-size:18px; cursor:pointer; color:#2d6a6a; font-weight:600; display:none; transition:0.3s; }
    @media (max-width:768px) { .toggle-sidebar { display:block; } }
    /* Shared field box style used for With Case and Number of Cases */
    .field-label { display:block; margin-bottom:6px; font-weight:600; color:#333; }
    .field-box { display:flex; gap:10px; align-items:center; height:40px; border:1px solid #ddd; border-radius:6px; padding:10px; background:#f9f9f9; width:100%; }
    .field-stack { display:flex; flex-direction:column; }
    /* bottle size toggle buttons */
    .size-toggle { display:flex; gap:8px; }
    .size-option { cursor:pointer; user-select:none; padding:0; border:2px solid #26a69a; border-radius:6px; transition:all .2s; font-weight:600; display:flex; align-items:center; }
    .size-option input { display:none; }
    .size-option span { padding:8px 16px; color:#26a69a; display:block; }
    .size-option input:checked ~ span { background:#26a69a; color:white; }
    .size-option:hover { background:rgba(38,166,154,.15); }
    .field-box input[type="number"] { width:60px; border:none; background:transparent; padding:6px; font-size:14px; text-align:center; }
  </style>
</head>
<body>

<script>
// ensure toggleSidebar is available early for onclick attributes
function toggleSidebar(){
  const sidebar = document.querySelector('.sidebar');
  const overlay = document.querySelector('.sidebar-overlay');
  if(sidebar) sidebar.classList.toggle('active');
  if(overlay) overlay.classList.toggle('active');
}

// Auto-hide notifications after 4.5 seconds
document.addEventListener('DOMContentLoaded', function() {
  const notification = document.getElementById('notification');
  const errorNotification = document.getElementById('error-notification');
  
  if(notification) {
    setTimeout(() => {
      notification.style.animation = 'slideUp 0.5s ease-in-out forwards';
    }, 4500);
  }
  
  if(errorNotification) {
    setTimeout(() => {
      errorNotification.style.animation = 'slideUp 0.5s ease-in-out forwards';
    }, 4500);
  }
});
</script>

<div class="sidebar-overlay"></div>

<div class="sidebar">
    <div class="brand">
        <h1>BB</h1>
    </div>
    <nav class="sidebar-nav">
        <a href="index.php">Dashboard</a>
        <a href="deposit.php">Deposit</a>
        <a href="returns.php">Returns</a>
        <a href="stock_log.php">Stock Log</a>
        <?php if($is_admin): ?>
          <a href="/admin/admin_panel.php#users-section" > Users</a>
        <?php endif; ?>
        <a href="logout.php" class="logout">Logout</a>
    </nav>
</div>

<div class="app">
  <div class="topbar">
  <div class="brand"><button class="toggle-sidebar">☰</button><div><h1>Return</h1><p class="kv">Record and manage bottle returns</p></div></div>
  <div class="menu-wrap"><a href="index.php" class="kv">← Back to Dashboard</a></div>
  </div>
  
  <?php if($msg): ?><div class="notice" id="notification" style="display:flex;justify-content:space-between;align-items:center;animation: slideDown 0.3s ease-in-out;"><?=$msg?><button onclick="document.getElementById('notification').style.display='none'" style="background:none;border:none;color:inherit;cursor:pointer;font-size:18px;padding:0;">✕</button></div><?php endif; ?>
  <?php if($error): ?><div class="error" id="error-notification" style="display:flex;justify-content:space-between;align-items:center;animation: slideDown 0.3s ease-in-out;"><?=htmlspecialchars($error)?><button onclick="document.getElementById('error-notification').style.display='none'" style="background:none;border:none;color:inherit;cursor:pointer;font-size:18px;padding:0;">✕</button></div><?php endif; ?>

  <div class="grid">
    <div class="panel" style="grid-column: span 12;">
      <h3>New Return</h3>

      <?php if($is_admin && isset($editRow)): ?>
        <h3>Edit Return #<?= $editRow['return_id'] ?></h3>
        <form method="POST" style="max-width:600px">
          <input type="hidden" name="edit_id" value="<?= $editRow['return_id'] ?>">
          <div class="form-row">
            <div class="col">
              <label>Customer Name</label>
              <select name="customer_name" id="customer_name_edit" required>
                <option value="">(none)</option>
                <?php foreach($deposit_customers as $c): ?>
                  <option value="<?= htmlspecialchars($c) ?>" <?= ($editRow['customer_name'] ?? '') === $c ? 'selected' : '' ?>><?= htmlspecialchars($c) ?></option>
                <?php endforeach; ?>
              </select>
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
              <label>Bottle Size</label>
              <?php $selSize = $editRow['bottle_size'] ?? 'small'; ?>
              <div class="size-toggle">
                <label class="size-option"><input type="radio" name="bottle_size" value="small" <?= $selSize !== '1l' ? 'checked' : '' ?>><span>8oz/12oz</span></label>
                <label class="size-option"><input type="radio" name="bottle_size" value="1l" <?= $selSize === '1l' ? 'checked' : '' ?>><span>1L</span></label>
              </div>
            </div>
          </div>
          <div class="form-row">
            <div class="col">
              <label>Quantity</label>
              <input type="number" name="quantity" value="<?=htmlspecialchars($editRow['quantity'])?>" min="1" required>
            </div>
          </div>
          <div class="form-row">
            <div class="col field-stack">
              <label class="field-label" for="with_case_edit">With Case?</label>
              <div class="field-box">
                <input type="checkbox" name="with_case" id="with_case_edit" style="width:18px;height:18px;cursor:pointer;margin:0;" <?= ($editRow['with_case'] ?? 0) ? 'checked' : '' ?>>
              </div>
            </div>
            <div class="col field-stack" id="caseQuantityColEdit" style="display:<?= ($editRow['with_case'] ?? 0) ? 'flex' : 'none' ?>;flex:1;">
              <label class="field-label">Number of Cases</label>
              <div class="field-box">
                <input type="number" name="case_quantity" id="case_quantity_edit" min="0" placeholder="0" value="<?=htmlspecialchars($editRow['case_quantity'] ?? 0)?>">
              </div>
            </div>
          </div>
          <div style="display:flex;gap:12px;margin-top:20px">
            <button type="submit" class="primary">Save Correction</button>
            <a href="returns.php"><button type="button" class="ghost">Cancel</button></a>
          </div>
        </form>
      <?php else: ?>
        <form method="POST" style="max-width:600px" onsubmit="return confirm('Record this transaction?')">
          <div class="form-row">
            <div class="col">
              <label>Select Customer</label>
              <select name="customer_name" id="customer_name">
                <option value="">(none)</option>
                <?php foreach($deposit_customers as $c): ?>
                  <option value="<?= htmlspecialchars($c) ?>"><?= htmlspecialchars($c) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
          <div class="form-row">
            <div class="col">
              <label>Amount to be Refunded (Required)</label>
              <input type="number" step="0.01" min="0" name="amount" id="refund_amount" placeholder="₱ 0.00" value="0" required>
            </div>
          </div>

          <h4 style="margin:20px 0 10px 0;color:#2d6a6a;">Available Bottles to Return</h4>
          <div style="border:1px solid #e0e0e0;border-radius:8px;padding:15px;background:#fafafa;margin-bottom:15px;" id="returnBottleList"></div>
          
          <div style="display:flex;gap:12px;margin-top:20px">
            <button type="submit" class="primary">Record</button>
            <a href="index.php"><button type="button" class="ghost">Cancel</button></a>
          </div>
        </form>

      <?php endif; ?>
    </div>
  </div>
</div>

<script>


// Toggle the submit button into the green notice when checkbox is checked
// (No-op) submit button stays in original place; notice UI was removed.

function toggleSidebar(){
  const sidebar = document.querySelector('.sidebar');
  const overlay = document.querySelector('.sidebar-overlay');
  sidebar.classList.toggle('active');
  overlay.classList.toggle('active');
}

// wire up controls after function defined
window.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('.toggle-sidebar').forEach(b => b.addEventListener('click', toggleSidebar));
  const ov = document.querySelector('.sidebar-overlay');
  if (ov) ov.addEventListener('click', toggleSidebar);

  // initialize controls that depend on DOM
  custInput = document.getElementById('customer_name');
  historyDiv = document.getElementById('customerHistory'); // may be null if table removed
  sizeRadios = document.querySelectorAll('input[name="bottle_size"]');
  quantityInput = document.querySelector('input[name="quantity"]');
  withCaseCheckbox = document.getElementById('with_case');
  caseQuantityInput = document.getElementById('case_quantity');

  if(custInput){
    custInput.addEventListener('input', function(){ fetchCustomerData(this.value); });
    custInput.addEventListener('change', function(){ fetchCustomerData(this.value); });
    custInput.addEventListener('blur', function(){
      console.log('Blur event fired, current value:', this.value);
      fetchCustomerData(this.value);
    });
  }

  const custEditInput = document.getElementById('customer_name_edit');
  if(custEditInput){
    console.log('Edit form customer select found');
    custEditInput.addEventListener('change', function(){
      console.log('Edit form customer changed:', this.value);
      fetchCustomerDataEdit(this.value);
    });
    const preselectedName = custEditInput.value;
    if(preselectedName){
      console.log('Edit form pre-loaded with customer:', preselectedName);
      setTimeout(() => {
        console.log('Triggering autofetch for preselected customer');
        fetchCustomerDataEdit(preselectedName);
      },300);
    }
  }

  // compute cases handlers only if elements exist
  if(quantityInput) quantityInput.addEventListener('input', computeCases);
  if(withCaseCheckbox) withCaseCheckbox.addEventListener('change', computeCases);
  if(sizeRadios && sizeRadios.length) sizeRadios.forEach(r=>r.addEventListener('change', computeCases));
  computeCases();
});


// when customer name selected, fetch latest deposit info via AJAX
let custInput, historyDiv, sizeRadios, quantityInput, withCaseCheckbox, caseQuantityInput;

function formatDateTime(dateStr) {
  const date = new Date(dateStr);
  const options = { month: 'short', day: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit', hour12: true };
  return date.toLocaleDateString('en-PH', options).replace(',', '').replace(/(\d+)(\s)/, '$1, ') + ' ' + date.toLocaleTimeString('en-US', {hour: '2-digit', minute: '2-digit', hour12: true});
}

function fetchCustomerData(name) {
  name = name.trim();
  console.log('Fetching data for customer:', name);
  if(name){
    const url = 'api/deposit.php?customer=' + encodeURIComponent(name);
    console.log('Fetching from:', url);
    fetch(url)
      .then(r => {
        console.log('Response status:', r.status);
        return r.json();
      })
      .then(resp => {
        console.log('API Response:', resp);
        // resp should be { deposits: [...], returns: [...] }
        let html = '';
        if(resp.deposits && resp.deposits.length){
          console.log('Found', resp.deposits.length, 'deposits');
          html += '<h4>Recent deposits for '+name+':</h4>' +
            '<table><tr><th>Date</th><th>Bottle</th><th>Qty</th><th>Cases</th><th>Amount</th></tr>' +
            resp.deposits.map(d => `<tr><td>${formatDateTime(d.date)}</td><td>${d.bottle_type || '-'}</td><td>${d.quantity || '-'}</td><td>${d.with_case?d.case_quantity:'-'}</td><td>${d.amount ? '₱'+parseFloat(d.amount).toLocaleString('en-PH', {minimumFractionDigits: 2, maximumFractionDigits: 2}) : '-'}</td></tr>`).join('') +
            '</table>';
            // auto-populate from latest deposit or canonical customer info
            const d0 = (resp.deposits && resp.deposits.length) ? resp.deposits[0] : resp.customer;
            console.log('Source for autofill (deposit or customer):', d0);
            if(d0){
              const bt = d0.bottle_type;
              const qty = d0.quantity;
              const amt = (typeof d0.amount !== 'undefined') ? d0.amount : 0;
              const withCaseVal = d0.with_case;
              const caseQtyVal = d0.case_quantity;
            
            console.log('Attempting to fill: amount=', amt, 'bottle=', bt, 'qty=', qty, 'withCase=', withCaseVal);
            
            // fill amount
            const amtInput = document.getElementById('refund_amount');
            console.log('Amount input found:', !!amtInput);
            if(amtInput) { amtInput.value = amt; console.log('Amount filled'); }
            
            // fill bottle type
            const select = document.querySelector('select[name="bottle_type"]');
            console.log('Select found:', !!select, 'Options:', select ? select.options.length : 0);
            if(select){
              for(let i = 0; i < select.options.length; i++){
                const opt = select.options[i];
                console.log('Option', i, ':', opt.value, 'comparing to', bt, '--match?', opt.value === bt);
                if(opt.value === bt){ opt.selected = true; console.log('Option selected'); break; }
              }
            }
            
            // fill quantity
            const qtyInput = document.querySelector('input[name="quantity"]');
            console.log('Quantity input found:', !!qtyInput);
            if(qtyInput) { qtyInput.value = qty; console.log('Quantity filled'); }
            
            // fill with_case and case_quantity
            if(withCaseVal){
              const wc = document.getElementById('with_case');
              const cas = document.getElementById('case_quantity');
              console.log('With case checkbox found:', !!wc, 'case qty found:', !!cas);
              if(wc) { wc.checked = true; wc.dispatchEvent(new Event('change')); console.log('With case checked'); }
              if(cas) { cas.value = caseQtyVal; console.log('Case quantity filled'); }
            } else {
              const wc = document.getElementById('with_case');
              if(wc) { wc.checked = false; wc.dispatchEvent(new Event('change')); console.log('With case unchecked'); }
            }
            // attempt to set bottle size radio
            const sizeVal = d0.bottle_size || '';
            if(sizeVal){
              const r = document.querySelector(`input[name="bottle_size"][value="${sizeVal}"]`);
              if(r) r.checked = true;
            } else if(resp.deposits && resp.deposits.length && resp.deposits[0].details){
              const m = resp.deposits[0].details.match(/\((1L|8\/12oz)\)$/);
              if(m){
                const parsed = m[1] === '1L' ? '1l' : 'small';
                const r2 = document.querySelector(`input[name="bottle_size"][value="${parsed}"]`);
                if(r2) r2.checked = true;
              }
            }
          }
        } else {
          console.log('No deposits found in response');
        }
        if(resp.returns && resp.returns.length){
          html += '<h4>Recent returns/refunds for '+name+':</h4>' +
            '<table><tr><th>Date</th><th>Bottle</th><th>Qty</th><th>Cases</th><th>Amount</th></tr>' +
            resp.returns.map(r => {
              if (r.amount && !r.bottle_type) {
                // refund row: no bottle/qty/cases
                return `<tr><td>${formatDateTime(r.date)}</td><td>-</td><td>-</td><td>-</td><td>₱${parseFloat(r.amount).toLocaleString('en-PH', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</td></tr>`;
              } else {
                return `<tr><td>${formatDateTime(r.date)}</td><td>${r.bottle_type || '-'}</td><td>${r.quantity || '-'}</td><td>${r.with_case? r.case_quantity : '-'}</td><td>-</td></tr>`;
              }
            }).join('') +
            '</table>';
        }
        if(historyDiv) historyDiv.innerHTML = html;
      }).catch(err => {
        console.error('Fetch error:', err);
      });
  } else {
    if(historyDiv) historyDiv.innerHTML = '';
  }
}

if(custInput){
  custInput.addEventListener('input', function(){ fetchCustomerData(this.value); });
  custInput.addEventListener('change', function(){ fetchCustomerData(this.value); });
  custInput.addEventListener('blur', function(){ 
    console.log('Blur event fired, current value:', this.value);
    fetchCustomerData(this.value); 
  });
}

// Also wire autofill for edit form customer select
const custEditInput = document.getElementById('customer_name_edit');
if(custEditInput){
  custEditInput.addEventListener('change', function(){
    console.log('Edit form customer changed:', this.value);
    fetchCustomerDataEdit(this.value);
  });
   // Auto-fetch if there's already a customer selected on page load
   const preselectedName = custEditInput.value;
   if(preselectedName) {
     console.log('Edit form pre-loaded with customer:', preselectedName);
     setTimeout(() => {
       console.log('Triggering autofetch for preselected customer');
       fetchCustomerDataEdit(preselectedName);
     }, 300);
   }
}

function fetchCustomerDataEdit(name) {
  name = name.trim();
  console.log('Fetching data for edit customer:', name);
  if(name){
    const url = 'api/deposit.php?customer=' + encodeURIComponent(name);
    fetch(url)
      .then(r => r.json())
      .then(resp => {
        if(!resp.deposits || !resp.deposits.length) { console.log('No deposits for', name); return; }
        const d0 = resp.deposits[0];
        console.log('Edit autofill source:', d0);
        
        // Get the edit form by finding the one with edit_id hidden input
        const editForm = document.querySelector('input[name="edit_id"]')?.closest('form');
        if(!editForm) { console.log('Edit form not found'); return; }
        
        // Fill bottle type
        const selectBt = editForm.querySelector('select[name="bottle_type"]');
        if(selectBt && d0.bottle_type){
          for(let i = 0; i < selectBt.options.length; i++){
            if(selectBt.options[i].value === d0.bottle_type){ 
              selectBt.options[i].selected = true; 
              console.log('Edit: bottle type set to', d0.bottle_type);
              break; 
            }
          }
        }
        
        // Fill quantity
        const qtyEdit = editForm.querySelector('input[name="quantity"]');
        if(qtyEdit && d0.quantity) { 
          qtyEdit.value = d0.quantity; 
          console.log('Edit: quantity set to', d0.quantity);
        }
        
        // Fill with_case and case_quantity
        const wcEdit = editForm.querySelector('input[name="with_case"]');
        const casEdit = editForm.querySelector('input[name="case_quantity"]');
        const casColEdit = editForm.querySelector('div[id="caseQuantityColEdit"]');
        
        if(wcEdit && casEdit){
          if(d0.with_case){
            wcEdit.checked = true;
            console.log('Edit: with_case checked=true');
            wcEdit.dispatchEvent(new Event('change'));
            casEdit.value = d0.case_quantity || 1;
            if(casColEdit) casColEdit.style.display = 'flex';
          } else {
            wcEdit.checked = false;
            console.log('Edit: with_case checked=false');
            wcEdit.dispatchEvent(new Event('change'));
            casEdit.value = '0';
            if(casColEdit) casColEdit.style.display = 'none';
          }
        }
        
        // Fill bottle size
        const sizeVal = d0.bottle_size || '';
        if(sizeVal){
          const r = editForm.querySelector(`input[name="bottle_size"][value="${sizeVal}"]`);
          if(r) { 
            r.checked = true; 
            console.log('Edit: bottle size set to', sizeVal);
          }
        }
      }).catch(err => console.error('Fetch error:', err));
  }
}

function computeCases(){
  if(withCaseCheckbox){
    if(withCaseCheckbox.checked){
      if(caseQuantityCol) caseQuantityCol.style.display = 'flex';
      if(caseQuantityInput) caseQuantityInput.required = true;
    } else {
      if(caseQuantityCol) caseQuantityCol.style.display = 'none';
      if(caseQuantityInput){ caseQuantityInput.required = false; caseQuantityInput.value = '0'; }
    }
  }
  if(withCaseCheckbox && withCaseCheckbox.checked && quantityInput){
    const qty = parseInt(quantityInput.value) || 0;
    let size = 'small';
    const sel = document.querySelector('input[name="bottle_size"]:checked');
    if(sel) size = sel.value;
    const factor = size === '1l' ? 12 : 24;
    caseQuantityInput.value = Math.floor(qty / factor);
  }
}



// auto-toggle bottle/quantity reqs based on amount
const amtInput = document.getElementById('refund_amount') || document.getElementById('amount');
if(amtInput){
  amtInput.addEventListener('input', function(){
    const val = parseFloat(this.value) || 0;
    const isRefund = val > 0;
    const bt = document.querySelector('select[name="bottle_type"]');
    const qty = document.querySelector('input[name="quantity"]');
    if(bt){ bt.required = !isRefund; bt.disabled = isRefund; }
    if(qty){ qty.required = !isRefund; qty.disabled = isRefund; }
  });
}

// Toggle the submit button into the green notice when checkbox is checked
// (No-op) submit button stays in original place; notice UI was removed.

// Handle "With Case?" checkbox toggle for EDIT form
const withCaseCheckboxEdit = document.getElementById('with_case_edit');
const caseQuantityColEdit = document.getElementById('caseQuantityColEdit');
const caseQuantityInputEdit = document.getElementById('case_quantity_edit');

if (withCaseCheckboxEdit) {
  console.log('EDIT FORM: with_case_edit checkbox found, attaching listeners');
  withCaseCheckboxEdit.addEventListener('change', function() {
    console.log('EDIT FORM: with_case changed, checked =', this.checked);
    if (this.checked) {
      if(caseQuantityColEdit) { caseQuantityColEdit.style.display = 'flex'; console.log('Showing case quantity col in edit'); }
      if(caseQuantityInputEdit) { caseQuantityInputEdit.required = true; caseQuantityInputEdit.focus(); console.log('Case qty input focused'); }
    } else {
      if(caseQuantityColEdit) { caseQuantityColEdit.style.display = 'none'; console.log('Hiding case quantity col in edit'); }
      if(caseQuantityInputEdit) { caseQuantityInputEdit.value = '0'; caseQuantityInputEdit.required = false; }
    }
  });
} else {
  console.log('EDIT FORM: with_case_edit checkbox NOT found');
}





document.querySelectorAll('.sidebar-nav a').forEach(link => {
  link.addEventListener('click', function(){
    if(window.innerWidth <= 768){
      toggleSidebar();
    }
  });
});

// Multi-bottle return functionality
let returnBottleCount = 0;
let availableBottlesData = {};

function formatPrice(num) {
  return num.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
}

function addReturnBottleRow() {
  const container = document.getElementById('returnBottleList');
  const rowId = 'return_bottle_' + (++returnBottleCount);
  
  const bottleOptions = Object.entries(availableBottlesData).map(([key, bottle]) => 
    `<option value="${bottle.bottle_type}|${bottle.bottle_size}|${bottle.available_qty}">${bottle.bottle_type} ${bottle.bottle_size} (${bottle.available_qty} available)</option>`
  ).join('');
  
  const html = `
    <div class="return-bottle-row" id="${rowId}" style="display:flex;gap:10px;align-items:flex-end;margin-bottom:12px;padding:12px;background:white;border-radius:6px;border:1px solid #eee;">
      <select onchange="updateReturnBottleInfo(this)" style="flex:1.2;padding:8px;border:1px solid #ddd;border-radius:4px;font-size:13px;">
        <option value="">Select a bottle to return</option>
        ${bottleOptions}
      </select>
      <input type="number" min="1" placeholder="Qty" class="return-quantity-input" style="width:70px;padding:8px;border:1px solid #ddd;border-radius:4px;font-size:13px;" onchange="updateReturnTotal()">
      <div class="available-label" style="width:100px;color:#00796b;font-weight:600;padding:8px;border-radius:4px;text-align:right;">-</div>
      <button type="button" onclick="removeReturnBottleRow('${rowId}')" style="padding:8px 12px;background:#ef5350;color:white;border:none;border-radius:4px;cursor:pointer;font-size:12px;font-weight:600;">Remove</button>
    </div>
  `;
  container.insertAdjacentHTML('beforeend', html);
}

function addReturnBottleRowWithData(bottle) {
  const container = document.getElementById('returnBottleList');
  const rowId = 'return_bottle_' + (++returnBottleCount);
  
  const html = `
    <div class="return-bottle-row" id="${rowId}" style="display:flex;gap:10px;align-items:flex-end;margin-bottom:12px;padding:12px;background:white;border-radius:6px;border:1px solid #eee;">
      <select onchange="updateReturnBottleInfo(this)" style="flex:1.2;padding:8px;border:1px solid #ddd;border-radius:4px;font-size:13px;">
        <option value="${bottle.bottle_type}|${bottle.bottle_size}|${bottle.available_qty}" selected>${bottle.bottle_type} ${bottle.bottle_size} (${bottle.available_qty} available)</option>
      </select>
      <input type="number" min="1" max="${bottle.available_qty}" placeholder="Qty" class="return-quantity-input" style="width:70px;padding:8px;border:1px solid #ddd;border-radius:4px;font-size:13px;" onchange="updateReturnTotal()">
      <div class="available-label" style="width:100px;color:#00796b;font-weight:600;padding:8px;border-radius:4px;text-align:right;">${bottle.available_qty} available</div>
      <button type="button" onclick="removeReturnBottleRow('${rowId}')" style="padding:8px 12px;background:#ef5350;color:white;border:none;border-radius:4px;cursor:pointer;font-size:12px;font-weight:600;">Remove</button>
    </div>
  `;
  const rowElement = document.createElement('div');
  rowElement.innerHTML = html;
  const newRow = rowElement.firstElementChild;
  const row = newRow;
  row.dataset.bottleType = bottle.bottle_type;
  row.dataset.bottleSize = bottle.bottle_size;
  row.dataset.available = bottle.available_qty;
  container.appendChild(newRow);
}

function updateReturnBottleInfo(select) {
  const row = select.closest('.return-bottle-row');
  const [bottle_type, bottle_size, available] = select.value.split('|');
  row.dataset.bottleType = bottle_type;
  row.dataset.bottleSize = bottle_size;
  row.dataset.available = parseInt(available);
  row.querySelector('.available-label').textContent = available + ' available';
}

function updateReturnTotal() {
  let totalBottles = 0;
  const returnList = document.getElementById('returnBottleList');
  
  returnList.querySelectorAll('.return-bottle-row').forEach(row => {
    const qty = parseInt(row.querySelector('.return-quantity-input').value) || 0;
    totalBottles += qty;
  });
}

function removeReturnBottleRow(rowId) {
  document.getElementById(rowId).remove();
  updateReturnTotal();
}

// When customer is selected, fetch available bottles and auto-populate rows
window.addEventListener('DOMContentLoaded', function() {
  const custSelect = document.getElementById('customer_name');
  if (custSelect) {
    custSelect.addEventListener('change', function() {
      const customerName = this.value;
      const containerDiv = document.getElementById('returnBottleList');
      
      // Clear existing rows
      containerDiv.innerHTML = '';
      
      if (customerName) {
        fetch('api/returns.php?customer=' + encodeURIComponent(customerName))
          .then(r => r.json())
          .then(data => {
            availableBottlesData = {};
            // Auto-populate amount field with remaining refund
            const amountField = document.getElementById('refund_amount');
            if (data.remaining_refund_amount) {
              amountField.value = data.remaining_refund_amount.toFixed(2);
            }
            
            if (data.available_bottles && data.available_bottles.length > 0) {
              data.available_bottles.forEach(bottle => {
                const key = bottle.bottle_type + '_' + bottle.bottle_size;
                availableBottlesData[key] = bottle;
                // Automatically add a row for each available bottle
                addReturnBottleRowWithData(bottle);
              });
              containerDiv.innerHTML = '<div style="padding:10px;background:#fff9e6;border:1px solid #ffe0b2;border-radius:4px;color:#e65100;font-size:13px;margin-bottom:15px;"><strong>✓ Bottles available to return (rows auto-populated below):</strong><br>' + data.available_bottles.map(b => `${b.bottle_type} ${b.bottle_size}: ${b.available_qty}/${b.deposited_qty}`).join(', ') + '<br><strong>Refund Amount:</strong> ₱' + (data.remaining_refund_amount || 0).toFixed(2) + '</div>' + containerDiv.innerHTML;
            } else {
              containerDiv.innerHTML = '<div style="padding:10px;background:#ffebee;border:1px solid #ef5350;border-radius:4px;color:#c62828;font-size:13px;">No bottles available to return for this customer</div>';
              availableBottlesData = {};
              amountField.value = '0.00';
            }
          })
          .catch(err => console.error('Error fetching available bottles:', err));
      } else {
        containerDiv.innerHTML = '';
        availableBottlesData = {};
      }
    });
  }
});

// Multi-bottle returns form submission
window.addEventListener('DOMContentLoaded', function() {
  const returnForms = document.querySelectorAll('form[method="POST"]');
  returnForms.forEach(returnForm => {
    // Check if this is the return form (not edit form) by looking for returnBottleList
    if (document.getElementById('returnBottleList') && !returnForm.querySelector('input[name="edit_id"]')) {
      returnForm.addEventListener('submit', function(e) {
        const returnList = document.getElementById('returnBottleList');
        const returnBottles = [];
        
        returnList.querySelectorAll('.return-bottle-row').forEach(row => {
          const qty = parseInt(row.querySelector('.return-quantity-input').value) || 0;
          if (row.dataset.bottleType && qty > 0) {
            returnBottles.push({
              bottle_type: row.dataset.bottleType,
              bottle_size: row.dataset.bottleSize,
              quantity: qty
            });
          }
        });
        
        const amount = parseFloat(document.getElementById('refund_amount').value) || 0;
        
        if (returnBottles.length > 0 || amount > 0) {
          e.preventDefault();
          // Create hidden input for multi-bottle returns
          if (returnBottles.length > 0) {
            let input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'return_bottles';
            input.value = JSON.stringify(returnBottles);
            this.appendChild(input);
          }
          this.submit();
        }
      });
    }
  });
});
</script>

</body>
</html>
