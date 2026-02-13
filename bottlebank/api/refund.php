<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../includes/db_connect.php';

$data = json_decode(file_get_contents('php://input'), true);

// check if all required fields are there
if (!$data || !isset($data['user_id']) || !isset($data['amount']) || !isset($data['customer_name'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid input']);
    exit;
}

$user_id = $data['user_id'];
$amount = $data['amount'];
$cust_name = trim($data['customer_name']);

if ($amount <= 0) {
    echo json_encode(['success' => false, 'message' => 'Amount must be positive']);
    exit;
}

if (empty($cust_name)) {
    echo json_encode(['success' => false, 'message' => 'Customer name is required']);
    exit;
}

// insert the refund
$stmt = $conn->prepare("INSERT INTO refund (user_id, customer_name, amount, refund_date) VALUES (?, ?, ?, NOW())");
$stmt->bind_param("isd", $user_id, $cust_name, $amount);
if ($stmt->execute()) {
    $refund_id = $stmt->insert_id;
    // log it
    $log_stmt = $conn->prepare("INSERT INTO stock_log (user_id, action_type, customer_name, amount) VALUES (?, 'Refund', ?, ?)");
    $log_stmt->bind_param("isd", $user_id, $cust_name, $amount);
    $log_stmt->execute();
    $log_stmt->close();
    echo json_encode(['success' => true, 'refund_id' => $refund_id]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to refund']);
}

$stmt->close();
$conn->close();
?>
