-- Database Setup for Projek Cuti Sem
-- Run this SQL in your database to set up the required tables

-- Comments Table (if not exists, will be created by API)
CREATE TABLE IF NOT EXISTS `comment` (
  `comment_id` INT AUTO_INCREMENT PRIMARY KEY,
  `comment` TEXT NOT NULL,
  `ip_address` VARCHAR(45) DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `status` VARCHAR(20) DEFAULT 'approved',
  INDEX `idx_status` (`status`),
  INDEX `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Visitors Table (will be created automatically by API)
-- This table tracks unique visitors
CREATE TABLE IF NOT EXISTS `visitors` (
  `visitor_id` INT AUTO_INCREMENT PRIMARY KEY,
  `ip_address` VARCHAR(45) NOT NULL,
  `visit_date` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `user_agent` TEXT,
  UNIQUE KEY `unique_visit` (`ip_address`, `visit_date`),
  INDEX `idx_ip` (`ip_address`),
  INDEX `idx_date` (`visit_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

