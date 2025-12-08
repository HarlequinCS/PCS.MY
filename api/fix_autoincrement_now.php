<?php
/**
 * IMMEDIATE FIX for AUTO_INCREMENT
 * This will fix the AUTO_INCREMENT right now
 */

header('Content-Type: text/plain; charset=utf-8');

define('DB_CONFIG_LOADED', true);
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/db.php';

try {
    $pdo = getDBConnection();
    
    echo "=== FIXING AUTO_INCREMENT NOW ===\n\n";
    
    // Step 1: Check for row with comment_id = 0
    echo "Step 1: Checking for comment_id = 0...\n";
    $zeroRow = $pdo->query("SELECT * FROM comment WHERE comment_id = 0 LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    
    if ($zeroRow) {
        echo "  ⚠ Found row with comment_id = 0\n";
        
        // Get max ID (excluding 0)
        $maxId = $pdo->query("SELECT MAX(comment_id) as max_id FROM comment WHERE comment_id > 0")->fetch(PDO::FETCH_ASSOC);
        $newId = ($maxId['max_id'] ?? 0) + 1;
        if ($newId < 1) $newId = 1;
        
        echo "  Updating to comment_id = $newId...\n";
        $pdo->prepare("UPDATE comment SET comment_id = ? WHERE comment_id = 0")->execute([$newId]);
        echo "  ✓ Updated successfully!\n\n";
    } else {
        echo "  ✓ No row with comment_id = 0\n\n";
    }
    
    // Step 2: Fix AUTO_INCREMENT
    echo "Step 2: Fixing AUTO_INCREMENT...\n";
    $maxId = $pdo->query("SELECT MAX(comment_id) as max_id FROM comment")->fetch(PDO::FETCH_ASSOC);
    $nextId = ($maxId['max_id'] ?? 0) + 1;
    if ($nextId < 1) $nextId = 1;
    
    echo "  Setting AUTO_INCREMENT to: $nextId\n";
    $pdo->exec("ALTER TABLE comment AUTO_INCREMENT = $nextId");
    echo "  ✓ AUTO_INCREMENT fixed!\n\n";
    
    // Step 3: Verify
    echo "Step 3: Verifying...\n";
    $tableStatus = $pdo->query("SHOW TABLE STATUS LIKE 'comment'")->fetch(PDO::FETCH_ASSOC);
    $autoIncrement = $tableStatus['Auto_increment'] ?? null;
    $zeroCount = $pdo->query("SELECT COUNT(*) as count FROM comment WHERE comment_id = 0")->fetch(PDO::FETCH_ASSOC);
    
    echo "  AUTO_INCREMENT: $autoIncrement\n";
    echo "  Rows with ID 0: " . $zeroCount['count'] . "\n";
    
    if ($autoIncrement > 0 && $zeroCount['count'] == 0) {
        echo "\n  ✓✓✓ FIXED! You can now insert comments. ✓✓✓\n";
    } else {
        echo "\n  ⚠ Still has issues. Check manually.\n";
    }
    
} catch (PDOException $e) {
    echo "\n✗ ERROR: " . $e->getMessage() . "\n";
    echo "Code: " . $e->getCode() . "\n";
    echo "\nTry running this SQL directly:\n";
    echo "ALTER TABLE comment AUTO_INCREMENT = 1;\n";
}

