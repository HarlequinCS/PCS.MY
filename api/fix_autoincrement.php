<?php
/**
 * Fix AUTO_INCREMENT for comment table
 * Run this once to fix the AUTO_INCREMENT issue
 */

header('Content-Type: text/plain');

$host = 'localhost';
$dbname = 'u284911514_pcsmy';
$username = 'u284911514_pcsadmin';
$password = 'PCSadmin25';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    
    echo "Fixing AUTO_INCREMENT for comment table...\n\n";
    
    // Get the maximum comment_id
    $maxId = $pdo->query("SELECT MAX(comment_id) as max_id FROM comment")->fetch();
    $nextId = ($maxId['max_id'] ?? 0) + 1;
    
    echo "Current max comment_id: " . ($maxId['max_id'] ?? 'NULL') . "\n";
    echo "Setting AUTO_INCREMENT to: $nextId\n\n";
    
    // Fix AUTO_INCREMENT
    $pdo->exec("ALTER TABLE comment AUTO_INCREMENT = $nextId");
    
    echo "✓ AUTO_INCREMENT fixed!\n";
    echo "Next comment will have ID: $nextId\n\n";
    
    // Verify
    $check = $pdo->query("SHOW TABLE STATUS LIKE 'comment'")->fetch();
    echo "Current AUTO_INCREMENT value: " . $check['Auto_increment'] . "\n";
    
    echo "\n✓ Fix complete! You can now insert comments.\n";
    
} catch (PDOException $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
    echo "Code: " . $e->getCode() . "\n";
}

