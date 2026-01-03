<?php
/**
 * Database Update Script for Orders Table - Pickup/Delivery Feature
 * Run this once to add new columns: order_type, delivery_fee, final_amount
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/db.php';

try {
    $pdo = getDBConnection();
    
    echo "Starting database update for pickup/delivery feature...\n\n";
    
    // Check if columns already exist
    $columns = $pdo->query("SHOW COLUMNS FROM orders")->fetchAll(PDO::FETCH_COLUMN);
    
    // Add order_type column
    if (!in_array('order_type', $columns)) {
        echo "Adding order_type column...\n";
        $pdo->exec("ALTER TABLE `orders` 
            ADD COLUMN `order_type` ENUM('pickup', 'delivery') NULL DEFAULT 'delivery' 
            COMMENT 'Order type: pickup or delivery' 
            AFTER `postcode`");
        echo "✓ order_type column added\n\n";
    } else {
        echo "⚠ order_type column already exists, skipping...\n\n";
    }
    
    // Add delivery_fee column
    if (!in_array('delivery_fee', $columns)) {
        echo "Adding delivery_fee column...\n";
        $pdo->exec("ALTER TABLE `orders` 
            ADD COLUMN `delivery_fee` DECIMAL(10,2) NOT NULL DEFAULT 0.00 
            COMMENT 'Delivery fee in RM (0 for pickup)' 
            AFTER `order_type`");
        echo "✓ delivery_fee column added\n\n";
    } else {
        echo "⚠ delivery_fee column already exists, skipping...\n\n";
    }
    
    // Add final_amount column
    if (!in_array('final_amount', $columns)) {
        echo "Adding final_amount column...\n";
        $pdo->exec("ALTER TABLE `orders` 
            ADD COLUMN `final_amount` DECIMAL(10,2) NULL 
            COMMENT 'Final amount including delivery fee and discount' 
            AFTER `amount`");
        echo "✓ final_amount column added\n\n";
        
        // Update existing records
        echo "Updating existing records...\n";
        $pdo->exec("UPDATE `orders` 
            SET `final_amount` = `amount`, 
                `order_type` = COALESCE(`order_type`, 'delivery'),
                `delivery_fee` = COALESCE(`delivery_fee`, 0.00)
            WHERE `final_amount` IS NULL");
        echo "✓ Existing records updated\n\n";
        
        // Make final_amount NOT NULL after updating existing records
        echo "Making final_amount NOT NULL...\n";
        $pdo->exec("ALTER TABLE `orders` 
            MODIFY COLUMN `final_amount` DECIMAL(10,2) NOT NULL 
            COMMENT 'Final amount including delivery fee and discount'");
        echo "✓ final_amount set to NOT NULL\n\n";
    } else {
        echo "⚠ final_amount column already exists, skipping...\n\n";
    }
    
    echo "✓ Database update completed successfully!\n";
    echo "\nNew columns added:\n";
    echo "  - order_type (ENUM: 'pickup', 'delivery')\n";
    echo "  - delivery_fee (DECIMAL 10,2)\n";
    echo "  - final_amount (DECIMAL 10,2)\n";
    
} catch (PDOException $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
    echo "Error Code: " . $e->getCode() . "\n";
    exit(1);
}

