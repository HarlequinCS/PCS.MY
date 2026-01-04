<?php
/**
 * Get Button Link API
 * Returns the current Buy Now button link from database
 * Public endpoint for frontend JavaScript to get the redirect URL
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
    
    // Get current button link from database
    // Default to '/order-baju.php' if not set
    $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'button_link' LIMIT 1");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Default link if not set in database
    $buttonLink = ($result && isset($result['setting_value']) && !empty($result['setting_value'])) 
        ? $result['setting_value'] 
        : '/order-baju.php';
    
    // Ensure link starts with / for relative paths
    if (!empty($buttonLink) && $buttonLink[0] !== '/') {
        $buttonLink = '/' . $buttonLink;
    }
    
    echo json_encode([
        'success' => true,
        'link' => $buttonLink,
        'message' => 'Button link retrieved successfully'
    ]);
    
} catch (Exception $e) {
    error_log('Error getting button link: ' . $e->getMessage());
    // Default to order-baju.php on error
    echo json_encode([
        'success' => false,
        'link' => '/order-baju.php',
        'error' => 'Failed to get button link',
        'message' => 'Using default link'
    ]);
}

