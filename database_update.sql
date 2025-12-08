-- Database Update for Comment Moderation
-- Run this SQL to update your comment table for admin moderation

-- Add ip_address column if it doesn't exist
ALTER TABLE `comment` 
ADD COLUMN IF NOT EXISTS `ip_address` VARCHAR(45) DEFAULT NULL AFTER `comment`;

-- Update default status to 'pending' for new comments
ALTER TABLE `comment` 
MODIFY COLUMN `status` VARCHAR(20) DEFAULT 'pending';

-- Update existing comments to 'approved' if they don't have a status
UPDATE `comment` SET `status` = 'approved' WHERE `status` IS NULL OR `status` = '';

-- Add index for better performance
ALTER TABLE `comment` 
ADD INDEX IF NOT EXISTS `idx_status` (`status`),
ADD INDEX IF NOT EXISTS `idx_created` (`created_at`);

