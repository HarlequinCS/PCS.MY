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
// Only use .env values if they are actually set and not empty
// Otherwise, use defaults (which will be overridden by database.php fallbacks)
if (!defined('DB_HOST')) {
    define('DB_HOST', !empty($env['DB_HOST']) ? $env['DB_HOST'] : 'localhost');
    define('DB_NAME', !empty($env['DB_NAME']) ? $env['DB_NAME'] : '');
    define('DB_USER', !empty($env['DB_USER']) ? $env['DB_USER'] : '');
    define('DB_PASS', !empty($env['DB_PASS']) ? $env['DB_PASS'] : '');
    define('APP_ENV', !empty($env['APP_ENV']) ? $env['APP_ENV'] : 'production');
    define('APP_DEBUG', filter_var($env['APP_DEBUG'] ?? 'false', FILTER_VALIDATE_BOOLEAN));
}

if (!defined('CLOUDINARY_CLOUD_NAME')) {
    // Cloud name is public (it appears in every delivery URL). Default it so
    // production still uses Cloudinary if Hostinger .env was not updated.
    $cloudinaryCloudName = trim((string) ($env['CLOUDINARY_CLOUD_NAME'] ?? getenv('CLOUDINARY_CLOUD_NAME') ?: ''));
    if ($cloudinaryCloudName === '') {
        $cloudinaryCloudName = 'ditifn5y3';
    }

    define('CLOUDINARY_CLOUD_NAME', $cloudinaryCloudName);
    define('CLOUDINARY_API_KEY', (string) ($env['CLOUDINARY_API_KEY'] ?? getenv('CLOUDINARY_API_KEY') ?: ''));
    define('CLOUDINARY_API_SECRET', (string) ($env['CLOUDINARY_API_SECRET'] ?? getenv('CLOUDINARY_API_SECRET') ?: ''));
}

