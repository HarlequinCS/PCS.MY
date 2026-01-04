-- ============================================
-- Create Settings Table for ToyyibPay Mode Toggle
-- ============================================
-- This table stores system settings like ToyyibPay mode (staging/production)

CREATE TABLE IF NOT EXISTS `settings` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `setting_key` VARCHAR(100) NOT NULL COMMENT 'Setting key (e.g., toyyibpay_mode, toyyibpay_production_secret, etc.)',
    `setting_value` TEXT NULL COMMENT 'Setting value',
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='System settings table';

-- Insert default settings
INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES
('toyyibpay_mode', 'staging'),
('toyyibpay_production_secret_key', ''),
('toyyibpay_production_category_code', '')
ON DUPLICATE KEY UPDATE `setting_key` = `setting_key`;

