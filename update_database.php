<?php
/**
 * Database Update Script for Enhanced Checkout System
 * This script will automatically update the orders table and create promotions table
 * Run this file once via browser: http://yourdomain.com/update_database.php
 */

// ============================================
// STEP 1: Configuration
// ============================================
// Define database config constant untuk include db.php
if (!defined('DB_CONFIG_LOADED')) {
    define('DB_CONFIG_LOADED', true);
}

// Include database connection
require_once __DIR__ . '/includes/db.php';

// ============================================
// STEP 2: Database Update Functions
// ============================================
function updateOrdersTable($pdo) {
    $updates = [];
    $errors = [];
    
    // List of columns to add
    $columns = [
        'fullname' => "VARCHAR(255) NULL COMMENT 'Full name of customer'",
        'address1' => "VARCHAR(255) NULL COMMENT 'Address Line 1'",
        'address2' => "VARCHAR(255) NULL COMMENT 'Address Line 2 (optional)'",
        'address3' => "VARCHAR(255) NULL COMMENT 'Address Line 3 (optional)'",
        'state' => "VARCHAR(100) NULL COMMENT 'State in Malaysia'",
        'district' => "VARCHAR(100) NULL COMMENT 'District/City'",
        'postcode' => "VARCHAR(10) NULL COMMENT 'Postcode (5 digits)'",
        'delivery_notes' => "TEXT NULL COMMENT 'Delivery instructions'",
        'company_name' => "VARCHAR(255) NULL COMMENT 'Company name (optional)'",
        'promo_code' => "VARCHAR(50) NULL COMMENT 'Promotion code applied'",
        'discount_amount' => "DECIMAL(10,2) NULL DEFAULT 0.00 COMMENT 'Discount amount applied'"
    ];
    
    // Check existing columns
    $stmt = $pdo->query("DESCRIBE orders");
    $existingColumns = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $existingColumns[] = $row['Field'];
    }
    
    // Add missing columns (with proper positioning)
    // Define order of columns to add
    $columnOrder = [
        'fullname' => ['after' => 'name'],
        'address1' => ['after' => 'phone'],
        'address2' => ['after' => 'address1'],
        'address3' => ['after' => 'address2'],
        'state' => ['after' => 'address3'],
        'district' => ['after' => 'state'],
        'postcode' => ['after' => 'district'],
        'delivery_notes' => ['after' => 'postcode'],
        'company_name' => ['after' => 'delivery_notes'],
        'promo_code' => ['after' => 'company_name'],
        'discount_amount' => ['after' => 'promo_code']
    ];
    
    foreach ($columnOrder as $columnName => $positionInfo) {
        if (!in_array($columnName, $existingColumns)) {
            // Check if the "after" column exists
            $afterColumn = $positionInfo['after'];
            if (!in_array($afterColumn, $existingColumns)) {
                // If the "after" column doesn't exist yet, add it first or skip positioning
                $position = '';
            } else {
                $position = " AFTER `{$afterColumn}`";
            }
            
            try {
                $columnDefinition = $columns[$columnName];
                $sql = "ALTER TABLE `orders` ADD COLUMN `{$columnName}` {$columnDefinition}{$position}";
                $pdo->exec($sql);
                $existingColumns[] = $columnName; // Add to existing list for next iterations
                $updates[] = "✅ Added column: {$columnName}";
            } catch (PDOException $e) {
                $errors[] = "❌ Error adding column {$columnName}: " . $e->getMessage();
            }
        } else {
            $updates[] = "ℹ️ Column already exists: {$columnName}";
        }
    }
    
    return ['updates' => $updates, 'errors' => $errors];
}

