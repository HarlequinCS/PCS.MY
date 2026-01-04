<?php
/**
 * Environment Configuration Loader
 * Loads from .env file or uses defaults
 * 
 * SECURITY: Never commit .env file to version control
 */

function loadEnv($filePath) {
    if (!file_exists($filePath)) {
        return [];
    }
    
    $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $env = [];
    
    foreach ($lines as $line) {
        // Skip comments and empty lines
        $line = trim($line);
        if (empty($line) || strpos($line, '#') === 0) {
            continue;
        }
        
        // Parse KEY=VALUE format
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $env[trim($key)] = trim($value, '"\'');
        }
    }
    
    return $env;
}

// Load environment variables from .env file
$envPath = __DIR__ . '/../.env';
$env = loadEnv($envPath);

// Define constants if not already defined
if (!defined('DB_HOST')) {
    define('DB_HOST', $env['DB_HOST'] ?? 'localhost');
    define('DB_NAME', $env['DB_NAME'] ?? '');
    define('DB_USER', $env['DB_USER'] ?? '');
    define('DB_PASS', $env['DB_PASS'] ?? '');
    define('APP_ENV', $env['APP_ENV'] ?? 'production');
    define('APP_DEBUG', filter_var($env['APP_DEBUG'] ?? 'false', FILTER_VALIDATE_BOOLEAN));
}

