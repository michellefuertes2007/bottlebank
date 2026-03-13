<?php
// Test script to verify return validation logic

include 'includes/db_connect.php';

// Include the validation functions from returns.php
// Mock the functions here for testing

function getCustomerAvailableBottles($conn, $customer_name) {
  $available_bottles = [];
  
  $deposits_query = $conn->prepare("SELECT details FROM stock_log WHERE action_type='Deposit' AND customer_name = ? ORDER BY date_logged DESC");
  if (!$deposits_query) return [];
  
  $deposits_query->bind_param('s', $customer_name);
  $deposits_query->execute();
  $deposits_result = $deposits_query->get_result();
  
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

function validateReturnBottle($conn, $customer_name, $bottle_type, $bottle_size, $quantity) {
  $available = getCustomerAvailableBottles($conn, $customer_name);
  
  if (!isset($available[$bottle_type])) {
    return ['valid' => false, 'error' => "Customer did not deposit " . htmlspecialchars($bottle_type) . ". Available: " . (count($available) > 0 ? implode(', ', array_keys($available)) : 'none')];
  }
  
  $available_qty = $available[$bottle_type]['available_qty'];
  if ($quantity > $available_qty) {
    return ['valid' => false, 'error' => "Only " . $available_qty . " of " . htmlspecialchars($bottle_type) . " available (" . $available[$bottle_type]['deposited_qty'] . " deposited, " . $available[$bottle_type]['returned_qty'] . " returned)"];
  }
  
  return ['valid' => true];
}

// Test cases
echo "=== RETURN VALIDATION TEST ===\n\n";

// Get all customers with deposits
$customers = [];
$cR = $conn->query("SELECT DISTINCT customer_name FROM stock_log WHERE action_type='Deposit' ORDER BY customer_name ASC");
if ($cR) {
  while ($r = $cR->fetch_assoc()) {
    $customers[] = $r['customer_name'];
  }
}

if (empty($customers)) {
  echo "No customers with deposits found. Please create a deposit first.\n";
  echo "Run deposit.php to create a test deposit.\n";
} else {
  $test_customer = $customers[0];
  echo "Testing with customer: " . htmlspecialchars($test_customer) . "\n\n";
  
  // Get available bottles
  $available = getCustomerAvailableBottles($conn, $test_customer);
  
  if (empty($available)) {
    echo "This customer has no available bottles (all returned).\n";
  } else {
    echo "Available bottles for " . htmlspecialchars($test_customer) . ":\n";
    foreach ($available as $type => $info) {
      echo "  - " . htmlspecialchars($type) . " " . htmlspecialchars($info['bottle_size']) . ": " . $info['available_qty'] . " available (" . $info['deposited_qty'] . " deposited, " . $info['returned_qty'] . " returned)\n";
    }
    
    echo "\n--- TEST 1: Valid partial return ---\n";
    $bottle_type = array_key_first($available);
    $qty = max(1, floor($available[$bottle_type]['available_qty'] / 2));
    $result = validateReturnBottle($conn, $test_customer, $bottle_type, $available[$bottle_type]['bottle_size'], $qty);
    echo "Attempting to return " . $qty . " of " . htmlspecialchars($bottle_type) . ": ";
    echo $result['valid'] ? "✓ PASS" : "✗ FAIL - " . $result['error'];
    echo "\n\n";
    
    echo "--- TEST 2: Return too many (over-return) ---\n";
    $result = validateReturnBottle($conn, $test_customer, $bottle_type, $available[$bottle_type]['bottle_size'], $available[$bottle_type]['available_qty'] + 1);
    echo "Attempting to return more than available: ";
    echo !$result['valid'] ? "✓ PASS - " . $result['error'] : "✗ FAIL (should reject)";
    echo "\n\n";
    
    echo "--- TEST 3: Return wrong bottle type ---\n";
    $result = validateReturnBottle($conn, $test_customer, 'sprite', 'small', 1);
    echo "Attempting to return 'sprite' (not deposited): ";
    echo !$result['valid'] ? "✓ PASS - " . $result['error'] : "✗ FAIL (should reject)";
    echo "\n\n";
  }
  
  echo "--- CUSTOMER DROPDOWN FILTER ---\n";
  $filtered_customers = [];
  foreach ($customers as $cust) {
    $avail = getCustomerAvailableBottles($conn, $cust);
    if (count($avail) > 0) {
      $filtered_customers[] = $cust;
    }
  }
  
  echo "Customers shown in dropdown (with unreturned deposits):\n";
  foreach ($filtered_customers as $cust) {
    echo "  - " . htmlspecialchars($cust) . "\n";
  }
  if (empty($filtered_customers)) {
    echo "  (none - all customers have fully returned all bottles)\n";
  }
}

$conn->close();
