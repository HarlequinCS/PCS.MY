<?php
/**
 * PERMANENT FIX for comment_id = 0 issue
 * This script will:
 * 1. Update the row with comment_id = 0 to have a proper ID
 * 2. Reset AUTO_INCREMENT to the correct value
 * 3. Verify the fix
 * 
 * Run this ONCE to permanently fix the database
 */

header('Content-Type: text/plain; charset=utf-8');

define('DB_CONFIG_LOADED', true);
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/db.php';

try {
    $pdo = getDBConnection();
    
    echo "=== PERMANENT DATABASE FIX ===\n\n";
    
    // Step 1: Check for row with comment_id = 0
    echo "Step 1: Checking for row with comment_id = 0...\n";
    $zeroRow = $pdo->query("SELECT * FROM comment WHERE comment_id = 0 LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    
    if ($zeroRow) {
        echo "  ✓ Found row with comment_id = 0\n";
        echo "  Comment: " . substr($zeroRow['comment'], 0, 50) . "...\n";
        echo "  Status: " . ($zeroRow['status'] ?? 'N/A') . "\n\n";
        
        // Step 2: Get max ID (excluding 0)
        echo "Step 2: Calculating new ID...\n";
        $maxId = $pdo->query("SELECT MAX(comment_id) as max_id FROM comment WHERE comment_id > 0")->fetch(PDO::FETCH_ASSOC);
        $newId = ($maxId['max_id'] ?? 0) + 1;
        
        if ($newId < 1) $newId = 1;
        echo "  ✓ New ID will be: $newId\n\n";
        
        // Step 3: Update the row
        echo "Step 3: Updating row from comment_id = 0 to comment_id = $newId...\n";
        $stmt = $pdo->prepare("UPDATE comment SET comment_id = ? WHERE comment_id = 0");
        $stmt->execute([$newId]);
        echo "  ✓ Row updated successfully!\n\n";
    } else {
        echo "  ✓ No row with comment_id = 0 found (good!)\n\n";
    }
    
    // Step 4: Fix AUTO_INCREMENT
    echo "Step 4: Fixing AUTO_INCREMENT...\n";
    $maxId = $pdo->query("SELECT MAX(comment_id) as max_id FROM comment")->fetch(PDO::FETCH_ASSOC);
    $nextId = ($maxId['max_id'] ?? 0) + 1;
    if ($nextId < 1) $nextId = 1;
    
    $pdo->exec("ALTER TABLE comment AUTO_INCREMENT = $nextId");
    echo "  ✓ AUTO_INCREMENT set to: $nextId\n\n";
    
    // Step 5: Verify
    echo "Step 5: Verifying fix...\n";
    $tableStatus = $pdo->query("SHOW TABLE STATUS LIKE 'comment'")->fetch(PDO::FETCH_ASSOC);
    $autoIncrement = $tableStatus['Auto_increment'] ?? null;
    $zeroRowCheck = $pdo->query("SELECT COUNT(*) as count FROM comment WHERE comment_id = 0")->fetch(PDO::FETCH_ASSOC);
    
    echo "  Current AUTO_INCREMENT: $autoIncrement\n";
    echo "  Rows with comment_id = 0: " . $zeroRowCheck['count'] . "\n";
    
    if ($autoIncrement > 0 && $zeroRowCheck['count'] == 0) {
        echo "\n  ✓✓✓ VERIFICATION PASSED! Database is fixed! ✓✓✓\n";
    } else {
        echo "\n  ⚠ WARNING: Verification shows issues. Please check manually.\n";
    }
    
    echo "\n=== FIX COMPLETE ===\n";
    echo "You can now insert comments normally.\n";
    
} catch (PDOException $e) {
    echo "\n✗ DATABASE ERROR:\n";
    echo "  Message: " . $e->getMessage() . "\n";
    echo "  Code: " . $e->getCode() . "\n";
    echo "  SQL State: " . ($e->errorInfo[0] ?? 'N/A') . "\n";
    echo "  Driver Code: " . ($e->errorInfo[1] ?? 'N/A') . "\n";
    echo "\nPlease check your database connection and try again.\n";
} catch (Exception $e) {
    echo "\n✗ ERROR: " . $e->getMessage() . "\n";
}

