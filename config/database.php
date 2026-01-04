<?php
/**
 * Database Configuration
 * Secure database connection settings
 * 
 * Loads credentials from environment variables (.env file)
 * Falls back to hardcoded values for backward compatibility
 */

// Load environment variables first
if (file_exists(__DIR__ . '/env.php')) {
    require_once __DIR__ . '/env.php';
}

// Prevent direct access
if (!defined('DB_CONFIG_LOADED')) {
    define('DB_CONFIG_LOADED', true);
}

// Use environment variables if available, otherwise use defaults
// SECURITY: In production, always use .env file, never hardcode credentials
return [
    'host' => defined('DB_HOST') ? DB_HOST : 'localhost',
    'database' => defined('DB_NAME') ? DB_NAME : 'u284911514_pcsmy',
    'username' => defined('DB_USER') ? DB_USER : 'u284911514_pcsadmin',
    'password' => defined('DB_PASS') ? DB_PASS : 'PCSadmin25',
    'charset' => 'utf8mb4',
    'options' => [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_PERSISTENT => false, // Don't use persistent connections
        PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true, // Use buffered queries for better performance
    ]
];

