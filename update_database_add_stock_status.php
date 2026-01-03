<?php
/**
 * Database Update Script: Add stock_status column to orders table
 * Run this once to add stock_status column
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/db.php';

try {
    $pdo = getDBConnection();
    
    echo "Starting database update to add stock_status column...\n\n";
    
    // Check if column already exists
    $columns = $pdo->query("SHOW COLUMNS FROM orders")->fetchAll(PDO::FETCH_COLUMN);
    
    // Add stock_status column
    if (!in_array('stock_status', $columns)) {
        echo "Adding stock_status column...\n";
        $pdo->exec("ALTER TABLE `orders` 
            ADD COLUMN `stock_status` ENUM('ready', 'out_of_stock', 'pre_order') NULL DEFAULT 'ready' 
            COMMENT 'Stock status: ready, out_of_stock, pre_order' 
            AFTER `status`");
        echo "✓ stock_status column added\n\n";
        
        // Update existing records to have default stock_status
        echo "Updating existing records...\n";
        $pdo->exec("UPDATE `orders` 
            SET `stock_status` = 'ready'
            WHERE `stock_status` IS NULL");
        echo "✓ Existing records updated\n\n";
    } else {
        echo "⚠ stock_status column already exists, skipping...\n\n";
    }
    
    echo "✓ Database update completed successfully!\n";
    echo "\nNew column added:\n";
    echo "  - stock_status (ENUM: 'ready', 'out_of_stock', 'pre_order')\n";
    
} catch (PDOException $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
    echo "Error Code: " . $e->getCode() . "\n";
    exit(1);
}

