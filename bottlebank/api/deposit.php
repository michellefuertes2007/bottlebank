<?php
// Suppress warnings/notices that could break JSON output
error_reporting(E_ALL);
// Do not display PHP errors in JSON API responses; log them instead.
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
ini_set('display_errors', '1');
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../includes/db_connect.php';

// Convert PHP errors to exceptions so we can return JSON error payloads
set_error_handler(function($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});
set_exception_handler(function($e) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit;
});

// GET: fetch deposits and returns by canonical customer name (new schema)
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['customer'])) {
    $cust = trim($_GET['customer']);
    $deposits = [];
    $returns = [];
    $customerInfo = null;

    // Find customer_id by canonical name
    $customer_id = null;
    $find_cust = $conn->prepare("SELECT customer_id, canonical_name, created_at FROM customer WHERE canonical_name = ?");
    if ($find_cust) {
        $find_cust->bind_param('s', $cust);
        $find_cust->execute();
        $find_cust->bind_result($cid, $cname, $ccreated);
        if ($find_cust->fetch()) {
            $customer_id = $cid;
            $customerInfo = [
                'customer_id' => $cid,
                'canonical_name' => $cname,
                'created_at' => $ccreated
            ];
        }
        $find_cust->close();
    }
    if (!$customer_id) {
        echo json_encode(['deposits' => [], 'returns' => [], 'customer' => null, 'error' => 'Customer not found']);
        $conn->close();
        exit;
    }

    // Fetch deposits for this customer
    $stmt = $conn->prepare("SELECT d.deposit_id, d.type_id, b.type_name, b.bottle_size, d.quantity, d.amount, d.deposit_date FROM deposit d JOIN bottle_types b ON d.type_id = b.type_id WHERE d.customer_id = ? ORDER BY d.deposit_date DESC LIMIT 20");
    if ($stmt) {
        $stmt->bind_param('i', $customer_id);
        $stmt->execute();
        $stmt->bind_result($deposit_id, $type_id, $type_name, $bottle_size, $quantity, $amount, $deposit_date);
        while ($stmt->fetch()) {
            $deposits[] = [
                'deposit_id' => $deposit_id,
                'type_id' => $type_id,
                'bottle_type' => $type_name,
                'bottle_size' => $bottle_size,
                'quantity' => $quantity,
                'amount' => $amount,
                'deposit_date' => $deposit_date
            ];
        }
        $stmt->close();
    }

    // Fetch returns for this customer
    $stmt2 = $conn->prepare("SELECT r.return_id, r.type_id, b.type_name, b.bottle_size, r.quantity, r.return_date FROM returns r JOIN bottle_types b ON r.type_id = b.type_id WHERE r.customer_name = ? ORDER BY r.return_date DESC LIMIT 20");
    if ($stmt2) {
        $stmt2->bind_param('s', $cust);
        $stmt2->execute();
        $stmt2->bind_result($return_id, $type_id, $type_name, $bottle_size, $quantity, $return_date);
        while ($stmt2->fetch()) {
            $returns[] = [
                'return_id' => $return_id,
                'type_id' => $type_id,
                'bottle_type' => $type_name,
                'bottle_size' => $bottle_size,
                'quantity' => $quantity,
                'return_date' => $return_date
            ];
        }
        $stmt2->close();
    }

    echo json_encode(['deposits' => $deposits, 'returns' => $returns, 'customer' => $customerInfo]);
    $conn->close();
    exit;
}

// POST: legacy create deposit for API
$data = json_decode(file_get_contents('php://input'), true);

// Required: user_id, customer_name, type_id, quantity, amount
if (!$data || !isset($data['user_id']) || !isset($data['customer_name']) || !isset($data['type_id']) || !isset($data['quantity']) || !isset($data['amount'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid input']);
    exit;
}

$user_id = $data['user_id'];
$customer_name = trim($data['customer_name']);
$type_id = intval($data['type_id']);
$quantity = intval($data['quantity']);
$amount = floatval($data['amount']);

if ($amount <= 0 || $quantity <= 0) {
    echo json_encode(['success' => false, 'message' => 'Amount and quantity must be positive']);
    exit;
}

// Find or create customer by canonical name
$customer_id = null;
$find_cust = $conn->prepare("SELECT customer_id FROM customer WHERE canonical_name = ?");
if ($find_cust) {
    $find_cust->bind_param('s', $customer_name);
    $find_cust->execute();
    $find_cust->bind_result($cid);
    if ($find_cust->fetch()) {
        $customer_id = $cid;
    }
    $find_cust->close();
}
if (!$customer_id) {
    $add_cust = $conn->prepare("INSERT INTO customer (canonical_name) VALUES (?)");
    $add_cust->bind_param('s', $customer_name);
    if ($add_cust->execute()) {
        $customer_id = $add_cust->insert_id;
    }
    $add_cust->close();
}
if (!$customer_id) {
    echo json_encode(['success' => false, 'message' => 'Failed to find or create customer']);
    exit;
}

// Insert deposit
$stmt = $conn->prepare("INSERT INTO deposit (user_id, customer_id, type_id, quantity, amount, deposit_date) VALUES (?, ?, ?, ?, ?, NOW())");
$stmt->bind_param("iiiid", $user_id, $customer_id, $type_id, $quantity, $amount);
if ($stmt->execute()) {
    $deposit_id = $stmt->insert_id;
    // Log to stock_log
    $log_stmt = $conn->prepare("INSERT INTO stock_log (action_type, user_id, customer_id, type_id, quantity, amount, date_logged) VALUES ('Deposit', ?, ?, ?, ?, ?, NOW())");
    $log_stmt->bind_param("iiiid", $user_id, $customer_id, $type_id, $quantity, $amount);
    $log_stmt->execute();
    $log_stmt->close();
    echo json_encode(['success' => true, 'deposit_id' => $deposit_id]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to deposit']);
}

$stmt->close();
$conn->close();
?>