function createPromotionsTable($pdo) {
    $updates = [];
    $errors = [];
    
    // Check if table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'promotions'");
    if ($stmt->rowCount() > 0) {
        $updates[] = "ℹ️ Promotions table already exists";
        return ['updates' => $updates, 'errors' => $errors];
    }
    
    // Create promotions table
    try {
        $sql = "CREATE TABLE IF NOT EXISTS `promotions` (
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Promotion codes table'";
        
        $pdo->exec($sql);
        $updates[] = "✅ Created promotions table";
    } catch (PDOException $e) {
        $errors[] = "❌ Error creating promotions table: " . $e->getMessage();
        return ['updates' => $updates, 'errors' => $errors];
    }
    
    // Insert sample promotion codes
    $sampleCodes = [
        [
            'code' => 'WELCOME10',
            'type' => 'percentage',
            'value' => 10.00,
            'active' => 1,
            'min_purchase' => 0.00,
            'max_discount' => 50.00,
            'valid_from' => date('Y-m-d H:i:s'),
            'valid_until' => date('Y-m-d H:i:s', strtotime('+1 year'))
        ],
        [
            'code' => 'SAVE20',
            'type' => 'fixed',
            'value' => 20.00,
            'active' => 1,
            'min_purchase' => 100.00,
            'max_discount' => null,
            'valid_from' => date('Y-m-d H:i:s'),
            'valid_until' => date('Y-m-d H:i:s', strtotime('+1 year'))
        ],
        [
            'code' => 'SUMMER15',
            'type' => 'percentage',
            'value' => 15.00,
            'active' => 1,
            'min_purchase' => 150.00,
            'max_discount' => 75.00,
            'valid_from' => date('Y-m-d H:i:s'),
            'valid_until' => date('Y-m-d H:i:s', strtotime('+6 months'))
        ]
    ];
    
    $insertStmt = $pdo->prepare("
        INSERT INTO promotions (code, type, value, active, min_purchase, max_discount, valid_from, valid_until) 
        VALUES (:code, :type, :value, :active, :min_purchase, :max_discount, :valid_from, :valid_until)
        ON DUPLICATE KEY UPDATE code = code
    ");
    
    foreach ($sampleCodes as $code) {
        try {
            $insertStmt->execute([
                ':code' => $code['code'],
                ':type' => $code['type'],
                ':value' => $code['value'],
                ':active' => $code['active'],
                ':min_purchase' => $code['min_purchase'],
                ':max_discount' => $code['max_discount'],
                ':valid_from' => $code['valid_from'],
                ':valid_until' => $code['valid_until']
            ]);
            $updates[] = "✅ Inserted sample code: {$code['code']}";
        } catch (PDOException $e) {
            $errors[] = "❌ Error inserting code {$code['code']}: " . $e->getMessage();
        }
    }
    
    return ['updates' => $updates, 'errors' => $errors];
}

// ============================================
// STEP 3: Execute Updates
// ============================================
$allUpdates = [];
$allErrors = [];

try {
    $pdo = getDBConnection();
    
    // Update orders table
    $result = updateOrdersTable($pdo);
    $allUpdates = array_merge($allUpdates, $result['updates']);
    $allErrors = array_merge($allErrors, $result['errors']);
    
    // Create promotions table
    $result = createPromotionsTable($pdo);
    $allUpdates = array_merge($allUpdates, $result['updates']);
    $allErrors = array_merge($allErrors, $result['errors']);
    
} catch (Exception $e) {
    $allErrors[] = "❌ Fatal error: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Update - Checkout System</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        
        .container {
            background: white;
            border-radius: 12px;
            padding: 40px;
            max-width: 800px;
            width: 100%;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
        }
        
        h1 {
            color: #1a202c;
            margin-bottom: 10px;
        }
        
        .subtitle {
            color: #6b7280;
            margin-bottom: 30px;
        }
        
        .section {
            margin-bottom: 30px;
        }
        
        .section h2 {
            color: #1a202c;
            font-size: 20px;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #e5e7eb;
        }
        
        .update-item {
            padding: 10px;
            margin-bottom: 8px;
            border-radius: 6px;
            font-family: 'Courier New', monospace;
            font-size: 14px;
        }
        
        .update-item.success {
            background: #d1fae5;
            color: #065f46;
        }
        
        .update-item.info {
            background: #dbeafe;
            color: #1e40af;
        }
        
        .update-item.error {
            background: #fee2e2;
            color: #991b1b;
        }
        
        .summary {
            margin-top: 30px;
            padding: 20px;
            background: #f9fafb;
            border-radius: 8px;
        }
        
        .summary h3 {
            color: #1a202c;
            margin-bottom: 10px;
        }
        
        .summary p {
            color: #6b7280;
            margin: 5px 0;
        }
        
        .btn {
            display: inline-block;
            padding: 12px 24px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            margin-top: 20px;
            transition: transform 0.2s;
        }
        
        .btn:hover {
            transform: translateY(-2px);
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Database Update Script</h1>
        <p class="subtitle">Enhanced Checkout System - Database Migration</p>
        
        <div class="section">
            <h2>Update Results</h2>
            
            <?php if (!empty($allUpdates)): ?>
                <?php foreach ($allUpdates as $update): ?>
                    <div class="update-item <?php echo strpos($update, '✅') !== false ? 'success' : 'info'; ?>">
                        <?php echo htmlspecialchars($update); ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
            
            <?php if (!empty($allErrors)): ?>
                <?php foreach ($allErrors as $error): ?>
                    <div class="update-item error">
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
        <div class="summary">
            <h3>Summary</h3>
            <p><strong>Updates:</strong> <?php echo count($allUpdates); ?> operations</p>
            <p><strong>Errors:</strong> <?php echo count($allErrors); ?> errors</p>
            
            <?php if (empty($allErrors)): ?>
                <p style="color: #10b981; font-weight: 600; margin-top: 15px;">
                    ✅ Database update completed successfully!
                </p>
                <p style="margin-top: 10px; color: #6b7280;">
                    You can now use the enhanced checkout system. 
                    <a href="order-baju.php" class="btn">Go to Order Page</a>
                </p>
            <?php else: ?>
                <p style="color: #ef4444; font-weight: 600; margin-top: 15px;">
                    ⚠️ Some errors occurred. Please check the error messages above.
                </p>
            <?php endif; ?>
        </div>
        
        <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #e5e7eb; color: #6b7280; font-size: 12px;">
            <p><strong>Note:</strong> You can safely delete this file (update_database.php) after successful update.</p>
        </div>
    </div>
</body>
</html>

