<?php
require 'includes/db_connect.php';

$migrations = [
  "ALTER TABLE `bottle_types` ADD COLUMN `bottle_size` VARCHAR(20) DEFAULT 'small' AFTER `type_name`",
  "ALTER TABLE `bottle_types` ADD COLUMN `price_per_bottle` DECIMAL(10, 2) DEFAULT 0.00 AFTER `bottle_size`",
  "UPDATE `bottle_types` SET `bottle_size` = '8oz', `price_per_bottle` = 15.00 WHERE `type_name` = 'coke'",
  "UPDATE `bottle_types` SET `bottle_size` = '8oz', `price_per_bottle` = 12.00 WHERE `type_name` = 'sprite'",
  "UPDATE `bottle_types` SET `bottle_size` = '500ml', `price_per_bottle` = 20.00 WHERE `type_name` = 'royal'",
  "INSERT INTO `bottle_types` (`type_name`, `bottle_size`, `price_per_bottle`) VALUES ('red horse', '500ml', 55.00) ON DUPLICATE KEY UPDATE `bottle_size` = VALUES(`bottle_size`), `price_per_bottle` = VALUES(`price_per_bottle`)"
  , "-- Adjust unique constraint to allow same name with different sizes",
  "ALTER TABLE `bottle_types` DROP INDEX `type_name`",
  "ALTER TABLE `bottle_types` ADD UNIQUE KEY `uniq_type_size` (`type_name`,`bottle_size`)"
];

foreach ($migrations as $sql) {
  try {
    if ($conn->query($sql)) {
      echo "✓ Executed: " . substr($sql, 0, 60) . "...\n";
    } else {
      echo "⚠ Skipped (may exist): " . substr($sql, 0, 60) . "...\n";
    }
  } catch (Exception $e) {
    echo "⚠ Skipped (may exist): " . substr($sql, 0, 60) . "...\n";
  }
}

echo "\n✓ Migration complete! Current bottles:\n";
$result = $conn->query('SELECT type_id, type_name, bottle_size, price_per_bottle FROM bottle_types ORDER BY type_id');
while ($row = $result->fetch_assoc()) {
  echo "  {$row['type_name']} {$row['bottle_size']} - ₱{$row['price_per_bottle']}\n";
}

$conn->close();
?>
