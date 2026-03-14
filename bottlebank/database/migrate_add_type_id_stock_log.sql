-- Migration: Add type_id to stock_log table for full bottle type tracking

ALTER TABLE `stock_log` ADD COLUMN `type_id` INT(11) DEFAULT NULL AFTER `bottle_type`;

-- Optionally, update existing records if you can match bottle_type and bottle_size to type_id
-- Example:
-- UPDATE stock_log s JOIN bottle_types bt ON s.bottle_type = bt.type_name AND s.bottle_size = bt.bottle_size SET s.type_id = bt.type_id;

-- Add foreign key constraint if desired
-- ALTER TABLE `stock_log` ADD CONSTRAINT `fk_stocklog_type_id` FOREIGN KEY (`type_id`) REFERENCES `bottle_types`(`type_id`) ON DELETE SET NULL;
