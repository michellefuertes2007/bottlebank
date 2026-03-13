<?php
require 'includes/db_connect.php';
echo "Current bottle_types in database:\n";
$result = $conn->query('SELECT type_id, type_name FROM bottle_types ORDER BY type_id');
while ($row = $result->fetch_assoc()) {
  echo "  ID {$row['type_id']}: {$row['type_name']}\n";
}
$conn->close();
?>