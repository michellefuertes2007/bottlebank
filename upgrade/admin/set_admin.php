<?php
require '../includes/db_connect.php';

$hash = '$2y$10$7w7n5zK2Cq9eF3xLZrTjHObR8o8EHV8nH9kzJY7b7n8mGJ6mF7K1y';

// Ensure force_change column exists before updating
$col = $conn->query("SHOW COLUMNS FROM user LIKE 'force_change'");
if ($col && $col->num_rows === 0) {
	$conn->query("ALTER TABLE user ADD COLUMN force_change TINYINT(1) NOT NULL DEFAULT 0");
}

$res = $conn->query("UPDATE user SET password='{$conn->real_escape_string($hash)}', force_change=0, role='admin' WHERE username='admin'");
if ($res) {
	echo 'done';
} else {
	echo 'error: ' . $conn->error;
}
?>
