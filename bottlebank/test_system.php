<?php
// Test Script: Verify Multi-Bottle Deposit System
// Run this to check if all components are working

require 'includes/db_connect.php';

echo "=== BottleBank Multi-Bottle Deposit System Test ===\n\n";

// Test 1: Verify bottle_types table has new columns
echo "TEST 1: Checking bottle_types table structure...\n";
$result = $conn->query("SHOW COLUMNS FROM bottle_types");
$columns = [];
while ($row = $result->fetch_assoc()) {
    $columns[$row['Field']] = $row['Type'];
}

if (isset($columns['bottle_size']) && isset($columns['price_per_bottle'])) {
    echo "✓ bottle_size column exists\n";
    echo "✓ price_per_bottle column exists\n";
} else {
    echo "✗ Missing columns in bottle_types table\n";
}

// Test 2: Check bottle data
echo "\nTEST 2: Checking bottle data with prices...\n";
$bottles = $conn->query("SELECT type_name, bottle_size, price_per_bottle FROM bottle_types ORDER BY type_name");
$count = 0;
while ($row = $bottles->fetch_assoc()) {
    echo "  • {$row['type_name']} ({$row['bottle_size']}) - ₱{$row['price_per_bottle']}\n";
    $count++;
}
echo "✓ Found $count bottle types\n";

// Test 3: Verify deposit table structure
echo "\nTEST 3: Checking deposit table...\n";
$result = $conn->query("SHOW COLUMNS FROM deposit");
$cols = [];
while ($row = $result->fetch_assoc()) {
    $cols[$row['Field']] = 1;
}
if (isset($cols['amount'])) {
    echo "✓ amount column exists in deposit table\n";
} else {
    echo "✗ amount column missing\n";
}

// Test 4: Verify stock_log structure
echo "\nTEST 4: Checking stock_log table...\n";
$result = $conn->query("SHOW COLUMNS FROM stock_log");
$cols = [];
while ($row = $result->fetch_assoc()) {
    $cols[$row['Field']] = 1;
}
if (isset($cols['details']) && isset($cols['amount'])) {
    echo "✓ details and amount columns exist\n";
} else {
    echo "✗ Missing columns in stock_log\n";
}

// Test 5: Count existing records
echo "\nTEST 5: Current record counts...\n";
$deposits = $conn->query("SELECT COUNT(*) as cnt FROM deposit")->fetch_assoc();
$logs = $conn->query("SELECT COUNT(*) as cnt FROM stock_log")->fetch_assoc();
$returns = $conn->query("SELECT COUNT(*) as cnt FROM returns")->fetch_assoc();

echo "  Deposits: {$deposits['cnt']}\n";
echo "  Stock Log: {$logs['cnt']}\n";
echo "  Returns: {$returns['cnt']}\n";

echo "\n=== Database Setup Complete ===\n";
echo "\nYou can now:\n";
echo "1. Go to http://localhost/BB/deposit.php\n";
echo "2. Try adding a multi-bottle deposit with 2+ bottle types\n";
echo "3. Check stock_log.php to see the results\n";
echo "4. Try returning some bottles and verify deposit is NOT deleted\n";

$conn->close();
?>
