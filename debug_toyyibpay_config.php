<?php
/**
 * Debug Script for ToyyibPay Configuration
 * Run this to see what mode and endpoint are being used
 */

// Define database config constant
if (!defined('DB_CONFIG_LOADED')) {
    define('DB_CONFIG_LOADED', true);
}

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/toyyibpay_config.php';

header('Content-Type: text/plain');

echo "=== ToyyibPay Configuration Debug ===\n\n";

try {
    $pdo = getDBConnection();
    
    // Get current mode from database
    $modeStmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'toyyibpay_mode' LIMIT 1");
    $modeStmt->execute();
    $modeResult = $modeStmt->fetch(PDO::FETCH_ASSOC);
    $databaseMode = ($modeResult && isset($modeResult['setting_value'])) ? $modeResult['setting_value'] : 'staging';
    
    echo "Database Mode: " . $databaseMode . "\n";
    echo "Mode from getToyyibPayMode(): " . getToyyibPayMode() . "\n\n";
    
    // Get config
    $config = getToyyibPayConfig();
    
    echo "=== Configuration Being Used ===\n";
    echo "Endpoint: " . $config['endpoint'] . "\n";
    echo "Secret Key: " . substr($config['secret_key'], 0, 15) . "...\n";
    echo "Category Code: " . $config['category_code'] . "\n\n";
    
    echo "=== Expected Values ===\n";
    if ($databaseMode === 'production') {
        echo "Expected Endpoint: https://toyyibpay.com/index.php/api/createBill\n";
        echo "Expected Category Code: lfhcsj8w\n";
    } else {
        echo "Expected Endpoint: https://dev.toyyibpay.com/index.php/api/createBill\n";
        echo "Expected Category Code: g3ii2ms2\n";
    }
    
    echo "\n=== Match Check ===\n";
    if ($databaseMode === 'production') {
        if ($config['endpoint'] === 'https://toyyibpay.com/index.php/api/createBill') {
            echo "✓ Endpoint CORRECT (production)\n";
        } else {
            echo "✗ Endpoint WRONG! Expected production but got: " . $config['endpoint'] . "\n";
        }
        
        if ($config['category_code'] === 'lfhcsj8w') {
            echo "✓ Category Code CORRECT (production)\n";
        } else {
            echo "✗ Category Code WRONG! Expected 'lfhcsj8w' but got: " . $config['category_code'] . "\n";
        }
    } else {
        if ($config['endpoint'] === 'https://dev.toyyibpay.com/index.php/api/createBill') {
            echo "✓ Endpoint CORRECT (staging)\n";
        } else {
            echo "✗ Endpoint WRONG! Expected staging but got: " . $config['endpoint'] . "\n";
        }
        
        if ($config['category_code'] === 'g3ii2ms2') {
            echo "✓ Category Code CORRECT (staging)\n";
        } else {
            echo "✗ Category Code WRONG! Expected 'g3ii2ms2' but got: " . $config['category_code'] . "\n";
        }
    }
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString();
}

