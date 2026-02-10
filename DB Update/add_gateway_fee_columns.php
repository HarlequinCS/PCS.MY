<?php
/**
 * TEMPORARY WORKAROUND: Add Gateway Fee Tracking Columns
 * 
 * This script adds columns to track gateway fee absorption:
 * - gateway_fee: RM1.00 fee absorbed by company
 * - pushed_amount: Amount sent to ToyyibPay (original - RM1)
 * - net_received: Amount actually received (same as pushed_amount)
 * 
 * NOTE: This is a temporary workaround. Remove these columns when implementing
 * a proper fee handling system.
 */

define('DB_CONFIG_LOADED', true);
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/db.php';

try {
    $pdo = getDBConnection();
    
    // Check if columns already exist
    $columns = $pdo->query("SHOW COLUMNS FROM orders")->fetchAll(PDO::FETCH_COLUMN);
    
    // Add gateway_fee column if not exists
    if (!in_array('gateway_fee', $columns)) {
        $pdo->exec("
            ALTER TABLE `orders` 
            ADD COLUMN `gateway_fee` DECIMAL(10,2) DEFAULT 0.00 
            COMMENT 'Gateway fee absorbed by company (RM1.00) - TEMPORARY WORKAROUND'
            AFTER `final_amount`
        ");
        echo "✓ Added gateway_fee column\n";
    } else {
        echo "- gateway_fee column already exists\n";
    }
    
    // Add pushed_amount column if not exists
    if (!in_array('pushed_amount', $columns)) {
        $pdo->exec("
            ALTER TABLE `orders` 
            ADD COLUMN `pushed_amount` DECIMAL(10,2) DEFAULT NULL 
            COMMENT 'Amount sent to ToyyibPay (final_amount - gateway_fee) - TEMPORARY WORKAROUND'
            AFTER `gateway_fee`
        ");
        echo "✓ Added pushed_amount column\n";
    } else {
        echo "- pushed_amount column already exists\n";
    }
    
    // Add net_received column if not exists
    if (!in_array('net_received', $columns)) {
        $pdo->exec("
            ALTER TABLE `orders` 
            ADD COLUMN `net_received` DECIMAL(10,2) DEFAULT NULL 
            COMMENT 'Amount actually received from payment gateway - TEMPORARY WORKAROUND'
            AFTER `pushed_amount`
        ");
        echo "✓ Added net_received column\n";
    } else {
        echo "- net_received column already exists\n";
    }
    
    echo "\n✅ Database migration completed successfully!\n";
    echo "⚠️  REMINDER: These columns are part of a temporary workaround.\n";
    echo "   Remove them when implementing proper fee handling.\n";
    
} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
