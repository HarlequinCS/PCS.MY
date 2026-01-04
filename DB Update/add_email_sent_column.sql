-- ============================================
-- Add email_sent column to orders table
-- ============================================
-- This column prevents duplicate email sending
-- Run this SQL script before deploying email functionality

ALTER TABLE `orders` 
ADD COLUMN `email_sent` TINYINT(1) NOT NULL DEFAULT 0 
COMMENT 'Email sent flag: 0 = not sent, 1 = sent' 
AFTER `status`;

-- Add index for better query performance
ALTER TABLE `orders`
ADD INDEX `idx_email_sent` (`email_sent`);

-- Verify the column was added
SELECT COLUMN_NAME, DATA_TYPE, IS_NULLABLE, COLUMN_DEFAULT, COLUMN_COMMENT 
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_SCHEMA = DATABASE() 
AND TABLE_NAME = 'orders' 
AND COLUMN_NAME = 'email_sent';

