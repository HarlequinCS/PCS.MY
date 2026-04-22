<?php
/**
 * Get ToyyibPay Mode API
 * Returns the current ToyyibPay mode (staging/production) as JSON
 * Public endpoint for frontend JavaScript to check mode
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

// Load Honeybadger error monitoring
require_once __DIR__ . '/../config/honeybadger.php';

// Define database config constant
if (!defined('DB_CONFIG_LOADED')) {
    define('DB_CONFIG_LOADED', true);
}

try {
    require_once __DIR__ . '/../config/database.php';
    require_once __DIR__ . '/../includes/db.php';
    require_once __DIR__ . '/../includes/toyyibpay_config.php';
    
    $mode = getToyyibPayMode();
    
    echo json_encode([
        'success' => true,
        'mode' => $mode,
        'isProduction' => ($mode === 'production')
    ]);
    
} catch (Exception $e) {
    error_log('Error getting ToyyibPay mode: ' . $e->getMessage());
    // Default to staging on error
    echo json_encode([
        'success' => false,
        'mode' => 'staging',
        'isProduction' => false,
        'error' => 'Failed to get mode'
    ]);
}

