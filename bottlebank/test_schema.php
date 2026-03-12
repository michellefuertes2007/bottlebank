<?php
include 'includes/db_connect.php';

// Check stock_log table structure
echo "=== Checking stock_log table ===\n";
$result = $conn->query("DESCRIBE stock_log");
if ($result) {
  echo "Columns in stock_log:\n";
  while ($row = $result->fetch_assoc()) {
    echo "  - " . $row['Field'] . " (" . $row['Type'] . ")\n";
  }
} else {
  echo "Error: " . $conn->error . "\n";
}

echo "\n=== Sample deposits ===\n";
$deposits = $conn->query("SELECT customer_name, action_type, amount, details FROM stock_log WHERE action_type='Deposit' LIMIT 3");
if ($deposits) {
  while ($row = $deposits->fetch_assoc()) {
    echo "Customer: " . htmlspecialchars($row['customer_name']) .
         "\nAmount: " . ($row['amount'] ? '₱' . number_format($row['amount'], 2) : 'NULL') .
         "\nDetails: " . substr($row['details'], 0, 60) . "...\n\n";
  }
}

$conn->close();
