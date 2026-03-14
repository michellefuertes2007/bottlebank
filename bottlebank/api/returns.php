<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../includes/db_connect.php';

// GET: fetch available bottles for return by customer name

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['customer'])) {
    $cust = trim($_GET['customer']);
    $availableBottles = [];
    $totalDepositAmount = 0;
    $totalRefundedAmount = 0;

    // Find customer_id by canonical name
    $customer_id = null;
    $find_cust = $conn->prepare("SELECT customer_id FROM customer WHERE canonical_name = ?");
    if ($find_cust) {
        $find_cust->bind_param('s', $cust);
        $find_cust->execute();
        $find_cust->bind_result($cid);
        if ($find_cust->fetch()) {
            $customer_id = $cid;
        }
        $find_cust->close();
    }
    if (!$customer_id) {
        echo json_encode(['available_bottles' => [], 'error' => 'Customer not found']);
        $conn->close();
        exit;
    }

    // Get all deposits for this customer by type_id
    $deposits_query = $conn->prepare("SELECT type_id, quantity, amount FROM deposit WHERE customer_id = ?");
    $deposited = [];
    if ($deposits_query) {
        $deposits_query->bind_param('i', $customer_id);
        $deposits_query->execute();
        $deposits_query->bind_result($type_id, $qty, $amt);
        while ($deposits_query->fetch()) {
            if (!isset($deposited[$type_id])) {
                $deposited[$type_id] = ['qty' => 0, 'total_value' => 0];
            }
            $deposited[$type_id]['qty'] += $qty;
            $deposited[$type_id]['total_value'] += $amt;
            $totalDepositAmount += $amt;
        }
        $deposits_query->close();
    }

    // Get all returns for this customer by type_id (returns table stores customer_name)
    $returns_query = $conn->prepare("SELECT type_id, SUM(quantity) as total_returned FROM returns WHERE customer_name = ? GROUP BY type_id");
    $returned = [];
    if ($returns_query) {
        $returns_query->bind_param('s', $cust);
        $returns_query->execute();
        $returns_query->bind_result($type_id, $total_returned);
        while ($returns_query->fetch()) {
            $returned[$type_id] = intval($total_returned);
        }
        $returns_query->close();
    }

    // Get bottle type info for display
    foreach ($deposited as $type_id => $info) {
        $qty_deposited = $info['qty'];
        $qty_returned = $returned[$type_id] ?? 0;
        $available_qty = $qty_deposited - $qty_returned;
        if ($available_qty > 0) {
            // Get bottle type details
            $type_stmt = $conn->prepare("SELECT type_name, bottle_size FROM bottle_types WHERE type_id = ?");
            $type_stmt->bind_param('i', $type_id);
            $type_stmt->execute();
            $type_stmt->bind_result($type_name, $bottle_size);
            if ($type_stmt->fetch()) {
                $availableBottles[] = [
                    'type_id' => $type_id,
                    'bottle_type' => $type_name,
                    'bottle_size' => $bottle_size,
                    'available_qty' => $available_qty,
                    'deposited_qty' => $qty_deposited,
                    'returned_qty' => $qty_returned
                ];
            }
            $type_stmt->close();
        }
    }

    // Compute total returned value (based on bottle_types pricing) to determine how much refund remains.
    $totalReturnedAmount = 0;
    $returns_value_stmt = $conn->prepare(
        "SELECT SUM(r.quantity * COALESCE(b.price_per_bottle, 0)) AS total_returned " .
        "FROM returns r LEFT JOIN bottle_types b ON r.type_id = b.type_id " .
        "WHERE r.customer_name = ?"
    );
    if ($returns_value_stmt) {
        $returns_value_stmt->bind_param('s', $cust);
        $returns_value_stmt->execute();
        $returns_value_stmt->bind_result($totalReturnedAmount);
        $returns_value_stmt->fetch();
        $returns_value_stmt->close();

        // Ensure value is numeric to avoid PHP 8.1+ deprecation warnings when no rows exist.
        if ($totalReturnedAmount === null) {
            $totalReturnedAmount = 0;
        }
    }

    $totalDepositAmount = round($totalDepositAmount, 2);
    $totalReturnedAmount = round($totalReturnedAmount, 2);
    $remainingRefund = max(0, $totalDepositAmount - $totalReturnedAmount);

    echo json_encode([
        'available_bottles' => $availableBottles,
        'total_deposit_amount' => $totalDepositAmount,
        'total_returned_amount' => $totalReturnedAmount,
        'remaining_refund_amount' => $remainingRefund
    ]);
    $conn->close();
    exit;
}

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

$stmt = $conn->prepare("INSERT INTO returns (user_id, amount, date) VALUES (?, ?, NOW())");
$stmt->bind_param("id", $user_id, $amount);
if ($stmt->execute()) {
    $return_id = $stmt->insert_id;
    // Log to stock_log
    $log_stmt = $conn->prepare("INSERT INTO stock_log (action, details, user_id, timestamp) VALUES ('return', ?, ?, NOW())");
    $details = "Return of $amount by user $user_id";
    $log_stmt->bind_param("si", $details, $user_id);
    $log_stmt->execute();
    $log_stmt->close();
    echo json_encode(['success' => true, 'return_id' => $return_id]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to return']);
}

$stmt->close();
$conn->close();
?>
