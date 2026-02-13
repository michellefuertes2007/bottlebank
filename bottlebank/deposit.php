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

// Handle editing bottle type
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_bottle_type'])) {
  $type_id = intval($_POST['type_id'] ?? 0);
  $edit_name = trim($_POST['edit_bottle_name'] ?? '');
  
  if (!$type_id) {
    $error = 'Invalid bottle type.';
  } elseif (!$edit_name) {
    $error = 'Please enter a bottle type name.';
  } elseif (strlen($edit_name) < 3) {
    $error = 'Bottle type name must be at least 3 characters long.';
  } elseif (strlen($edit_name) > 100) {
    $error = 'Bottle type name cannot exceed 100 characters.';
  } else {
    $stmt = $conn->prepare("UPDATE bottle_types SET type_name = ? WHERE type_id = ?");
    $stmt->bind_param("si", $edit_name, $type_id);
    
    try {
      if ($stmt->execute()) {
        $msg = "✓ Bottle type '" . htmlspecialchars($edit_name) . "' updated successfully!";
        // Refresh the bottle types list
        $bottle_types_list = [];
        $btResult = $conn->query("SELECT type_id, type_name FROM bottle_types ORDER BY type_name ASC");
        if ($btResult) {
          while ($row = $btResult->fetch_assoc()) {
            $bottle_types_list[] = $row;
          }
        }
      }
    } catch (mysqli_sql_exception $e) {
      if (strpos($e->getMessage(), 'Duplicate') !== false || strpos($e->getMessage(), 'UNIQUE') !== false) {
        $error = "Bottle type '" . htmlspecialchars($edit_name) . "' already exists. Please use a different name.";
      } else {
        $error = "Error updating bottle type: " . htmlspecialchars($e->getMessage());
      }
    }
    $stmt->close();
  }
}

