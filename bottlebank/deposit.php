    {
      {const opt = select.options[select.selectedIndex];
      // ...existing code for updateBottleInfo...
  // ...existing code...
  }
}
<?php
session_start();
require 'includes/db_connect.php';
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }
$user_id = intval($_SESSION['user_id']);
$is_admin = (isset($_SESSION['role']) && $_SESSION['role'] === 'admin');
$msg = '';
$error = '';
// helper to determine if a table exists in the current database
function tableExists($conn, $table) {
    $res = $conn->query("SHOW TABLES LIKE '$table'");
    return ($res && $res->num_rows > 0);
} 

// helper to add column if missing (avoids syntax issues on older MySQL)
// now also skips if table itself doesn't exist
function ensureColumn($conn, $table, $column, $definition) {
    if (!tableExists($conn, $table)) {
        // table not present yet, nothing to do
        return;
    }
    $res = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
    if(!$res || $res->num_rows === 0) {
        $conn->query("ALTER TABLE `$table` ADD COLUMN $definition");
    }
}

// guarantee the size field exists in related tables
ensureColumn($conn, 'deposit', 'bottle_size', "bottle_size VARCHAR(10) DEFAULT 'small'");
ensureColumn($conn, 'customers', 'bottle_size', "bottle_size VARCHAR(10) DEFAULT 'small'");
ensureColumn($conn, 'returns', 'bottle_size', "bottle_size VARCHAR(10) DEFAULT 'small'");
// stock_log also started recording size; add column if missing
ensureColumn($conn, 'stock_log', 'bottle_size', "bottle_size VARCHAR(10) DEFAULT 'small'");

// Add customer_id to tables that reference canonical customer records (new column for linking)
ensureColumn($conn, 'deposit', 'customer_id', 'customer_id INT(11) DEFAULT NULL');
ensureColumn($conn, 'deposit', 'customer_name', "customer_name VARCHAR(100) DEFAULT NULL");
ensureColumn($conn, 'stock_log', 'customer_id', 'customer_id INT(11) DEFAULT NULL');

// Get all bottle types with prices from database
$bottle_types_list = [];
$btResult = $conn->query("SELECT type_id, type_name, bottle_size, price_per_bottle FROM bottle_types ORDER BY type_name ASC");
if ($btResult) {}
  while ($row = $btResult->fetch_assoc()) {
    $bottle_types_list[] = $row;
  }

// prepare distinct customer list from canonical customer records for datalist/autofill
$deposit_customers = [];
$cR = $conn->query("SELECT canonical_name FROM customer ORDER BY canonical_name ASC");
if ($cR) { while ($r = $cR->fetch_assoc()) $deposit_customers[] = $r['canonical_name']; }

