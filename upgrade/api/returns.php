<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../includes/db_connect.php';

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
