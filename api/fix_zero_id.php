<?php
/**
 * Fix the comment_id = 0 issue
 * This will update the row with comment_id = 0 to have a proper ID
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
    
    echo "Fixing comment_id = 0 issue...\n\n";
    
    // Check if row with comment_id = 0 exists
    $zeroRow = $pdo->query("SELECT * FROM comment WHERE comment_id = 0 LIMIT 1")->fetch();
    
    if ($zeroRow) {
        echo "Found row with comment_id = 0:\n";
        echo "Comment: " . substr($zeroRow['comment'], 0, 50) . "...\n";
        echo "Status: " . $zeroRow['status'] . "\n\n";
        
        // Get max ID
        $maxId = $pdo->query("SELECT MAX(comment_id) as max_id FROM comment WHERE comment_id > 0")->fetch();
        $newId = ($maxId['max_id'] ?? 0) + 1;
        
        echo "Updating comment_id from 0 to $newId...\n";
        
        // Update the row
        $stmt = $pdo->prepare("UPDATE comment SET comment_id = ? WHERE comment_id = 0");
        $stmt->execute([$newId]);
        
        echo "✓ Row updated successfully!\n\n";
    } else {
        echo "No row with comment_id = 0 found.\n\n";
    }
    
    // Fix AUTO_INCREMENT
    $maxId = $pdo->query("SELECT MAX(comment_id) as max_id FROM comment")->fetch();
    $nextId = ($maxId['max_id'] ?? 0) + 1;
    if ($nextId < 1) $nextId = 1;
    
    echo "Setting AUTO_INCREMENT to: $nextId\n";
    $pdo->exec("ALTER TABLE comment AUTO_INCREMENT = $nextId");
    
    echo "✓ AUTO_INCREMENT fixed!\n\n";
    
    // Verify
    $check = $pdo->query("SHOW TABLE STATUS LIKE 'comment'")->fetch();
    echo "Current AUTO_INCREMENT value: " . $check['Auto_increment'] . "\n";
    
    echo "\n✓ All fixes complete! You can now insert comments.\n";
    
} catch (PDOException $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
    echo "Code: " . $e->getCode() . "\n";
    echo "Error Info: " . print_r($e->errorInfo, true) . "\n";
}

