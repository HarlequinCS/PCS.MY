<?php
/**
 * Test Database Connection
 * Use this file to test if your database connection is working
 * Access via: https://projectcutisem.com/api/test_connection.php
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

define('DB_CONFIG_LOADED', true);
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/db.php';

$results = [
    'database_config' => 'OK',
    'connection' => 'FAILED',
    'table_exists' => 'UNKNOWN',
    'table_structure' => []
];

try {
    $pdo = getDBConnection();
    $results['connection'] = 'OK';
    
    // Check if table exists
    $tableExists = $pdo->query("SHOW TABLES LIKE 'comment'")->rowCount() > 0;
    $results['table_exists'] = $tableExists ? 'YES' : 'NO';
    
    if ($tableExists) {
        // Get table structure
        $columns = $pdo->query("SHOW COLUMNS FROM comment")->fetchAll(PDO::FETCH_ASSOC);
        $results['table_structure'] = $columns;
        
        // Count comments
        $count = $pdo->query("SELECT COUNT(*) as count FROM comment")->fetch();
        $results['comment_count'] = $count['count'];
    }
    
    echo json_encode([
        'success' => true,
        'results' => $results,
        'message' => 'Database connection successful!'
    ], JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'results' => $results,
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ], JSON_PRETTY_PRINT);
}

