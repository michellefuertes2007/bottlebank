-- Migration: Add customer table for history and fuzzy matching

CREATE TABLE IF NOT EXISTS `customer` (
  `customer_id` INT(11) NOT NULL AUTO_INCREMENT,
  `canonical_name` VARCHAR(100) NOT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`customer_id`),
  UNIQUE KEY `uniq_canonical_name` (`canonical_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Optionally, add a mapping table for alternate spellings
CREATE TABLE IF NOT EXISTS `customer_alias` (
  `alias_id` INT(11) NOT NULL AUTO_INCREMENT,
  `customer_id` INT(11) NOT NULL,
  `alias_name` VARCHAR(100) NOT NULL,
  PRIMARY KEY (`alias_id`),
  KEY `idx_customer_id` (`customer_id`),
  CONSTRAINT `fk_alias_customer` FOREIGN KEY (`customer_id`) REFERENCES `customer`(`customer_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
