<?php
$host = "localhost";
$user = "root";      // change if your MySQL username is different
$pass = "";          // change if your MySQL password is set
$db   = "upgrade";

$conn = mysqli_connect($host, $user, $pass, $db);
if (!$conn) {
    die('Database connection failed. Start MySQL (XAMPP) and verify credentials. Error: ' . mysqli_connect_error());
}


?>
