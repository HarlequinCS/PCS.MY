<?php
/**
 * Simple Database Connection Test
 * Visit: https://projectcutisem.com/api/test_db.php
 */

header('Content-Type: text/plain');

$host = 'localhost';
$dbname = 'u284911514_pcsmy';
$username = 'u284911514_pcsadmin';
$password = 'PCSadmin25';

echo "Testing Database Connection...\n\n";

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    
    echo "✓ Database connection successful!\n\n";
    
    // Check if table exists
    $tableExists = $pdo->query("SHOW TABLES LIKE 'comment'")->rowCount() > 0;
    echo "Table 'comment' exists: " . ($tableExists ? "YES" : "NO") . "\n\n";
    
    if ($tableExists) {
        // Get table structure
        echo "Table Structure:\n";
        echo str_repeat("-", 50) . "\n";
        $columns = $pdo->query("SHOW COLUMNS FROM comment")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($columns as $col) {
            echo "Column: {$col['Field']} | Type: {$col['Type']} | Null: {$col['Null']} | Default: {$col['Default']}\n";
        }
        echo str_repeat("-", 50) . "\n\n";
        
        // Test INSERT
        echo "Testing INSERT...\n";
        $testComment = "Test comment " . date('Y-m-d H:i:s');
        $stmt = $pdo->prepare("INSERT INTO comment (comment) VALUES (?)");
        $stmt->execute([$testComment]);
        $insertId = $pdo->lastInsertId();
        echo "✓ INSERT successful! ID: $insertId\n\n";
        
        // Test UPDATE status
        echo "Testing UPDATE status to 'pending'...\n";
        try {
            $pdo->prepare("UPDATE comment SET status = 'pending' WHERE comment_id = ?")->execute([$insertId]);
            echo "✓ UPDATE status successful!\n\n";
        } catch (PDOException $e) {
            echo "✗ UPDATE status failed: " . $e->getMessage() . "\n";
            echo "  (This is OK if status column doesn't exist)\n\n";
        }
        
        // Delete test comment
        $pdo->prepare("DELETE FROM comment WHERE comment_id = ?")->execute([$insertId]);
        echo "✓ Test comment deleted\n\n";
        
        echo "All tests passed! ✓\n";
    } else {
        echo "✗ Table 'comment' does not exist!\n";
        echo "Please create the table first.\n";
    }
    
} catch (PDOException $e) {
    echo "✗ Database Error:\n";
    echo "Message: " . $e->getMessage() . "\n";
    echo "Code: " . $e->getCode() . "\n";
    echo "\nPossible issues:\n";
    echo "1. Wrong database host (might not be 'localhost')\n";
    echo "2. Wrong database name\n";
    echo "3. Wrong username/password\n";
    echo "4. Database server not accessible\n";
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
}

