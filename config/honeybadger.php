<?php
/**
 * Honeybadger Error Monitoring Configuration
 * Initializes Honeybadger for error tracking and monitoring
 *
 * SECURITY: API key should be stored in environment variable in production
 */

// Prevent direct access
if (!defined('HONEYBADGER_CONFIG_LOADED')) {
    define('HONEYBADGER_CONFIG_LOADED', true);
}

// Load Composer autoloader for Honeybadger
require_once __DIR__ . '/../vendor/autoload.php';

// Load from environment variable if available, otherwise use default
$apiKey = getenv('HONEYBADGER_API_KEY') ?: 'hbp_SqN6yMKgodhhiCc9a7WEdgVdl2K8UE1cQq1y';

// Initialize Honeybadger
$honeybadger = Honeybadger\Honeybadger::new([
    'api_key' => $apiKey,
    'environment' => getenv('APP_ENV') ?: 'production',
    'report_data' => true,
    'handlers' => [
        'exception' => true,
        'error' => true,
    ],
]);

// Make honeybadger instance globally available
global $honeybadger;

?>