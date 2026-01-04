<?php
$host = "localhost";
$user = "root";      // change if your MySQL username is different
$pass = "";          // change if your MySQL password is set
$db   = "bottlbankdb1";

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// ===================================
// AUTO-CREATE ADMIN ACCOUNT IF NOT EXISTS
// ===================================
$checkAdmin = $conn->query("SELECT user_id FROM user WHERE username='admin'");
if ($checkAdmin->num_rows === 0) {
    // This hash is for password 'admin123'
    $adminPass = '$2y$10$7w7n5zK2Cq9eF3xLZrTjHObR8o8EHV8nH9kzJY7b7n8mGJ6mF7K1y';
    $conn->query("INSERT INTO user (username,email,password) VALUES ('admin','admin@bottlebank.com','$adminPass')");
}
?>
