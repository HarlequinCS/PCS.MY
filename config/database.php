<?php
/**
 * Database Configuration
 * Secure database connection settings
 */

// Prevent direct access
if (!defined('DB_CONFIG_LOADED')) {
    define('DB_CONFIG_LOADED', true);
}

return [
    'host' => 'localhost',
    'database' => 'u284911514_pcsmy',
    'username' => 'u284911514_pcsadmin',
    'password' => 'PCSadmin25',
    'charset' => 'utf8mb4',
    'options' => [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]
];

