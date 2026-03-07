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

// prepare distinct customer list from deposits for datalist/autofill
$deposit_customers = [];
$cR = $conn->query("SELECT DISTINCT customer_name FROM deposit WHERE customer_name IS NOT NULL AND customer_name != '' ORDER BY customer_name ASC");
if ($cR) {
  while ($r = $cR->fetch_assoc()) $deposit_customers[] = $r['customer_name'];
}

// Handle creation (return or refund)
if ($_SERVER["REQUEST_METHOD"] == "POST" && !isset($_POST['edit_id'])) {
  $customer_name = trim($_POST['customer_name'] ?? '');
  $amount = floatval($_POST['amount'] ?? 0);

  if ($amount > 0) {
    // process refund; bottle/quantity fields ignored
    if (empty($customer_name)) {
      $error = 'Customer name is required for refund.';
    } else {
      $stmt = $conn->prepare("INSERT INTO refund (user_id, customer_name, amount, refund_date) VALUES (?, ?, ?, NOW())");
      $stmt->bind_param('isd', $user_id, $customer_name, $amount);
      if ($stmt->execute()) {
        $details = "Refund — ₱" . number_format($amount, 2);
        $log = $conn->prepare("INSERT INTO stock_log (user_id, action_type, customer_name, amount, details) VALUES (?, 'Refund', ?, ?, ?)");
        $log->bind_param('isds', $user_id, $customer_name, $amount, $details);
        $log->execute(); $log->close();
        $msg = 'Refund recorded!';
        header("refresh:1;url=index.php");
      } else {
        $error = 'Database error: ' . $stmt->error;
      }
      $stmt->close();
    }
  } else {
    // process return
    $bottle_type = trim($_POST['bottle_type'] ?? '');
    $quantity = intval($_POST['quantity'] ?? 0);
    $bottle_size = trim($_POST['bottle_size'] ?? 'small');

    if ($quantity <= 0) {
      $error = 'Please enter a valid quantity greater than zero.';
    } elseif (!$bottle_type) {
      $error = 'Please select a bottle type.';
    } else {
      $with_case = isset($_POST['with_case']) ? 1 : 0;
      $case_quantity = isset($_POST['case_quantity']) ? intval($_POST['case_quantity']) : 0;
      if ($with_case && $case_quantity <= 0) {
        $case_quantity = 1;
      }
      $stmt = $conn->prepare("INSERT INTO returns (user_id, customer_name, bottle_type, quantity, with_case, case_quantity, bottle_size, return_date) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
      $stmt->bind_param('issiiis', $user_id, $customer_name, $bottle_type, $quantity, $with_case, $case_quantity, $bottle_size);
      if ($stmt->execute()) {
        $sizeLabel = $bottle_size === '1l' ? '1L' : '8/12oz';
        $details = "Return — " . $quantity . " bottles (" . $bottle_type . ", " . $sizeLabel . ")";
        $log = $conn->prepare("INSERT INTO stock_log (user_id, action_type, customer_name, bottle_type, quantity, details, with_case, case_quantity) VALUES (?, 'Return', ?, ?, ?, ?, ?, ?)");
        $log->bind_param('isssiii', $user_id, $customer_name, $bottle_type, $quantity, $details, $with_case, $case_quantity);
        $log->execute(); $log->close();
        // automatically remove one matching deposit log entry for this customer/bottle/quantity
        if($customer_name && $bottle_type && $quantity > 0) {
          $del = $conn->prepare("DELETE FROM stock_log WHERE action_type='Deposit' AND customer_name=? AND bottle_type=? AND quantity=? LIMIT 1");
          if($del) {
            $del->bind_param('ssi', $customer_name, $bottle_type, $quantity);
            $del->execute();
            $del->close();
          }
        }
        $msg = 'Return recorded!';
        header("refresh:1;url=index.php");
      } else {
        $error = 'Database error: ' . $stmt->error;
      }
      $stmt->close();
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
  <div class="brand"><button class="toggle-sidebar">Menu</button><div><h1>Return</h1><p class="kv">Record and manage bottle returns</p></div></div>
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
              <label>Amount (₱)</label>
              <input type="number" step="0.01" min="0" name="amount" id="refund_amount" placeholder="₱ 0.00" value="0">
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
              <label>Bottle Size</label>
              <div class="size-toggle">
                <label class="size-option"><input type="radio" name="bottle_size" value="small" checked><span>8oz/12oz</span></label>
                <label class="size-option"><input type="radio" name="bottle_size" value="1l"><span>1L</span></label>
              </div>
            </div>
          </div>
          <div class="form-row">
            <div class="col">
              <label>Quantity</label>
              <input type="number" name="quantity" min="1" placeholder="Number of bottles" required>
            </div>
          </div>
          <div class="form-row">
            <div class="col field-stack" style="flex:0.5;">
              <label class="field-label" for="with_case">With Case?</label>
              <div class="field-box">
                <input type="checkbox" name="with_case" id="with_case" style="width:18px;height:18px;cursor:pointer;margin:0;">
              </div>
            </div>
            <div class="col field-stack" style="flex:0.5; display:none;" id="caseQuantityCol">
              <label class="field-label">Number of Cases</label>
              <div class="field-box">
                <input type="number" name="case_quantity" id="case_quantity" min="0" placeholder="0" value="0">
              </div>
            </div>
          </div>
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
  } else {
    console.log('Edit form customer select NOT found');
  }

  // compute cases handlers
  if(quantityInput) quantityInput.addEventListener('input', computeCases);
  if(withCaseCheckbox) withCaseCheckbox.addEventListener('change', computeCases);
  if(sizeRadios) sizeRadios.forEach(r=>r.addEventListener('change', computeCases));
  computeCases();
});


// when customer name selected, fetch latest deposit info via AJAX
let custInput, historyDiv, sizeRadios, quantityInput, withCaseCheckbox, caseQuantityInput;

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
            resp.deposits.map(d => `<tr><td>${d.date}</td><td>${d.bottle_type || '-'}</td><td>${d.quantity || '-'}</td><td>${d.with_case?d.case_quantity:'-'}</td><td>${d.amount ? '₱'+d.amount : '-'}</td></tr>`).join('') +
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
                return `<tr><td>${r.date}</td><td>-</td><td>-</td><td>-</td><td>₱${parseFloat(r.amount).toFixed(2)}</td></tr>`;
              } else {
                return `<tr><td>${r.date}</td><td>${r.bottle_type || '-'}</td><td>${r.quantity || '-'}</td><td>${r.with_case? r.case_quantity : '-'}</td><td>-</td></tr>`;
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
</script>
</body>
</html>
