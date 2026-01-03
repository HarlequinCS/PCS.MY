<?php
/**
 * Update Product Variant Names
 * Changes variant names from old Malay names to new English names
 * 
 * Run this script once to update the database
 */

// Database configuration
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/db.php';

try {
    $pdo = getDBConnection();
    
    echo "=== Updating Product Variant Names ===\n\n";
    
    // Variant name mapping
    $variantNameUpdates = [
        'hitamdepan' => 'Classic Black',
        'hijaufront' => 'Forest Green',
        'merahdepan' => 'Royal Red'
    ];
    
    $pdo->beginTransaction();
    
    foreach ($variantNameUpdates as $variantKey => $newName) {
        // Update variant name
        $stmt = $pdo->prepare("
            UPDATE product_variants 
            SET variant_name = :new_name 
            WHERE variant_key = :variant_key
        ");
        
        $stmt->execute([
            ':new_name' => $newName,
            ':variant_key' => $variantKey
        ]);
        
        $affectedRows = $stmt->rowCount();
        
        if ($affectedRows > 0) {
            echo "✓ Updated '{$variantKey}' to '{$newName}' ({$affectedRows} row(s))\n";
        } else {
            echo "⚠ No rows updated for '{$variantKey}' (may not exist in database)\n";
        }
    }
    
    $pdo->commit();
    
    echo "\n=== Update Complete ===\n";
    echo "All variant names have been updated successfully!\n\n";
    
    // Verify updates
    echo "=== Verification ===\n";
    $verifyStmt = $pdo->query("SELECT variant_key, variant_name FROM product_variants ORDER BY variant_key");
    $variants = $verifyStmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($variants as $variant) {
        echo "  {$variant['variant_key']} => {$variant['variant_name']}\n";
    }
    
} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}

