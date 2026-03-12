<?php
// Suppress warnings/notices that could break JSON output
error_reporting(E_ALL);
ini_set('display_errors', '0');
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../includes/db_connect.php';

// GET: fetch deposits and returns by customer name (recent entries)
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['customer'])) {
    $cust = trim($_GET['customer']);
    $deposits = [];
    $returns = [];
    
    // Fetch latest deposit entry from stock_log for this customer
    $s1 = $conn->prepare("SELECT bottle_type, quantity, amount, with_case, case_quantity, details, date_logged as date FROM stock_log WHERE action_type='Deposit' AND customer_name = ? ORDER BY date_logged DESC LIMIT 5");
    if ($s1) {
        $s1->bind_param('s', $cust);
        $s1->execute();
        $r1 = $s1->get_result();
        while ($row = $r1->fetch_assoc()) {
            $deposits[] = $row;
        }
        $s1->close();
    }

    // Fetch returns and refunds from stock_log
    // return entries may include refunds which record an amount instead of bottle info
    $s2 = $conn->prepare("SELECT bottle_type, quantity, with_case, case_quantity, amount, date_logged as date FROM stock_log WHERE action_type IN ('Return','Refund') AND customer_name = ? ORDER BY date_logged DESC LIMIT 5");
    if ($s2) {
        $s2->bind_param('s', $cust);
        $s2->execute();
        $r2 = $s2->get_result();
        while ($row = $r2->fetch_assoc()) {
            $returns[] = $row;
        }
        $s2->close();
    }

    // also fetch canonical customer info if available
    $customerInfo = null;
    $cstmt = $conn->prepare("SELECT bottle_type, quantity, amount, with_case, case_quantity, bottle_size, last_deposit FROM customers WHERE customer_name = ? LIMIT 1");
    if ($cstmt) {
        $cstmt->bind_param('s', $cust);
        $cstmt->execute();
        $cres = $cstmt->get_result();
        if ($cres && $cres->num_rows) {
            $customerInfo = $cres->fetch_assoc();
        }
        $cstmt->close();
    }

    echo json_encode(['deposits' => $deposits, 'returns' => $returns, 'customer' => $customerInfo]);
    $conn->close();
    exit;
}

// POST: legacy create deposit for API
$data = json_decode(file_get_contents('php://input'), true);

if (!$data || !isset($data['user_id']) || !isset($data['amount'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid input']);
    exit;
}

$user_id = $data['user_id'];
$amount = $data['amount'];

if ($amount <= 0) {
    echo json_encode(['success' => false, 'message' => 'Amount must be positive']);
    exit;
}

$stmt = $conn->prepare("INSERT INTO deposit (user_id, amount, date) VALUES (?, ?, NOW())");
$stmt->bind_param("id", $user_id, $amount);
if ($stmt->execute()) {
    $deposit_id = $stmt->insert_id;
    // Log to stock_log
    $log_stmt = $conn->prepare("INSERT INTO stock_log (action, details, user_id, timestamp) VALUES ('deposit', ?, ?, NOW())");
    $details = "Deposit of $amount by user $user_id";
    $log_stmt->bind_param("si", $details, $user_id);
    $log_stmt->execute();
    $log_stmt->close();
    echo json_encode(['success' => true, 'deposit_id' => $deposit_id]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to deposit']);
}

$stmt->close();
$conn->close();
?>
