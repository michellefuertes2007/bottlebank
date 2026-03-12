-- Migration: Add size and price columns to bottle_types table
-- This allows storing complete bottle information: name, size, price

-- Add columns if they don't exist
ALTER TABLE `bottle_types` ADD COLUMN `bottle_size` VARCHAR(20) DEFAULT 'small' AFTER `type_name`;
ALTER TABLE `bottle_types` ADD COLUMN `price_per_bottle` DECIMAL(10, 2) DEFAULT 0.00 AFTER `bottle_size`;

-- Update existing bottles with sample data
-- Coke: 8oz size at ₱15 per bottle
UPDATE `bottle_types` SET `bottle_size` = '8oz', `price_per_bottle` = 15.00 WHERE `type_name` = 'coke';

-- Sprite: 8oz size at ₱12 per bottle  
UPDATE `bottle_types` SET `bottle_size` = '8oz', `price_per_bottle` = 12.00 WHERE `type_name` = 'sprite';

-- Royal: 500ml size at ₱20 per bottle
UPDATE `bottle_types` SET `bottle_size` = '500ml', `price_per_bottle` = 20.00 WHERE `type_name` = 'royal';

-- Add sample: Red Horse 500ml at ₱55 per bottle (if not exists)
INSERT INTO `bottle_types` (`type_name`, `bottle_size`, `price_per_bottle`) 
VALUES ('red horse', '500ml', 55.00)
ON DUPLICATE KEY UPDATE `bottle_size` = VALUES(`bottle_size`), `price_per_bottle` = VALUES(`price_per_bottle`);
