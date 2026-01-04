<?php
/**
 * Database Connection Helper
 * 
 * CRITICAL: Sets MySQL session timezone to UTC for consistent datetime storage
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
            
            // CRITICAL: Set MySQL session timezone to UTC
            // This ensures all NOW(), CURRENT_TIMESTAMP, etc. use UTC
            // Application layer converts to/from Asia/Kuala_Lumpur
            $pdo->exec("SET time_zone = '+00:00'");
            
        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            http_response_code(500);
            die(json_encode(['error' => 'Database connection failed']));
        }
    }
    
    return $pdo;
}

