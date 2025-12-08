<?php
/**
 * Check Table Status
 * This will show the current state of the comment table
 */

header('Content-Type: text/plain; charset=utf-8');

define('DB_CONFIG_LOADED', true);
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/db.php';

try {
    $pdo = getDBConnection();
    
    echo "=== COMMENT TABLE STATUS ===\n\n";
    
    // Check table structure
    echo "1. TABLE STRUCTURE:\n";
    echo str_repeat("-", 60) . "\n";
    $columns = $pdo->query("SHOW COLUMNS FROM comment")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($columns as $col) {
        echo sprintf(
            "  %-20s %-20s Null: %-5s Default: %s\n",
            $col['Field'],
            $col['Type'],
            $col['Null'],
            $col['Default'] ?? 'NULL'
        );
    }
    echo str_repeat("-", 60) . "\n\n";
    
    // Check AUTO_INCREMENT
    echo "2. AUTO_INCREMENT STATUS:\n";
    $tableStatus = $pdo->query("SHOW TABLE STATUS LIKE 'comment'")->fetch(PDO::FETCH_ASSOC);
    echo "  Current AUTO_INCREMENT: " . ($tableStatus['Auto_increment'] ?? 'NULL') . "\n\n";
    
    // Check for row with comment_id = 0
    echo "3. ROWS WITH comment_id = 0:\n";
    $zeroRows = $pdo->query("SELECT comment_id, comment, status FROM comment WHERE comment_id = 0")->fetchAll(PDO::FETCH_ASSOC);
    if (count($zeroRows) > 0) {
        echo "  ⚠ FOUND " . count($zeroRows) . " ROW(S) WITH comment_id = 0:\n";
        foreach ($zeroRows as $row) {
            echo "    - ID: " . $row['comment_id'] . " | Status: " . $row['status'] . " | Comment: " . substr($row['comment'], 0, 50) . "...\n";
        }
    } else {
        echo "  ✓ No rows with comment_id = 0\n";
    }
    echo "\n";
    
    // Check all comment IDs
    echo "4. ALL COMMENT IDs:\n";
    $allIds = $pdo->query("SELECT comment_id, status FROM comment ORDER BY comment_id")->fetchAll(PDO::FETCH_ASSOC);
    if (count($allIds) > 0) {
        echo "  Total rows: " . count($allIds) . "\n";
        echo "  IDs: ";
        $ids = array_column($allIds, 'comment_id');
        echo implode(', ', $ids) . "\n";
        echo "  Min ID: " . min($ids) . "\n";
        echo "  Max ID: " . max($ids) . "\n";
    } else {
        echo "  ✓ Table is empty\n";
    }
    echo "\n";
    
    // Calculate what AUTO_INCREMENT should be
    echo "5. RECOMMENDED AUTO_INCREMENT VALUE:\n";
    $maxId = $pdo->query("SELECT MAX(comment_id) as max_id FROM comment")->fetch(PDO::FETCH_ASSOC);
    $recommended = ($maxId['max_id'] ?? 0) + 1;
    if ($recommended < 1) $recommended = 1;
    echo "  Should be: $recommended\n";
    echo "  Current is: " . ($tableStatus['Auto_increment'] ?? 'NULL') . "\n";
    
    if (($tableStatus['Auto_increment'] ?? 0) != $recommended) {
        echo "\n  ⚠ AUTO_INCREMENT IS INCORRECT!\n";
        echo "  Run this SQL to fix it:\n";
        echo "  ALTER TABLE comment AUTO_INCREMENT = $recommended;\n";
    } else {
        echo "\n  ✓ AUTO_INCREMENT is correct\n";
    }
    
} catch (PDOException $e) {
    echo "\n✗ ERROR: " . $e->getMessage() . "\n";
    echo "Code: " . $e->getCode() . "\n";
}

