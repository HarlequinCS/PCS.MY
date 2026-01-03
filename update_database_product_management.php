<?php
/**
 * Database Migration Script for Product Management
 * Run this once to set up product management tables and initialize default product
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/db.php';

try {
    $pdo = getDBConnection();
    
    echo "Starting database migration for Product Management...\n\n";
    
    // Create products table
    echo "Creating products table...\n";
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `products` (
          `id` INT(11) NOT NULL AUTO_INCREMENT,
          `name` VARCHAR(255) NOT NULL,
          `price` DECIMAL(10,2) NOT NULL DEFAULT 50.00,
          `stock_status` ENUM('in_stock', 'out_of_stock', 'pre_order') NOT NULL DEFAULT 'pre_order',
          `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
          `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "✓ products table created\n\n";
    
    // Create product_variants table
    echo "Creating product_variants table...\n";
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `product_variants` (
          `id` INT(11) NOT NULL AUTO_INCREMENT,
          `product_id` INT(11) NOT NULL,
          `variant_key` VARCHAR(100) NOT NULL COMMENT 'e.g., merahdepan, hijaudepan',
          `variant_name` VARCHAR(255) NOT NULL COMMENT 'Display name',
          `variant_image` VARCHAR(500) NOT NULL,
          `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
          `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          UNIQUE KEY `product_variant_key` (`product_id`, `variant_key`),
          FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "✓ product_variants table created\n\n";
    
    // Create product_inventory table
    echo "Creating product_inventory table...\n";
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `product_inventory` (
          `id` INT(11) NOT NULL AUTO_INCREMENT,
          `variant_id` INT(11) NOT NULL,
          `size` VARCHAR(10) NOT NULL COMMENT 'S, M, L, XL, XXL',
          `quantity` INT(11) NOT NULL DEFAULT 0,
          `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
          `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          UNIQUE KEY `variant_size` (`variant_id`, `size`),
          FOREIGN KEY (`variant_id`) REFERENCES `product_variants`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "✓ product_inventory table created\n\n";
    
    // Initialize default product if it doesn't exist
    echo "Checking for default product...\n";
    $stmt = $pdo->query("SELECT id FROM products LIMIT 1");
    $existingProduct = $stmt->fetch();
    
    if (!$existingProduct) {
        echo "Initializing default product...\n";
        
        // Insert default product
        $stmt = $pdo->prepare("
            INSERT INTO products (name, price, stock_status) 
            VALUES ('Japanese Drill Half-Zip Workshirt', 50.00, 'pre_order')
        ");
        $stmt->execute();
        $productId = $pdo->lastInsertId();
        
        echo "✓ Default product created (ID: $productId)\n\n";
        
        // Insert variants (matching order-baju.php)
        $variants = [
            ['hitamdepan', 'Hitam', 'images/hitamdepan.svg'],
            ['hijaufront', 'Hijau', 'images/hijaufront.svg'],
            ['merahdepan', 'Merah', 'images/merahdepan.svg']
        ];
        
        echo "Adding product variants...\n";
        $variantStmt = $pdo->prepare("
            INSERT INTO product_variants (product_id, variant_key, variant_name, variant_image)
            VALUES (:product_id, :variant_key, :variant_name, :variant_image)
        ");
        
        $variantIds = [];
        foreach ($variants as $variant) {
            $variantStmt->execute([
                ':product_id' => $productId,
                ':variant_key' => $variant[0],
                ':variant_name' => $variant[1],
                ':variant_image' => $variant[2]
            ]);
            $variantIds[$variant[0]] = $pdo->lastInsertId();
            echo "  ✓ Variant: {$variant[1]}\n";
        }
        
        echo "\n✓ Product variants added\n\n";
        
        // Initialize inventory (optional - set to 0 by default, admin can update)
        echo "Inventory initialized to 0 (admin can update via product management)\n";
    } else {
        echo "✓ Default product already exists (ID: {$existingProduct['id']})\n\n";
    }
    
    echo "\n✅ Database migration completed successfully!\n";
    echo "\nNext steps:\n";
    echo "1. Access /admin/products.php to manage products\n";
    echo "2. Update product prices, stock status, and inventory quantities\n";
    echo "3. The ordering system will automatically use these values\n";
    
} catch (PDOException $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
    echo "Error Code: " . $e->getCode() . "\n";
    exit(1);
}

