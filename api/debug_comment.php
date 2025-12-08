<?php
/**
 * Debug Comment Submission
 * This file helps identify the exact error
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

define('DB_CONFIG_LOADED', true);
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/db.php';

$debug = [
    'step' => 'start',
    'errors' => [],
    'data' => []
];

try {
    $debug['step'] = 'config_loaded';
    
    // Test database connection
    $debug['step'] = 'testing_db_connection';
    $pdo = getDBConnection();
    $debug['data']['db_connected'] = true;
    
    // Check if table exists
    $debug['step'] = 'checking_table';
    $tableExists = $pdo->query("SHOW TABLES LIKE 'comment'")->rowCount() > 0;
    $debug['data']['table_exists'] = $tableExists;
    
    if ($tableExists) {
        // Get table structure
        $debug['step'] = 'getting_structure';
        $columns = $pdo->query("SHOW COLUMNS FROM comment")->fetchAll(PDO::FETCH_ASSOC);
        $debug['data']['columns'] = $columns;
        
        // Test a simple SELECT
        $debug['step'] = 'testing_select';
        $testSelect = $pdo->query("SELECT COUNT(*) as count FROM comment")->fetch();
        $debug['data']['comment_count'] = $testSelect['count'];
        
        // Test INSERT with sample data
        $debug['step'] = 'testing_insert';
        $testComment = "Test comment " . time();
        $stmt = $pdo->prepare("INSERT INTO comment (comment, status) VALUES (?, 'pending')");
        $stmt->execute([$testComment]);
        $insertId = $pdo->lastInsertId();
        $debug['data']['test_insert_id'] = $insertId;
        
        // Delete test comment
        if ($insertId) {
            $pdo->prepare("DELETE FROM comment WHERE comment_id = ?")->execute([$insertId]);
            $debug['data']['test_insert_success'] = true;
        }
    } else {
        $debug['errors'][] = 'Table does not exist';
    }
    
    $debug['step'] = 'success';
    $debug['status'] = 'All tests passed';
    
} catch (PDOException $e) {
    $debug['step'] = 'pdo_error';
    $debug['errors'][] = [
        'type' => 'PDOException',
        'message' => $e->getMessage(),
        'code' => $e->getCode(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'errorInfo' => $e->errorInfo
    ];
} catch (Exception $e) {
    $debug['step'] = 'general_error';
    $debug['errors'][] = [
        'type' => 'Exception',
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString()
    ];
} catch (Error $e) {
    $debug['step'] = 'fatal_error';
    $debug['errors'][] = [
        'type' => 'Fatal Error',
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ];
}

echo json_encode($debug, JSON_PRETTY_PRINT);

