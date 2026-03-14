-- BottleBank database schema
-- Generated: 2026-02-04
-- Import this file into MySQL/MariaDB to create the database and tables.

CREATE DATABASE IF NOT EXISTS `bottlebank` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `bottlebank`;

-- users
CREATE TABLE IF NOT EXISTS `user` (
  `user_id` INT(11) NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(50) NOT NULL UNIQUE,
  `email` VARCHAR(100) NOT NULL UNIQUE DEFAULT '',
  `password` VARCHAR(255) NOT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `force_change` TINYINT(1) NOT NULL DEFAULT 0,
  `role` ENUM('user','admin') NOT NULL DEFAULT 'user',
  PRIMARY KEY (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- bottle types (with sizes and pricing for multi-bottle deposits)
CREATE TABLE IF NOT EXISTS `bottle_types` (
  `type_id` INT(11) NOT NULL AUTO_INCREMENT,
  `type_name` VARCHAR(100) NOT NULL,
  `bottle_size` VARCHAR(20) DEFAULT 'small',
  `price_per_bottle` DECIMAL(10, 2) DEFAULT 0.00,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `created_by` INT(11) DEFAULT NULL,
  PRIMARY KEY (`type_id`),
  UNIQUE KEY `uniq_type_size` (`type_name`,`bottle_size`),
  KEY `idx_created_by` (`created_by`),
  CONSTRAINT `fk_bottle_created_by` FOREIGN KEY (`created_by`) REFERENCES `user`(`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Sample bottle types with sizes and pricing
INSERT INTO `bottle_types` (`type_name`, `bottle_size`, `price_per_bottle`) VALUES
  ('coke', '8oz', 15.00),
  ('sprite', '8oz', 12.00),
  ('royal', '500ml', 20.00),
  ('red horse', '500ml', 55.00)
ON DUPLICATE KEY UPDATE `bottle_size` = VALUES(`bottle_size`), `price_per_bottle` = VALUES(`price_per_bottle`);

-- deposit records
CREATE TABLE IF NOT EXISTS `deposit` (
  `deposit_id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) NOT NULL,
  `customer_name` VARCHAR(100) DEFAULT NULL,
  `bottle_type` VARCHAR(50) DEFAULT NULL,
  `quantity` INT(11) NOT NULL,
  `with_case` TINYINT(1) NOT NULL DEFAULT 0,
  `case_quantity` INT(11) DEFAULT 0,
  `amount` DECIMAL(10,2) DEFAULT 0.00,
  `deposit_date` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`deposit_id`),
  KEY `idx_deposit_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- returns
CREATE TABLE IF NOT EXISTS `returns` (
  `return_id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) NOT NULL,
  `customer_name` VARCHAR(100) DEFAULT NULL,
  `bottle_type` VARCHAR(50) DEFAULT NULL,
  `quantity` INT(11) NOT NULL,
  `with_case` TINYINT(1) NOT NULL DEFAULT 0,
  `case_quantity` INT(11) DEFAULT 0,
  `bottle_size` VARCHAR(10) DEFAULT 'small',
  `return_date` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`return_id`),
  KEY `idx_returns_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- refunds
CREATE TABLE IF NOT EXISTS `refund` (
  `refund_id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) NOT NULL,
  `customer_name` VARCHAR(100) DEFAULT NULL,
  `amount` DECIMAL(10,2) NOT NULL,
  `refund_date` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`refund_id`),
  KEY `idx_refund_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- stock log (audit trail)
CREATE TABLE IF NOT EXISTS `stock_log` (
  `log_id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) NOT NULL,
  `action_type` VARCHAR(50) NOT NULL,
  `customer_name` VARCHAR(100) DEFAULT NULL,
  `bottle_type` VARCHAR(50) DEFAULT NULL,
  `quantity` INT(11) DEFAULT NULL,
  `amount` DECIMAL(10,2) DEFAULT NULL,
  `details` VARCHAR(255) DEFAULT 'Successfully recorded',
  `with_case` TINYINT(1) DEFAULT 0,
  `case_quantity` INT(11) DEFAULT 0,
  `bottle_size` VARCHAR(10) DEFAULT 'small',
  `date_logged` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`log_id`),
  KEY `idx_stock_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- password change log
CREATE TABLE IF NOT EXISTS `password_log` (
  `log_id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) NOT NULL,
  `changed_by_id` INT(11) DEFAULT NULL,
  `change_type` VARCHAR(50) NOT NULL DEFAULT 'Self',
  `changed_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`log_id`),
  KEY `idx_pwlog_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;