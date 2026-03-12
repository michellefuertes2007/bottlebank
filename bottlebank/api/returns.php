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
    
    // Get all deposits for this customer from stock_log
    $deposits_query = $conn->prepare("
        SELECT bottle_type, quantity, details, amount FROM stock_log 
        WHERE action_type='Deposit' AND customer_name = ? 
        ORDER BY date_logged DESC
    ");
    
    if ($deposits_query) {
        $deposits_query->bind_param('s', $cust);
        $deposits_query->execute();
        $deposits_result = $deposits_query->get_result();
        
        // Parse deposits to get bottle details and amount
        $deposited = [];
        while ($row = $deposits_result->fetch_assoc()) {
            // Extract bottle info from details field
            // Format: "Deposit — 20x coke 8oz (₱15.00) + 10x red horse 500ml (₱55.00) | Total: ₱850.00"
            if (preg_match_all('/(\d+)x\s+(\w+(?:\s+\w+)?)\s+([^(]+)\s*\(₱([\d,]+\.\d{2})\)/', $row['details'], $matches)) {
                for ($i = 0; $i < count($matches[0]); $i++) {
                    $bottle_type = trim($matches[2][$i]);
                    $bottle_size = trim($matches[3][$i]);
                    $qty = intval($matches[1][$i]);
                    $price = floatval(str_replace(',', '', $matches[4][$i]));
                    
                    if (!isset($deposited[$bottle_type])) {
                        $deposited[$bottle_type] = ['size' => $bottle_size, 'qty' => 0, 'total_value' => 0];
                    }
                    $deposited[$bottle_type]['qty'] += $qty;
                    $deposited[$bottle_type]['total_value'] += ($qty * $price);
                }
            }
            // Add the deposit amount
            if (isset($row['amount']) && $row['amount']) {
                $totalDepositAmount += floatval($row['amount']);
            }
        }
        $deposits_query->close();
        
        // Get all refunds for this customer
        $refunds_query = $conn->prepare("
            SELECT SUM(amount) as total_refunded FROM stock_log 
            WHERE action_type='Refund' AND customer_name = ?
        ");
        
        if ($refunds_query) {
            $refunds_query->bind_param('s', $cust);
            $refunds_query->execute();
            $refunds_result = $refunds_query->get_result();
            if ($refund_row = $refunds_result->fetch_assoc()) {
                $totalRefundedAmount = floatval($refund_row['total_refunded'] ?? 0);
            }
            $refunds_query->close();
        }
        
        // Get all returns for this customer
        $returns_query = $conn->prepare("
            SELECT bottle_type, SUM(quantity) as total_returned FROM stock_log 
            WHERE action_type='Return' AND customer_name = ? AND bottle_type IS NOT NULL
            GROUP BY bottle_type
        ");
        
        if ($returns_query) {
            $returns_query->bind_param('s', $cust);
            $returns_query->execute();
            $returns_result = $returns_query->get_result();
            
            $returned = [];
            while ($row = $returns_result->fetch_assoc()) {
                $returned[$row['bottle_type']] = intval($row['total_returned']);
            }
            $returns_query->close();
            
            // Calculate available bottles (deposits - returns) and refund amount
            foreach ($deposited as $bottle_type => $info) {
                $available_qty = $info['qty'] - ($returned[$bottle_type] ?? 0);
                if ($available_qty > 0) {
                    $availableBottles[] = [
                        'bottle_type' => $bottle_type,
                        'bottle_size' => $info['size'],
                        'available_qty' => $available_qty,
                        'deposited_qty' => $info['qty'],
                        'returned_qty' => $returned[$bottle_type] ?? 0
                    ];
                }
            }
        }
    }
    
    // Calculate remaining refund amount
    $remainingRefundAmount = $totalDepositAmount - $totalRefundedAmount;
    
    echo json_encode([
        'available_bottles' => $availableBottles,
        'total_deposit_amount' => round($totalDepositAmount, 2),
        'total_refunded_amount' => round($totalRefundedAmount, 2),
        'remaining_refund_amount' => round(max(0, $remainingRefundAmount), 2)
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