// Handle editing bottle type
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_bottle_type']) && $is_admin) {
  $type_id = intval($_POST['type_id'] ?? 0);
  $edit_name = trim($_POST['edit_bottle_name'] ?? '');
  $edit_size = trim($_POST['edit_bottle_size'] ?? '');
  $edit_price = floatval($_POST['edit_bottle_price'] ?? 0);
  if (!$type_id) {
    $error = 'Invalid bottle type.';
  } elseif (!$edit_name || strlen($edit_name) < 3 || strlen($edit_name) > 100) {
    $error = 'Bottle type name must be 3-100 characters.';
  } elseif (!$edit_size || strlen($edit_size) < 2 || strlen($edit_size) > 20) {
    $error = 'Bottle size must be 2-20 characters.';
  } elseif ($edit_price < 0) {
    $error = 'Price must be positive.';
  } else {
    // Prevent exact duplicate (same name, size, price) on other records
    $dupCheck = $conn->prepare("SELECT COUNT(*) as cnt FROM bottle_types WHERE type_name = ? AND bottle_size = ? AND price_per_bottle = ? AND type_id != ?");
    if ($dupCheck) {
      $dupCheck->bind_param('ssdi', $edit_name, $edit_size, $edit_price, $type_id);
      $dupCheck->execute();
      $dupRes = $dupCheck->get_result();
      $dupRow = $dupRes ? $dupRes->fetch_assoc() : null;
      $dupCheck->close();
      if ($dupRow && intval($dupRow['cnt']) > 0) {
        $error = "Bottle type '" . htmlspecialchars($edit_name) . "' with size '" . htmlspecialchars($edit_size) . "' and price '" . number_format($edit_price,2) . "' already exists. Please edit the existing entry instead.";
      }
    }

    if (empty($error)) {
      $stmt = $conn->prepare("UPDATE bottle_types SET type_name = ?, bottle_size = ?, price_per_bottle = ? WHERE type_id = ?");
      $stmt->bind_param("ssdi", $edit_name, $edit_size, $edit_price, $type_id);
      try {
        if ($stmt->execute()) {
          $msg = "✓ Bottle type updated successfully!";
          // Refresh the bottle types list
          $bottle_types_list = [];
          $btResult = $conn->query("SELECT type_id, type_name, bottle_size, price_per_bottle FROM bottle_types ORDER BY type_name ASC");
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
  // ...existing code...
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
  $new_size = trim($_POST['new_bottle_size'] ?? 'small');
  $new_price = floatval($_POST['new_price'] ?? 0);
  
  // Validation checks
  if (!$new_type) {
    $error = 'Please enter a bottle type name.';
  } elseif (strlen($new_type) < 2) {
    $error = 'Bottle type name must be at least 2 characters.';
  } elseif ($new_price < 0) {
    $error = 'Price cannot be negative.';
  } else {
    // Prevent inserting exact duplicate (same name, size, price)
    $dupCheck = $conn->prepare("SELECT COUNT(*) as cnt FROM bottle_types WHERE type_name = ? AND bottle_size = ? AND price_per_bottle = ?");
    if ($dupCheck) {
      $dupCheck->bind_param('ssd', $new_type, $new_size, $new_price);
      $dupCheck->execute();
      $dupRes = $dupCheck->get_result();
      $dupRow = $dupRes ? $dupRes->fetch_assoc() : null;
      $dupCheck->close();
      if ($dupRow && intval($dupRow['cnt']) > 0) {
        $error = "Bottle type '" . htmlspecialchars($new_type) . "' with size '" . htmlspecialchars($new_size) . "' and price '" . number_format($new_price,2) . "' already exists. Please edit the existing entry instead.";
      }
    }

    if (empty($error)) {
      $stmt = $conn->prepare("INSERT INTO bottle_types (type_name, bottle_size, price_per_bottle, created_by) VALUES (?, ?, ?, NULL)");
      $stmt->bind_param("ssd", $new_type, $new_size, $new_price);

      try {
        if ($stmt->execute()) {
          // Refresh the bottle types list
          $bottle_types_list = [];
          $btResult = $conn->query("SELECT type_id, type_name, bottle_size, price_per_bottle FROM bottle_types ORDER BY type_name ASC");
          if ($btResult) {
            while ($row = $btResult->fetch_assoc()) {
              $bottle_types_list[] = $row;
            }
          }
          $msg = "✓ Bottle type '" . htmlspecialchars($new_type) . "' added successfully!";
        }
      } catch (mysqli_sql_exception $e) {
        // If unique constraint violated for (type_name, bottle_size), inform user to edit existing type
        if (strpos($e->getMessage(), 'Duplicate') !== false || strpos($e->getMessage(), 'UNIQUE') !== false) {
          $error = "Bottle type '" . htmlspecialchars($new_type) . "' with size '" . htmlspecialchars($new_size) . "' already exists. Please edit it instead.";
        } else {
          $error = "Error adding bottle type: " . htmlspecialchars($e->getMessage());
        }
      }

      $stmt->close();
    }
  }
}

// Handle MULTI-BOTTLE DEPOSIT form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['edit_id']) && !isset($_POST['add_bottle_type']) && isset($_POST['bottles_json'])) {
  $customer_name = trim($_POST['customer_name'] ?? '');
  $bottles_json = $_POST['bottles_json'];

  if (empty($customer_name)) {
    $error = 'Customer name is required.';
  } else {
      // Ensure we have a canonical customer record (for drop-down and FK integrity)
      $customer_id = null;
      $findCust = $conn->prepare("SELECT customer_id FROM customer WHERE canonical_name = ?");
      if ($findCust) {
        $findCust->bind_param('s', $customer_name);
        $findCust->execute();
        $findCust->bind_result($cid);
        if ($findCust->fetch()) {
          $customer_id = $cid;
        }
        $findCust->close();
      }
      if (!$customer_id) {
        $addCust = $conn->prepare("INSERT INTO customer (canonical_name) VALUES (?)");
        if ($addCust) {
          $addCust->bind_param('s', $customer_name);
          if ($addCust->execute()) {
            $customer_id = $addCust->insert_id;
          }
          $addCust->close();
        }
      }

      if (!$customer_id) {
        $error = 'Failed to resolve customer record.';
      }

    // Decode submitted bottle list JSON
    $decoded = json_decode($bottles_json, true);
    if (!is_array($decoded) || json_last_error() !== JSON_ERROR_NONE) {
      $error = 'Invalid bottle data submitted.';
    }

    // Handle both array format and object format with manual_amount
    $manual_amount = null;
    if (empty($error)) {
      if (isset($decoded['bottles']) && is_array($decoded['bottles'])) {
        $bottles = $decoded['bottles'];
        $manual_amount = floatval($decoded['manual_amount'] ?? 0);
      } elseif (is_array($decoded)) {
        $bottles = $decoded;
      } else {
        $bottles = [];
      }

      if (!$bottles || !is_array($bottles) || count($bottles) === 0) {
        $error = 'Please add at least one bottle type to the deposit.';
      } else {
        $total_amount = 0;
        $all_valid = true;
        $deposit_summary = [];

        // Validate all bottles and calculate total
        foreach ($bottles as $bottle) {
          $bottle_type = trim($bottle['bottle_type'] ?? '');
          $quantity = intval($bottle['quantity'] ?? 0);
          $price_per_bottle = floatval($bottle['price_per_bottle'] ?? 0);
          $bottle_size = $bottle['bottle_size'] ?? 'small';
          $with_case = isset($bottle['with_case']) ? intval($bottle['with_case']) : 0;
          $case_quantity = isset($bottle['case_quantity']) ? intval($bottle['case_quantity']) : 0;

          if (empty($bottle_type) || $quantity <= 0) {
            $all_valid = false;
            break;
          }

          // If type_id was provided, resolve canonical type name/size/price from bottle_types to keep records consistent
          $type_id = intval($bottle['type_id'] ?? 0);
          if ($type_id > 0) {
            $ptype = $conn->prepare("SELECT type_name, bottle_size, price_per_bottle FROM bottle_types WHERE type_id = ? LIMIT 1");
            if ($ptype) {
              $ptype->bind_param('i', $type_id);
              $ptype->execute();
              $tres = $ptype->get_result();
              if ($trow = $tres->fetch_assoc()) {
                $bottle_type = $trow['type_name'];
                if (empty($bottle_size)) {
                  $bottle_size = $trow['bottle_size'];
                }
                $price_per_bottle = floatval($trow['price_per_bottle']);
              }
              $ptype->close();
            }
          }

          if (empty($bottle_type) || $quantity <= 0 || $type_id <= 0) {
            $all_valid = false;
            break;
          }

          $subtotal = $quantity * $price_per_bottle;
          $total_amount += $subtotal;
          $deposit_summary[] = [
            'type_id' => $type_id,
            'bottle_type' => $bottle_type,
            'bottle_size' => $bottle_size,
            'quantity' => $quantity,
            'price_per_bottle' => $price_per_bottle,
            'subtotal' => $subtotal,
            'with_case' => $with_case,
            'case_quantity' => $case_quantity
          ];
        }

        // Use manual amount if provided
        if ($manual_amount !== null && $manual_amount > 0) {
          $total_amount = $manual_amount;
        }

        if (!$all_valid) {
          $error = 'Invalid bottle information. Please check all entries.';
        } else {
          // Create ONE deposit record with total amount
          $deposit_details = implode(' + ', array_map(function($item) {
            return $item['quantity'] . "x " . $item['bottle_type'] . " {$item['bottle_size']} (₱" . number_format($item['price_per_bottle'], 2, '.', ',') . ")";
          }, $deposit_summary));
        
        $summary_type = count($deposit_summary) > 1 ? "Multiple" : $deposit_summary[0]['bottle_type'];
        $summary_qty = array_sum(array_column($deposit_summary, 'quantity'));
        
        // Aggregate case info for summary deposit record
        $aggregate_with_case = 0;
        $aggregate_case_quantity = 0;
        foreach ($deposit_summary as $ds) {
          if (!empty($ds['with_case'])) $aggregate_with_case = 1;
          $aggregate_case_quantity += intval($ds['case_quantity'] ?? 0);
        }

        // Determine a representative type_id/size for the summary record (use the first bottle entry)
        $summary_type_id = intval($deposit_summary[0]['type_id'] ?? 0);
        $summary_type = count($deposit_summary) > 1 ? "Multiple" : $deposit_summary[0]['bottle_type'];
        $summary_size = $deposit_summary[0]['bottle_size'] ?? 'small';
        $summary_qty = array_sum(array_column($deposit_summary, 'quantity'));

        if (!$customer_id) {
          $error = 'Failed to resolve customer record.';
        } else {
          $ins = $conn->prepare("INSERT INTO deposit (user_id, customer_id, customer_name, type_id, quantity, with_case, case_quantity, amount, bottle_size, deposit_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
          $ins->bind_param('iisiiiids', $user_id, $customer_id, $customer_name, $summary_type_id, $summary_qty, $aggregate_with_case, $aggregate_case_quantity, $total_amount, $summary_size);

          if ($ins->execute()) {
          // Summary stock_log entry
          $stock_details = "Deposit — " . $deposit_details . " | Total: ₱" . number_format($total_amount, 2, '.', ',');
          $log = $conn->prepare("INSERT INTO stock_log (user_id, action_type, customer_id, type_id, customer_name, bottle_type, quantity, amount, details, with_case, case_quantity, bottle_size) VALUES (?, 'Deposit', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
          $log->bind_param('iiissidsiis', $user_id, $customer_id, $summary_type_id, $customer_name, $summary_type, $summary_qty, $total_amount, $stock_details, $aggregate_with_case, $aggregate_case_quantity, $summary_size);
          $log->execute();
          $log->close();

          // Also create per-bottle stock_log entries so returns UI can auto-detect per-type with_case
          foreach ($deposit_summary as $ds) {
            $per_amount = $ds['subtotal'];
            $per_details = "Deposit — " . $ds['quantity'] . "x " . $ds['bottle_type'] . " " . $ds['bottle_size'] . " (₱" . number_format($ds['price_per_bottle'], 2, '.', ',') . ")";
            $plog = $conn->prepare("INSERT INTO stock_log (user_id, action_type, customer_id, type_id, customer_name, bottle_type, quantity, amount, details, with_case, case_quantity, bottle_size) VALUES (?, 'Deposit', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $plog_with_case = intval($ds['with_case']);
            $plog_case_quantity = intval($ds['case_quantity']);
            $plog->bind_param('iiissidsiis', $user_id, $customer_id, $ds['type_id'], $customer_name, $ds['bottle_type'], $ds['quantity'], $per_amount, $per_details, $plog_with_case, $plog_case_quantity, $ds['bottle_size']);
            $plog->execute();
            $plog->close();
          }

          $msg = '✓ Multi-bottle deposit recorded! Total: ₱' . number_format($total_amount, 2, '.', ',');
          header("refresh:2;url=index.php");
        } else {
          $error = 'Database error: ' . $ins->error;
        }
        if (isset($ins) && $ins) {
          $ins->close();
        }
      }
    }
  }
}
}
}

// Admin edit handling
if ($is_admin && isset($_GET['edit_id'])) {
  $edit_id = intval($_GET['edit_id']);
  $eSt = $conn->prepare("SELECT deposit_id, customer_name, bottle_type, quantity, bottle_size, deposit_date FROM deposit WHERE deposit_id = ?");
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
    /* bottle size toggle buttons */
    .size-toggle { display:flex; gap:8px; }
    .size-option { cursor:pointer; user-select:none; padding:0; border:2px solid #26a69a; border-radius:6px; transition:all .2s; font-weight:600; display:flex; align-items:center; }
    .size-option input { display:none; }
    .size-option span { padding:8px 16px; color:#26a69a; display:block; }
    .size-option input:checked ~ span { background:#26a69a; color:white; }
    .size-option:hover { background:rgba(38,166,154,.15); }
    button { padding:10px 15px; border:none; border-radius:6px; cursor:pointer; font-weight:600; transition:0.3s; }
    button.primary { background:#26a69a; color:white; }
    button.primary:hover { background:#2e7d7d; }
    button.ghost { background:#80cbc4; color:#004d40; border:1px solid #80cbc4; }
    button.ghost:hover { background:#4db6ac; }
    .notice { position:fixed; top:50%; left:50%; transform:translate(-50%, -50%); padding:20px 30px; background:#e9fbf1; border-left:4px solid #26a69a; border-radius:6px; color:#155724; font-weight:500; z-index:9999; box-shadow:0 4px 12px rgba(0,0,0,0.15); min-width:300px; text-align:center; margin-top:0 !important; }
    .error { position:fixed; top:50%; left:50%; transform:translate(-50%, -50%); padding:20px 30px; background:#ffecec; border-left:4px solid #ef5350; border-radius:6px; color:#c62828; font-weight:500; z-index:9999; box-shadow:0 4px 12px rgba(0,0,0,0.15); min-width:300px; text-align:center; margin-top:0 !important; }
    @keyframes slideDown { from { opacity:0; transform:translate(-50%, -50%) translateY(-20px); } to { opacity:1; transform:translate(-50%, -50%) translateY(0); } }
    @keyframes slideUp { from { opacity:1; transform:translate(-50%, -50%) translateY(0); } to { opacity:0; transform:translate(-50%, -50%) translateY(-20px); } }
    .topbar .toggle-sidebar { background:none; border:none; font-size:18px; cursor:pointer; color:#2d6a6a; font-weight:600; display:none; transition:0.3s; }
    .topbar .toggle-sidebar:hover { color:#00796b; }
    @media (max-width:768px) { .topbar .toggle-sidebar { display:block; } }

    /* customer name autocomplete dropdown */
    .suggestions {
      position: relative;
      margin-top: 4px;
      border: 1px solid #ddd;
      border-radius: 6px;
      background: #fff;
      box-shadow: 0 4px 12px rgba(0,0,0,0.08);
      max-height: 220px;
      overflow-y: auto;
      font-size: 14px;
      z-index: 10;
    }
    .suggestions.hidden { display: none; }
    .suggestion-item {
      padding: 10px 12px;
      cursor: pointer;
      border-bottom: 1px solid rgba(0,0,0,0.05);
    }
    .suggestion-item:last-child { border-bottom: none; }
    .suggestion-item:hover { background: #f2f7f7; }
  </style>
</head>
<body>

<script>
// define toggleSidebar early so Menu buttons don't break
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

    // Multi-bottle deposit functionality
    var bottleTypes = <?php echo json_encode(array_map(fn($b) => [
      'type_id' => intval($b['type_id']),
      'type_name' => $b['type_name'],
      'bottle_size' => $b['bottle_size'],
      'price_per_bottle' => (float)$b['price_per_bottle']
    ], $bottle_types_list)); ?>;

    let bottleCount = 0;

    function formatPrice(num) {
      return num.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    }

    window.addBottleRow = function() {
      const container = document.getElementById('bottleList');
      const rowId = 'bottle_' + (++bottleCount);
      const html = `
        <div class="bottle-row" id="${rowId}" style="display:flex;gap:10px;align-items:flex-end;margin-bottom:12px;padding:12px;background:white;border-radius:6px;border:1px solid #eee;">
          <select onchange="updateBottleInfo(this)" style="flex:1.2;padding:8px;border:1px solid #ddd;border-radius:4px;font-size:13px;">
            <option value="">Select a bottle type</option>
            ${bottleTypes.map(b => `<option value="${b.type_id}" data-type-id="${b.type_id}" data-type-name="${b.type_name}" data-bottle-size="${b.bottle_size}" data-price="${b.price_per_bottle}">${b.type_name} ${b.bottle_size} ${b.price_per_bottle ? '₱'+Number(b.price_per_bottle).toFixed(2): ''}</option>`).join('')}
          </select>
          <input type="number" min="1" placeholder="Qty" class="quantity-input" style="width:70px;padding:8px;border:1px solid #ddd;border-radius:4px;font-size:13px;" onchange="updateTotal()">
          <label style="display:flex;align-items:center;gap:8px;margin:0 6px;">
            <input type="checkbox" class="with-case-checkbox" style="width:18px;height:18px;cursor:pointer;margin:0;"> With case
          </label>
          <input type="number" min="0" placeholder="Cases" class="case-quantity-input" style="width:80px;padding:8px;border:1px solid #ddd;border-radius:4px;font-size:13px;" value="0">
          <div class="price-display" style="width:80px;color:#00796b;font-weight:600;padding:8px;border-radius:4px;text-align:right;">₱0.00</div>
          <div class="subtotal" style="width:80px;color:#26a69a;font-weight:600;padding:8px;border-radius:4px;text-align:right;">₱0.00</div>
          <button type="button" onclick="removeBottleRow('${rowId}')" style="padding:8px 12px;background:#ef5350;color:white;border:none;border-radius:4px;cursor:pointer;font-size:12px;font-weight:600;">Remove</button>
        </div>
      `;
      container.insertAdjacentHTML('beforeend', html);
    }

    function updateBottleInfo(select) {
      const row = select.closest('.bottle-row');
      const opt = select.options[select.selectedIndex];
      const type = opt ? (opt.dataset.typeName || '') : '';
      const size = opt ? (opt.dataset.bottleSize || '') : '';
      const priceNum = opt && opt.dataset.price ? parseFloat(opt.dataset.price) : 0;
      row.dataset.typeId = opt ? (opt.dataset.typeId || '') : '';
      row.dataset.bottleType = type;
      row.dataset.bottleSize = size;
      row.dataset.pricePerBottle = priceNum;
      row.querySelector('.price-display').textContent = '₱' + formatPrice(priceNum);
      updateTotal();
    }

    function updateTotal() {
      let total = 0;
      const bottleList = document.getElementById('bottleList');
      bottleList.querySelectorAll('.bottle-row').forEach(row => {
        const qty = parseInt(row.querySelector('.quantity-input').value) || 0;
        const price = parseFloat(row.dataset.pricePerBottle) || 0;
        const subtotal = qty * price;
        row.querySelector('.subtotal').textContent = '₱' + formatPrice(subtotal);
        total += subtotal;
      });
      document.getElementById('totalAmount').textContent = '₱' + formatPrice(total);
    }

    function removeBottleRow(rowId) {
      document.getElementById(rowId).remove();
      updateTotal();
    }
    }
  // ...existing code...
    </script>
        <a href="logout.php" class="logout">Logout</a>
    </nav>
</div>
  <div class="app">
  <div class="topbar">
    <div class="brand">
        <button class="toggle-sidebar" onclick="toggleSidebar()">☰</button><div><h1>Deposit</h1><p class="kv">Record and manage bottle deposits</p></div></div>
    <div class="menu-wrap"><a href="index.php" class="kv">← Back to Dashboard</a></div>
  </div>

  <div class="grid" style="margin-top:8px;">
    <div class="panel" style="grid-column: span 12;">
      <h3 style="margin-top:0">New Deposit</h3>
      <?php if($msg): ?><div class="notice" id="notification" style="display:flex;justify-content:space-between;align-items:center;animation: slideDown 0.3s ease-in-out;"><?=$msg?><button onclick="document.getElementById('notification').style.display='none'" style="background:none;border:none;color:inherit;cursor:pointer;font-size:18px;padding:0;">✕</button></div><?php endif; ?>
      <?php if($error): ?><div class="error" id="error-notification" style="display:flex;justify-content:space-between;align-items:center;animation: slideDown 0.3s ease-in-out;"><?=htmlspecialchars($error)?><button onclick="document.getElementById('error-notification').style.display='none'" style="background:none;border:none;color:inherit;cursor:pointer;font-size:18px;padding:0;">✕</button></div><?php endif; ?>

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
          <?php $selectedSize = $editRow['bottle_size'] ?? 'small'; ?>
          <div class="form-row">
            <div class="col">
              <label>Bottle Size</label>
              <div class="size-toggle">
                <label class="size-option"><input type="radio" name="bottle_size" value="small" <?= $selectedSize !== '1l' ? 'checked' : '' ?>><span>8oz/12oz</span></label>
                <label class="size-option"><input type="radio" name="bottle_size" value="1l" <?= $selectedSize === '1l' ? 'checked' : '' ?>><span>1L</span></label>
              </div>
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
      <form method="post" style="max-width:800px" id="depositForm">
        <h4 style="margin-bottom:15px;color:#2d6a6a;">Customer Information</h4>
        <div class="form-row" style="margin-bottom:25px;">
          <div class="col" style="min-width:100%; flex:1;">
            <label>Customer Name</label>
            <input type="text" name="customer_name" id="customer_name" list="customer_list" placeholder="Enter customer name" autocomplete="off" required>
            <datalist id="customer_list">
              <?php foreach($deposit_customers as $c): ?>
                <option value="<?= htmlspecialchars($c) ?>">
              <?php endforeach; ?>
            </datalist>
            <div id="customer_suggestions" class="suggestions" style="display:none;"></div>
          </div>
        </div>

        <h4 style="margin:20px 0 10px 0;color:#2d6a6a;">Select Bottles to Deposit</h4>
        <div style="border:1px solid #e0e0e0;border-radius:8px;padding:15px;background:#fafafa;margin-bottom:15px;" id="bottleList"></div>
        
        <div style="margin-bottom:20px;">
          <button type="button" onclick="addBottleRow()" class="primary" style="background:#80cbc4;padding:10px 16px;border-radius:6px;border:none;color:#004d40;font-weight:600;cursor:pointer;">+ Add Bottle Type</button>
        </div>

        <div style="border-top:2px solid #26a69a;padding-top:15px;margin-top:15px;">
          <div style="display:flex;align-items:flex-start;gap:20px;justify-content:space-between;">
            <div>
              <div style="color:#666;font-size:14px;font-weight:600;margin-bottom:5px;">Calculated Total:</div>
              <div style="font-size:28px;font-weight:700;color:#26a69a;" id="totalAmount">₱0.00</div>
            </div>
            <div>
              <label style="color:#666;font-size:13px;font-weight:600;display:block;margin-bottom:8px;">Or Enter Manual Amount:</label>
              <input type="number" step="0.01" min="0" id="manualAmount" name="manual_amount" placeholder="Discount" style="width:150px;padding:10px;border:2px solid #26a69a;border-radius:4px;font-size:14px;font-weight:600;color:#26a69a;">
            </div>
          </div>
        </div>

        <button type="submit" class="primary" style="margin-top:25px;width:100%;padding:12px;background:#26a69a;color:white;border:none;border-radius:6px;font-weight:600;cursor:pointer;font-size:14px;">Record Multi-Bottle Deposit</button>
        <input type="hidden" name="bottles_json" id="bottles_json" value="[]">
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
              <div id="bottle_type_<?= $type['type_id'] ?>" data-type-id="<?= $type['type_id'] ?>" style="background:#26a69a;color:white;padding:5px 10px;border-radius:4px;font-size:12px;font-weight:500;display:flex;gap:6px;align-items:center;">
                <span><?= htmlspecialchars($type['type_name']) ?> <?= htmlspecialchars($type['bottle_size']) ?> ₱<?= number_format($type['price_per_bottle'], 2) ?></span>
                <?php if ($is_admin): ?>
                  <button type="button" class="edit-btn" onclick="editBottleType(<?= $type['type_id'] ?>, '<?= htmlspecialchars(addslashes($type['type_name'])) ?>', '<?= htmlspecialchars(addslashes($type['bottle_size'])) ?>', <?= (float)$type['price_per_bottle'] ?>)" style="background:none;border:none;color:white;cursor:pointer;padding:0;font-size:12px;opacity:0.8;" title="Edit">✎</button>
                  <button type="button" class="delete-btn" onclick="deleteBottleType(<?= $type['type_id'] ?>, '<?= htmlspecialchars(addslashes($type['type_name'])) ?>')" style="background:none;border:none;color:white;cursor:pointer;padding:0;font-size:12px;opacity:0.8;" title="Delete">✕</button>
                <?php endif; ?>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
        
        <form method="post" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end;" id="addBottleTypeForm">
          <input 
            type="text" 
            name="new_bottle_type" 
            placeholder="Bottle Type (e.g., Coke)" 
            minlength="2"
            style="flex:1;min-width:120px;padding:10px;border:2px solid #26a69a;border-radius:6px;font-family:'Poppins',sans-serif;font-size:14px;"
            required>
          <input 
            type="text" 
            name="new_bottle_size" 
            placeholder="Size (e.g., 8oz, 500ml)" 
            minlength="2"
            style="flex:0.8;min-width:90px;padding:10px;border:2px solid #26a69a;border-radius:6px;font-family:'Poppins',sans-serif;font-size:14px;"
            required>
          <input 
            type="number" 
            name="new_price" 
            placeholder="Price (₱)" 
            step="0.01"
            min="0"
            style="flex:0.6;min-width:80px;padding:10px;border:2px solid #26a69a;border-radius:6px;font-family:'Poppins',sans-serif;font-size:14px;"
            required>
          <button type="submit" name="add_bottle_type" class="primary" style="white-space:nowrap;padding:10px 16px;background:#26a69a;color:white;border:none;border-radius:6px;font-weight:600;cursor:pointer;">Add Type</button>
        </form>
      </div>


      <script>
      // Required functions for bottle type management
      function editBottleType(typeId, typeName) {
        // Admin modal for editing all fields
        let modal = document.getElementById('editBottleModal');
        if (!modal) {
          modal = document.createElement('div');
          modal.id = 'editBottleModal';
          modal.style = 'position:fixed;top:0;left:0;width:100vw;height:100vh;background:rgba(0,0,0,0.3);display:flex;align-items:center;justify-content:center;z-index:9999;';
          modal.innerHTML = `
            <div style="background:white;padding:30px;border-radius:8px;box-shadow:0 2px 16px #0002;min-width:320px;max-width:90vw;">
              <h3 style="margin-top:0;color:#2d6a6a;">Edit Bottle Type</h3>
              <form id="editBottleForm" method="POST" style="display:flex;flex-direction:column;gap:12px;">
                <input type="hidden" name="type_id" value="${typeId}">
                <input type="hidden" name="edit_bottle_type" value="1">
                <label>Name <input type="text" name="edit_bottle_name" value="${typeName}" required minlength="2" maxlength="100" style="padding:8px;border-radius:4px;border:1px solid #26a69a;"></label>
                <label>Size <input type="text" name="edit_bottle_size" value="" required minlength="2" maxlength="20" style="padding:8px;border-radius:4px;border:1px solid #26a69a;"></label>
                <label>Price <input type="number" name="edit_bottle_price" value="" step="0.01" min="0" required style="padding:8px;border-radius:4px;border:1px solid #26a69a;"></label>
                <div style="display:flex;gap:10px;margin-top:10px;">
                  <button type="submit" class="primary" style="background:#26a69a;color:white;padding:8px 16px;border:none;border-radius:6px;font-weight:600;cursor:pointer;">Save</button>
                  <button type="button" onclick="document.body.removeChild(document.getElementById('editBottleModal'))" class="ghost" style="background:#eee;color:#333;padding:8px 16px;border:none;border-radius:6px;font-weight:600;cursor:pointer;">Cancel</button>
                </div>
              </form>
            </div>
          `;
          document.body.appendChild(modal);
        }
        // Set values
        modal.querySelector('input[name="edit_bottle_name"]').value = typeName;
        modal.querySelector('input[name="edit_bottle_size"]').value = arguments[2] || '';
        modal.querySelector('input[name="edit_bottle_price"]').value = arguments[3] || '';
        modal.querySelector('#editBottleForm').onsubmit = function(e) {
          e.preventDefault();
          const form = document.createElement('form');
          form.method = 'POST';
          form.innerHTML = `
            <input type="hidden" name="type_id" value="${typeId}">
            <input type="hidden" name="edit_bottle_type" value="1">
            <input type="hidden" name="edit_bottle_name" value="${modal.querySelector('input[name="edit_bottle_name"]').value.trim()}">
            <input type="hidden" name="edit_bottle_size" value="${modal.querySelector('input[name="edit_bottle_size"]').value.trim()}">
            <input type="hidden" name="edit_bottle_price" value="${modal.querySelector('input[name="edit_bottle_price"]').value.trim()}">
          `;
          document.body.appendChild(form);
          form.submit();
          document.body.removeChild(form);
          document.body.removeChild(modal);
        };
        modal.style.display = 'flex';
      }

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
      </script>
    </div>
  </div>
      <?php endif; ?>

  </div>

<script id="bottleTypesData" type="application/json">
<?= json_encode(array_map(fn($b) => [
  'type_id' => intval($b['type_id']),
  'type_name' => $b['type_name'],
  'bottle_size' => $b['bottle_size'],
  'price_per_bottle' => (float)$b['price_per_bottle']
], $bottle_types_list)); ?>
</script>
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

// Multi-bottle deposit functionality
const bottleTypes = <?php echo json_encode(array_map(fn($b) => [
  'type_id' => intval($b['type_id']),
  'type_name' => $b['type_name'],
  'bottle_size' => $b['bottle_size'],
  'price_per_bottle' => (float)$b['price_per_bottle']
], $bottle_types_list)); ?>;

let bottleCount = 0;

// Format number with comma separators (e.g., 1000.00 -> 1,000.00)
function formatPrice(num) {
  return num.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
}

window.addBottleRow = function() {
  const container = document.getElementById('bottleList');
  const rowId = 'bottle_' + (++bottleCount);
  
      const html = `
        <div class="bottle-row" id="${rowId}" style="display:flex;gap:10px;align-items:flex-end;margin-bottom:12px;padding:12px;background:white;border-radius:6px;border:1px solid #eee;">
          <select onchange="updateBottleInfo(this)" style="flex:1.2;padding:8px;border:1px solid #ddd;border-radius:4px;font-size:13px;">
            <option value="">Select a bottle type</option>
            ${bottleTypes.map(b => `<option value="${b.type_id}" data-type-id="${b.type_id}" data-type-name="${b.type_name}" data-bottle-size="${b.bottle_size}" data-price="${b.price_per_bottle}">${b.type_name} ${b.bottle_size} ${b.price_per_bottle ? '₱'+Number(b.price_per_bottle).toFixed(2): ''}</option>`).join('')}
          </select>
          <input type="number" min="1" placeholder="Qty" class="quantity-input" style="width:70px;padding:8px;border:1px solid #ddd;border-radius:4px;font-size:13px;" onchange="updateTotal()">
          <label style="display:flex;align-items:center;gap:8px;margin:0 6px;">
            <input type="checkbox" class="with-case-checkbox" style="width:18px;height:18px;cursor:pointer;margin:0;"> With case
          </label>
          <input type="number" min="0" placeholder="Cases" class="case-quantity-input" style="width:80px;padding:8px;border:1px solid #ddd;border-radius:4px;font-size:13px;" value="0">
          <div class="price-display" style="width:80px;color:#00796b;font-weight:600;padding:8px;border-radius:4px;text-align:right;">₱0.00</div>
          <div class="subtotal" style="width:80px;color:#26a69a;font-weight:600;padding:8px;border-radius:4px;text-align:right;">₱0.00</div>
          <button type="button" onclick="removeBottleRow('${rowId}')" style="padding:8px 12px;background:#ef5350;color:white;border:none;border-radius:4px;cursor:pointer;font-size:12px;font-weight:600;">Remove</button>
        </div>
      `;
  container.insertAdjacentHTML('beforeend', html);
}

function updateBottleInfo(select) {
  const row = select.closest('.bottle-row');
  const opt = select.options[select.selectedIndex];
  const type = opt ? (opt.dataset.typeName || '') : '';
  const size = opt ? (opt.dataset.bottleSize || '') : '';
  const priceNum = opt && opt.dataset.price ? parseFloat(opt.dataset.price) : 0;
  row.dataset.typeId = opt ? (opt.dataset.typeId || '') : '';
  row.dataset.bottleType = type;
  row.dataset.bottleSize = size;
  row.dataset.pricePerBottle = priceNum;
  row.querySelector('.price-display').textContent = '₱' + formatPrice(priceNum);
  updateTotal();
}

function updateTotal() {
  let total = 0;
  const bottleList = document.getElementById('bottleList');
  
  bottleList.querySelectorAll('.bottle-row').forEach(row => {
    const qty = parseInt(row.querySelector('.quantity-input').value) || 0;
    const price = parseFloat(row.dataset.pricePerBottle) || 0;
    const subtotal = qty * price;
    row.querySelector('.subtotal').textContent = '₱' + formatPrice(subtotal);
    total += subtotal;
  });
  
  document.getElementById('totalAmount').textContent = '₱' + formatPrice(total);
}

function removeBottleRow(rowId) {
  document.getElementById(rowId).remove();
  updateTotal();
}

// Fetch and auto-populate customer deposit history
function fetchAndPopulateCustomerData(customerName) {
  if (!customerName || !customerName.trim()) return;
  
  console.log('Fetching data for customer:', customerName);
  
  fetch('api/deposit.php?customer=' + encodeURIComponent(customerName))
    .then(r => {
      if (!r.ok) throw new Error('API request failed with status ' + r.status);
      return r.json();
    })
    .then(data => {
      console.log('Customer data received:', data);
      // For multi-bottle form, we don't auto-populate bottles
      // User must manually add bottles for flexibility
    })
    .catch(err => {
      console.error('Error fetching customer data:', err);
      // Silently fail - user can still proceed with manual entry
    });
}

// Auto-populate customer information when selected
window.addEventListener('DOMContentLoaded', function() {
  const customerSelect = document.getElementById('customer_name');
  if (customerSelect) {
    customerSelect.addEventListener('change', function() {
      const customerName = this.value.trim();
      if (customerName) {
        fetchAndPopulateCustomerData(customerName);
      }
    });
    
    customerSelect.addEventListener('blur', function() {
      const customerName = this.value.trim();
      if (customerName) {
        fetchAndPopulateCustomerData(customerName);
      }
    });
  }
  
  // Handle manual amount override
  const manualAmountInput = document.getElementById('manualAmount');
  if (manualAmountInput) {
    manualAmountInput.addEventListener('change', function() {
      if (this.value) {
        document.getElementById('totalAmount').textContent = '₱' + formatPrice(parseFloat(this.value));
      }
    });
  }
  
  // Deposit form submission
  const depositForm = document.getElementById('depositForm');
  if (depositForm) 
    depositForm.addEventListener('submit', function(e) {
      e.preventDefault();
      const bottleList = document.getElementById('bottleList');
      const bottles = [];
      
      bottleList.querySelectorAll('.bottle-row').forEach(row => {
        const qty = parseInt(row.querySelector('.quantity-input').value);
        if (row.dataset.bottleType && qty > 0) {
            const withCaseEl = row.querySelector('.with-case-checkbox');
            const caseQtyEl = row.querySelector('.case-quantity-input');
            const withCaseVal = withCaseEl && withCaseEl.checked ? 1 : 0;
            const caseQtyVal = caseQtyEl ? parseInt(caseQtyEl.value) || 0 : 0;
            bottles.push({
              type_id: parseInt(row.dataset.typeId) || 0,
              bottle_type: row.dataset.bottleType,
              bottle_size: row.dataset.bottleSize,
              quantity: qty,
              price_per_bottle: parseFloat(row.dataset.pricePerBottle),
              with_case: withCaseVal,
              case_quantity: caseQtyVal
            });
          }
      });
      
      if (bottles.length === 0) {
        alert('Please add at least one bottle type with quantity.');
        return;
      }
      
      const manualAmount = parseFloat(document.getElementById('manualAmount').value);
      if (manualAmount && manualAmount > 0) {
        document.getElementById('bottles_json').value = JSON.stringify({bottles: bottles, manual_amount: manualAmount});
      } else {
        document.getElementById('bottles_json').value = JSON.stringify(bottles);
      }
      this.submit();
    });

    // Customer name autocomplete matching
    const customerInput = document.getElementById('customer_name');
    const suggestionBox = document.getElementById('customer_suggestions');
    const customerNames = <?= json_encode(array_values($deposit_customers)) ?>;

    function renderSuggestions(matches) {
      suggestionBox.innerHTML = '';
      if (!matches.length) {
        suggestionBox.style.display = 'none';
        return;
      }
      matches.forEach(name => {
        const div = document.createElement('div');
        div.className = 'suggestion-item';
        div.textContent = name;
        div.addEventListener('mousedown', function(e) {
          // mousedown instead of click to avoid blur before selection
          e.preventDefault();
          customerInput.value = name;
          suggestionBox.style.display = 'none';
        });
        suggestionBox.appendChild(div);
      });
      suggestionBox.style.display = 'block';
    }

    if (customerInput && suggestionBox) {
      customerInput.addEventListener('input', function() {
        const q = this.value.trim().toLowerCase();
        if (!q) {
          suggestionBox.style.display = 'none';
          return;
        }
        const matches = customerNames.filter(n => n.toLowerCase().includes(q)).slice(0, 10);
        renderSuggestions(matches);
      });

      customerInput.addEventListener('blur', function() {
        setTimeout(() => {
          suggestionBox.style.display = 'none';
        }, 200);
      });

      customerInput.addEventListener('focus', function() {
        const q = this.value.trim().toLowerCase();
        if (q) {
          const matches = customerNames.filter(n => n.toLowerCase().includes(q)).slice(0, 10);
          renderSuggestions(matches);
        }
      });
    }

  

</script>
</body>
</html>