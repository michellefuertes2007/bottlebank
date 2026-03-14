-- Migration: Add type_id to deposit and returns tables

ALTER TABLE `deposit` ADD COLUMN `type_id` INT(11) DEFAULT NULL AFTER `bottle_type`;
ALTER TABLE `returns` ADD COLUMN `type_id` INT(11) DEFAULT NULL AFTER `bottle_type`;

-- Optionally, update existing records if you can match bottle_type and bottle_size to type_id
-- Example:
-- UPDATE deposit d JOIN bottle_types bt ON d.bottle_type = bt.type_name AND d.bottle_size = bt.bottle_size SET d.type_id = bt.type_id;
-- UPDATE returns r JOIN bottle_types bt ON r.bottle_type = bt.type_name AND r.bottle_size = bt.bottle_size SET r.type_id = bt.type_id;

-- Add foreign key constraints if desired
-- ALTER TABLE `deposit` ADD CONSTRAINT `fk_deposit_type_id` FOREIGN KEY (`type_id`) REFERENCES `bottle_types`(`type_id`) ON DELETE SET NULL;
-- ALTER TABLE `returns` ADD CONSTRAINT `fk_returns_type_id` FOREIGN KEY (`type_id`) REFERENCES `bottle_types`(`type_id`) ON DELETE SET NULL;
