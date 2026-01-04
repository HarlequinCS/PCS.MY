<?php
/**
 * Get Button Mode API
 * Returns the current button mode (launch/coming_soon) and ToyyibPay mode (staging/production) as JSON
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
    require_once __DIR__ . '/../includes/toyyibpay_config.php';
    
    $pdo = getDBConnection();
    
    // Get current button mode
    $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'button_mode' LIMIT 1");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $buttonMode = ($result && isset($result['setting_value'])) ? $result['setting_value'] : 'coming_soon';
    
    // Get current ToyyibPay mode
    $toyyibpayMode = getToyyibPayMode();
    
    // Get current button link from database
    $linkStmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'button_link' LIMIT 1");
    $linkStmt->execute();
    $linkResult = $linkStmt->fetch(PDO::FETCH_ASSOC);
    
    // Default link if not set in database
    $buttonLink = ($linkResult && isset($linkResult['setting_value']) && !empty($linkResult['setting_value'])) 
        ? $linkResult['setting_value'] 
        : '/order-baju.php';
    
    // Ensure link starts with / for relative paths
    if (!empty($buttonLink) && $buttonLink[0] !== '/') {
        $buttonLink = '/' . $buttonLink;
    }
    
    // Determine if Buy Now button should redirect to order page
    // Button redirects to order page if:
    // 1. Button mode is 'launch' (enables order functionality)
    // 2. ToyyibPay mode is 'production' (real payments enabled)
    // OR if button mode is 'launch' regardless of payment mode (for testing)
    $shouldRedirect = ($buttonMode === 'launch');
    
    echo json_encode([
        'success' => true,
        'button_mode' => $buttonMode,
        'toyyibpay_mode' => $toyyibpayMode,
        'button_link' => $buttonLink,
        'isLaunch' => ($buttonMode === 'launch'),
        'isProduction' => (strtolower(trim($toyyibpayMode)) === 'production'),
        'shouldRedirect' => $shouldRedirect
    ]);
    
} catch (Exception $e) {
    error_log('Error getting button mode: ' . $e->getMessage());
    // Default to coming_soon on error
    echo json_encode([
        'success' => false,
        'button_mode' => 'coming_soon',
        'toyyibpay_mode' => 'staging',
        'button_link' => '/order-baju.php',
        'isLaunch' => false,
        'isProduction' => false,
        'shouldRedirect' => false,
        'error' => 'Failed to get mode'
    ]);
}

