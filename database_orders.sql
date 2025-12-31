-- ============================================
-- SQL Script: Create Orders Table for ToyyibPay Staging
-- ============================================
-- Table untuk menyimpan order baju dengan variant dan size
-- Run script ini untuk create table sebelum menggunakan order page

CREATE TABLE IF NOT EXISTS `orders` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(255) NOT NULL COMMENT 'Nama user (billTo)',
    `email` VARCHAR(255) NOT NULL COMMENT 'Email user (billEmail)',
    `phone` VARCHAR(50) NOT NULL COMMENT 'Phone user (billPhone)',
    `variant` VARCHAR(50) NOT NULL COMMENT 'Variant: hitamdepan, hijaufront, hijaudepan',
    `size` VARCHAR(10) NOT NULL COMMENT 'Size: S, M, L, XL, XXL',
    `quantity` INT(11) NOT NULL DEFAULT 1 COMMENT 'Quantity ordered',
    `amount` DECIMAL(10,2) NOT NULL COMMENT 'Total amount in RM',
    `billExternalReferenceNo` VARCHAR(255) NOT NULL COMMENT 'Unique reference number untuk ToyyibPay',
    `status` VARCHAR(50) NOT NULL DEFAULT 'pending' COMMENT 'Order status: pending, paid, failed',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Order creation timestamp',
    PRIMARY KEY (`id`),
    INDEX `idx_billExternalReferenceNo` (`billExternalReferenceNo`),
    INDEX `idx_status` (`status`),
    INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Orders table for ToyyibPay staging payment';

