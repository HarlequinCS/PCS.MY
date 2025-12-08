<?php
/**
 * Database Connection Helper
 */

// Prevent direct access
if (!defined('DB_CONFIG_LOADED')) {
    http_response_code(403);
    die('Direct access not allowed');
}

function getDBConnection() {
    static $pdo = null;
    
    if ($pdo === null) {
        $config = require __DIR__ . '/../config/database.php';
        
        try {
            $dsn = "mysql:host={$config['host']};dbname={$config['database']};charset={$config['charset']}";
            $pdo = new PDO($dsn, $config['username'], $config['password'], $config['options']);
        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            http_response_code(500);
            die(json_encode(['error' => 'Database connection failed']));
        }
    }
    
    return $pdo;
}

