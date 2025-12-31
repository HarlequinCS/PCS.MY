-- ============================================
-- Database Update for Enhanced Checkout System
-- ============================================
-- This script updates the orders table to include additional fields
-- for full address, company info, and promotion code support

-- ============================================
-- STEP 1: Add New Columns to Orders Table
-- ============================================
ALTER TABLE `orders` 
ADD COLUMN IF NOT EXISTS `fullname` VARCHAR(255) NULL COMMENT 'Full name of customer' AFTER `name`,
ADD COLUMN IF NOT EXISTS `address1` VARCHAR(255) NULL COMMENT 'Address Line 1' AFTER `phone`,
ADD COLUMN IF NOT EXISTS `address2` VARCHAR(255) NULL COMMENT 'Address Line 2 (optional)' AFTER `address1`,
ADD COLUMN IF NOT EXISTS `address3` VARCHAR(255) NULL COMMENT 'Address Line 3 (optional)' AFTER `address2`,
ADD COLUMN IF NOT EXISTS `state` VARCHAR(100) NULL COMMENT 'State in Malaysia' AFTER `address3`,
ADD COLUMN IF NOT EXISTS `district` VARCHAR(100) NULL COMMENT 'District/City' AFTER `state`,
ADD COLUMN IF NOT EXISTS `postcode` VARCHAR(10) NULL COMMENT 'Postcode (5 digits)' AFTER `district`,
ADD COLUMN IF NOT EXISTS `promo_code` VARCHAR(50) NULL COMMENT 'Promotion code applied' AFTER `postcode`,
ADD COLUMN IF NOT EXISTS `discount_amount` DECIMAL(10,2) NULL DEFAULT 0.00 COMMENT 'Discount amount applied' AFTER `promo_code`;

-- ============================================
-- STEP 2: Create Promotions Table
-- ============================================
CREATE TABLE IF NOT EXISTS `promotions` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `code` VARCHAR(50) NOT NULL COMMENT 'Promotion code',
    `type` ENUM('percentage', 'fixed') NOT NULL COMMENT 'Discount type: percentage or fixed amount',
    `value` DECIMAL(10,2) NOT NULL COMMENT 'Discount value (percentage or fixed amount)',
    `active` TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Is promotion active (1=yes, 0=no)',
    `min_purchase` DECIMAL(10,2) NULL DEFAULT 0.00 COMMENT 'Minimum purchase amount to apply',
    `max_discount` DECIMAL(10,2) NULL DEFAULT NULL COMMENT 'Maximum discount amount (for percentage)',
    `usage_limit` INT(11) NULL DEFAULT NULL COMMENT 'Maximum number of times code can be used',
    `used_count` INT(11) NOT NULL DEFAULT 0 COMMENT 'Number of times code has been used',
    `valid_from` DATETIME NULL COMMENT 'Promotion valid from date',
    `valid_until` DATETIME NULL COMMENT 'Promotion valid until date',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Creation timestamp',
    `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP COMMENT 'Update timestamp',
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_code` (`code`),
    INDEX `idx_active` (`active`),
    INDEX `idx_valid_dates` (`valid_from`, `valid_until`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Promotion codes table';

-- ============================================
-- STEP 3: Insert Sample Promotion Codes
-- ============================================
-- Uncomment and modify these as needed for testing

-- 10% discount code
INSERT INTO `promotions` (`code`, `type`, `value`, `active`, `min_purchase`, `max_discount`, `valid_from`, `valid_until`) 
VALUES ('WELCOME10', 'percentage', 10.00, 1, 0.00, 50.00, NOW(), DATE_ADD(NOW(), INTERVAL 1 YEAR))
ON DUPLICATE KEY UPDATE `code` = `code`;

-- RM20 fixed discount
INSERT INTO `promotions` (`code`, `type`, `value`, `active`, `min_purchase`, `valid_from`, `valid_until`) 
VALUES ('SAVE20', 'fixed', 20.00, 1, 100.00, NOW(), DATE_ADD(NOW(), INTERVAL 1 YEAR))
ON DUPLICATE KEY UPDATE `code` = `code`;

-- 15% discount with minimum purchase
INSERT INTO `promotions` (`code`, `type`, `value`, `active`, `min_purchase`, `max_discount`, `valid_from`, `valid_until`) 
VALUES ('SUMMER15', 'percentage', 15.00, 1, 150.00, 75.00, NOW(), DATE_ADD(NOW(), INTERVAL 6 MONTH))
ON DUPLICATE KEY UPDATE `code` = `code`;