// Handle deleting bottle type
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_bottle_type'])) {
  $type_id = intval($_POST['type_id'] ?? 0);
  
  if (!$type_id) {
    $error = 'Invalid bottle type.';
  } else {
    // Check if bottle type is in use
    $check = $conn->prepare("SELECT COUNT(*) as cnt FROM deposit WHERE bottle_type = (SELECT type_name FROM bottle_types WHERE type_id = ?)");
    $check->bind_param("i", $type_id);
    $check->execute();
    $result = $check->get_result()->fetch_assoc();
    $check->close();
    
    if ($result['cnt'] > 0) {
      $error = "Cannot delete this bottle type because it has " . $result['cnt'] . " deposit(s) in use.";
    } else {
      $stmt = $conn->prepare("DELETE FROM bottle_types WHERE type_id = ?");
      $stmt->bind_param("i", $type_id);
      if ($stmt->execute()) {
        $msg = "✓ Bottle type deleted successfully!";
        // Refresh the bottle types list
        $bottle_types_list = [];
        $btResult = $conn->query("SELECT type_id, type_name FROM bottle_types ORDER BY type_name ASC");
        if ($btResult) {
          while ($row = $btResult->fetch_assoc()) {
            $bottle_types_list[] = $row;
          }
        }
      } else {
        $error = "Error deleting bottle type: " . htmlspecialchars($stmt->error);
      }
      $stmt->close();
    }
  }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_bottle_type'])) {
  $new_type = trim($_POST['new_bottle_type'] ?? '');
  
  // Validation checks
  if (!$new_type) {
    $error = 'Please enter a bottle type name.';
  } elseif (strlen($new_type) < 3) {
    $error = 'Bottle type name must be at least 3 characters long.';
  } elseif (strlen($new_type) > 100) {
    $error = 'Bottle type name cannot exceed 100 characters.';
  } else {
    $stmt = $conn->prepare("INSERT INTO bottle_types (type_name, created_by) VALUES (?, ?)");
    $stmt->bind_param("si", $new_type, $user_id);
    
    try {
      if ($stmt->execute()) {
        // Refresh the bottle types list
        $bottle_types_list = [];
        $btResult = $conn->query("SELECT type_id, type_name FROM bottle_types ORDER BY type_name ASC");
        if ($btResult) {
          while ($row = $btResult->fetch_assoc()) {
            $bottle_types_list[] = $row;
          }
        }
        $msg = "✓ Bottle type '" . htmlspecialchars($new_type) . "' added successfully!";
      }
    } catch (mysqli_sql_exception $e) {
      // Check if error is due to duplicate
      if (strpos($e->getMessage(), 'Duplicate') !== false || strpos($e->getMessage(), 'UNIQUE') !== false) {
        $error = "Bottle type '" . htmlspecialchars($new_type) . "' already exists. Please use a different name or select it from the dropdown.";
      } else {
        $error = "Error adding bottle type: " . htmlspecialchars($e->getMessage());
      }
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
    // Get case information from form
    $with_case = isset($_POST['with_case']) ? 1 : 0;
    $case_quantity = isset($_POST['case_quantity']) ? intval($_POST['case_quantity']) : 0;
    // If user indicated there is a case but left quantity empty or zero, default to 1
    if ($with_case && $case_quantity <= 0) {
      $case_quantity = 1;
    }
    
    // insert single deposit
    $ins = $conn->prepare("INSERT INTO deposit (user_id, customer_name, bottle_type, quantity, with_case, case_quantity, deposit_date) VALUES (?, ?, ?, ?, ?, ?, NOW())");
    $ins->bind_param("issiii", $user_id, $customer_name, $bottle_type, $quantity, $with_case, $case_quantity);
    if ($ins->execute()) {
      // insert stock_log with formatted details including case info
      $details = "Deposit — " . $quantity . " bottles (" . $bottle_type . ")";
      if ($with_case && $case_quantity > 0) {
        $details .= " with " . $case_quantity . " case" . ($case_quantity > 1 ? "s" : "");
      }
      $log = $conn->prepare("INSERT INTO stock_log (user_id, action_type, customer_name, bottle_type, quantity, amount, details, with_case, case_quantity) VALUES (?, 'Deposit', ?, ?, ?, ?, ?, ?, ?)");
      $log->bind_param("issidsii", $user_id, $customer_name, $bottle_type, $quantity, $amount, $details, $with_case, $case_quantity);
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
    <div class="panel" style="grid-column: span 12;">
      <h3 style="margin-top:0">New Deposit</h3>
      <?php if($msg): ?><div class="notice"><?=$msg?></div><?php endif; ?>
      <?php if($error): ?><div class="error"><?=htmlspecialchars($error)?></div><?php endif; ?>

      <?php if($is_admin && isset($editRow)): ?>
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
      <?php else: ?>
      <form method="post" style="max-width:760px" id="depositForm">
        <div class="bottle-entry" style="border:1px solid #e0e0e0;padding:15px;border-radius:6px;margin-bottom:15px;background:#fafafa;">
          <div class="form-row">
            <div class="col">
              <label>Customer Name</label>
              <input type="text" name="customer_name" required>
            </div>
          </div>
          
          <div class="form-row">
            <div class="col">
              <label>Bottle Type</label>
              <select name="bottle_type" required>
                <option value="">Select a bottle type</option>
                <?php foreach ($bottle_types_list as $type): ?>
                  <option value="<?= htmlspecialchars($type['type_name']) ?>"><?= htmlspecialchars($type['type_name']) ?></option>
                <?php endforeach; ?>
              </select>
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
            <div class="col">
              <label>With Case?</label>
              <div style="display:flex;gap:10px;align-items:center;height:40px;border:1px solid #ddd;border-radius:6px;padding:10px;background:#f9f9f9;">
                <input type="checkbox" name="with_case" id="with_case" style="width:18px;height:18px;cursor:pointer;margin:0;">
                <label for="with_case" style="margin:0;cursor:pointer;font-weight:500;flex:1;">Include case with deposit</label>
              </div>
            </div>
          </div>

          <div class="form-row" id="caseQuantityRow" style="display:none;">
            <div class="col">
              <label>Number of Cases</label>
              <input type="number" name="case_quantity" id="case_quantity" min="0" placeholder="0" value="0">
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
        
        <!-- Show existing types -->
        <div style="margin-bottom:15px;padding:10px;background:white;border-radius:6px;border:1px solid #ddd;">
          <p style="color:#2d6a6a;font-weight:600;font-size:12px;margin:0 0 8px 0;">Existing Types:</p>
          <div style="display:flex;flex-wrap:wrap;gap:6px;">
            <?php foreach ($bottle_types_list as $type): ?>
              <div style="background:#26a69a;color:white;padding:5px 10px;border-radius:4px;font-size:12px;font-weight:500;display:flex;gap:6px;align-items:center;">
                <span><?= htmlspecialchars($type['type_name']) ?></span>
                <button type="button" class="edit-btn" onclick="editBottleType(<?= $type['type_id'] ?>, '<?= htmlspecialchars(addslashes($type['type_name'])) ?>')" style="background:none;border:none;color:white;cursor:pointer;padding:0;font-size:12px;opacity:0.8;" title="Edit">✎</button>
                <button type="button" class="delete-btn" onclick="deleteBottleType(<?= $type['type_id'] ?>, '<?= htmlspecialchars(addslashes($type['type_name'])) ?>')" style="background:none;border:none;color:white;cursor:pointer;padding:0;font-size:12px;opacity:0.8;" title="Delete">✕</button>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
        
        <form method="post" style="display:flex;gap:10px;flex-wrap:wrap;" id="addBottleTypeForm">
          <input 
            type="text" 
            name="new_bottle_type" 
            id="bottleTypeInput"
            placeholder="Enter new bottle type (e.g., Glass Jug, Carton)" 
            minlength="3"
            maxlength="100"
            style="flex:1;min-width:200px;padding:10px;border:2px solid #26a69a;border-radius:6px;font-family:'Poppins',sans-serif;font-size:14px;transition:all 0.3s ease;"
            title="Bottle type name must be 3-100 characters. Example: Glass Jug, Carton Box, Aluminum Can">
          <button type="submit" name="add_bottle_type" class="primary" style="white-space:nowrap;padding:10px 15px;" onclick="return confirmAddBottleType()">Add Type</button>
        </form>
        
        <div id="similarWarning" style="margin-top:8px;padding:8px;background:#fff3cd;border-left:3px solid #ffc107;color:#856404;border-radius:4px;display:none;font-size:12px;"></div>
        
        <p style="color:#666;font-size:11px;margin-top:8px;">
          <strong>Requirements:</strong> 3-100 characters, no duplicates allowed
        </p>
      </div>


      <script>
      // Handle "With Case?" checkbox toggle
      const withCaseCheckbox = document.getElementById('with_case');
      const caseQuantityRow = document.getElementById('caseQuantityRow');
      const caseQuantityInput = document.getElementById('case_quantity');

      if (withCaseCheckbox) {
        // Show/hide case quantity input based on checkbox and set sensible defaults
        withCaseCheckbox.addEventListener('change', function() {
          if (this.checked) {
            caseQuantityRow.style.display = 'flex';
            caseQuantityInput.value = '1';
            caseQuantityInput.required = true;
            caseQuantityInput.focus();
          } else {
            caseQuantityRow.style.display = 'none';
            caseQuantityInput.value = '0';
            caseQuantityInput.required = false;
          }
        });
      }

      // Existing bottle types from PHP
      const existingTypes = [
        <?php foreach ($bottle_types_list as $type) { 
          echo "'" . addslashes($type['type_name']) . "',"; 
        } ?>
      ];

      // Edit bottle type
      function editBottleType(typeId, typeName) {
        const newName = prompt('Edit bottle type name:', typeName);
        if (newName !== null && newName.trim()) {
          const form = document.createElement('form');
          form.method = 'POST';
          form.innerHTML = `
            <input type="hidden" name="type_id" value="${typeId}">
            <input type="hidden" name="edit_bottle_name" value="${newName.trim()}">
            <input type="hidden" name="edit_bottle_type" value="1">
          `;
          document.body.appendChild(form);
          form.submit();
          document.body.removeChild(form);
        }
      }

      // Delete bottle type
      function deleteBottleType(typeId, typeName) {
        if (confirm('Delete bottle type "' + typeName + '"?\n\nThis action cannot be undone.')) {
          const form = document.createElement('form');
          form.method = 'POST';
          form.innerHTML = `
            <input type="hidden" name="type_id" value="${typeId}">
            <input type="hidden" name="delete_bottle_type" value="1">
          `;
          document.body.appendChild(form);
          form.submit();
          document.body.removeChild(form);
        }
      }

      // Calculate similarity score between two strings
      function calculateSimilarity(str1, str2) {
        str1 = str1.toLowerCase().trim();
        str2 = str2.toLowerCase().trim();
        
        if (str1 === str2) return 100;
        
        let matches = 0;
        let maxLen = Math.max(str1.length, str2.length);
        
        for (let i = 0; i < maxLen; i++) {
          if (str1[i] === str2[i]) matches++;
        }
        
        return (matches / maxLen) * 100;
      }

      // Check for similar types as user types
      document.getElementById('bottleTypeInput').addEventListener('input', function() {
        const input = this.value.trim();
        const warningDiv = document.getElementById('similarWarning');
        
        if (!input) {
          warningDiv.style.display = 'none';
          return;
        }
        
        // Find similar types
        let similar = [];
        existingTypes.forEach(type => {
          const similarity = calculateSimilarity(input, type);
          if (similarity >= 70 && similarity < 100) {
            similar.push({ name: type, score: similarity });
          }
        });
        
        // Show warning if similar types found
        if (similar.length > 0) {
          similar.sort((a, b) => b.score - a.score);
          let message = '⚠️ Similar type(s) found: <strong>' + similar.map(s => s.name).join(', ') + '</strong><br>Did you mean one of these? Check the list above.';
          warningDiv.innerHTML = message;
          warningDiv.style.display = 'block';
        } else {
          warningDiv.style.display = 'none';
        }
      });

      // Confirm before adding
      function confirmAddBottleType() {
        const input = document.getElementById('bottleTypeInput').value.trim();
        
        if (!input) {
          alert('Please enter a bottle type name.');
          return false;
        }
        
        if (input.length < 3) {
          alert('Bottle type must be at least 3 characters.');
          return false;
        }
        
        // Check for exact duplicate
        if (existingTypes.some(type => type.toLowerCase() === input.toLowerCase())) {
          alert('This bottle type already exists!\n\nPlease check the list above or use a different name.');
          return false;
        }
        
        // Confirm addition
        return confirm('Add new bottle type: "' + input + '"?\n\nThis will be available for all deposits.');
      }

      function toggleSidebar(){
        document.querySelector('.sidebar').classList.toggle('show');
        document.querySelector('.sidebar-overlay').classList.toggle('show');
      }
      document.querySelector('.sidebar-overlay').addEventListener('click', toggleSidebar);
      </script>
    </div>
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