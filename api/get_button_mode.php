<?php
/**
 * Get Button Mode API
 * Returns the current button mode (launch/coming_soon) as JSON
 * Public endpoint for frontend JavaScript to check button behavior
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

// Define database config constant
if (!defined('DB_CONFIG_LOADED')) {
    define('DB_CONFIG_LOADED', true);
}

try {
    require_once __DIR__ . '/../config/database.php';
    require_once __DIR__ . '/../includes/db.php';
    
    $pdo = getDBConnection();
    
    // Get current button mode
    $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'button_mode' LIMIT 1");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $mode = ($result && isset($result['setting_value'])) ? $result['setting_value'] : 'coming_soon';
    
    echo json_encode([
        'success' => true,
        'mode' => $mode,
        'isLaunch' => ($mode === 'launch')
    ]);
    
} catch (Exception $e) {
    error_log('Error getting button mode: ' . $e->getMessage());
    // Default to coming_soon on error
    echo json_encode([
        'success' => false,
        'mode' => 'coming_soon',
        'isLaunch' => false,
        'error' => 'Failed to get mode'
    ]);
}

