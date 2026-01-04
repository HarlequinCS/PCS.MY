<?php
/**
 * ToyyibPay Configuration Helper
 * Provides functions to get ToyyibPay configuration based on mode (staging/production)
 */

// Prevent direct access
if (!defined('DB_CONFIG_LOADED')) {
    http_response_code(403);
    die('Direct access not allowed');
}

/**
 * Get ToyyibPay configuration based on current mode
 * 
 * @return array Configuration array with 'endpoint', 'secret_key', 'category_code'
 */
function getToyyibPayConfig() {
    static $config = null;
    
    // Cache config to avoid multiple database queries
    if ($config !== null) {
        return $config;
    }
    
    try {
        $pdo = getDBConnection();
        
        // Get current mode (staging or production)
        $modeStmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'toyyibpay_mode' LIMIT 1");
        $modeStmt->execute();
        $modeResult = $modeStmt->fetch(PDO::FETCH_ASSOC);
        $mode = ($modeResult && isset($modeResult['setting_value'])) ? $modeResult['setting_value'] : 'staging';
        
        // Staging configuration (default)
        $stagingConfig = [
            'endpoint' => 'https://dev.toyyibpay.com/index.php/api/createBill',
            'secret_key' => 'dh1kldj2-j8db-ofm0-qauv-e037egz4oiui', // Staging secret key
            'category_code' => 'g3ii2ms2' // Staging category code
        ];
        
        // Production configuration
        // TODO: Insert your production credentials below
        $productionConfig = [
            'endpoint' => 'https://toyyibpay.com/index.php/api/createBill', // Production endpoint
            'secret_key' => '58bq974i-wcu2-ypkz-4mbn-cvjuxtpil8kr', // TODO: INSERT YOUR PRODUCTION SECRET KEY HERE
            'category_code' => 'lfhcsj8w' // TODO: INSERT YOUR PRODUCTION CATEGORY CODE HERE
        ];
        
        // If production mode, get credentials from database
        if ($mode === 'production') {
            // Get production secret key
            $secretStmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'toyyibpay_production_secret_key' LIMIT 1");
            $secretStmt->execute();
            $secretResult = $secretStmt->fetch(PDO::FETCH_ASSOC);
            $productionConfig['secret_key'] = ($secretResult && isset($secretResult['setting_value'])) 
                ? $secretResult['setting_value'] 
                : '';
            
            // Get production category code
            $categoryStmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'toyyibpay_production_category_code' LIMIT 1");
            $categoryStmt->execute();
            $categoryResult = $categoryStmt->fetch(PDO::FETCH_ASSOC);
            $productionConfig['category_code'] = ($categoryResult && isset($categoryResult['setting_value'])) 
                ? $categoryResult['setting_value'] 
                : '';
            
            // Validate production credentials are set
            if (empty($productionConfig['secret_key']) || empty($productionConfig['category_code'])) {
                error_log('ToyyibPay Production mode enabled but credentials not configured. Falling back to staging.');
                $config = $stagingConfig;
                return $config;
            }
            
            $config = $productionConfig;
        } else {
            // Staging mode (default)
            $config = $stagingConfig;
        }
        
        return $config;
        
    } catch (PDOException $e) {
        error_log('Error getting ToyyibPay config: ' . $e->getMessage());
        // Return staging config as fallback
        return [
            'endpoint' => 'https://dev.toyyibpay.com/index.php/api/createBill',
            'secret_key' => 'dh1kldj2-j8db-ofm0-qauv-e037egz4oiui',
            'category_code' => 'g3ii2ms2'
        ];
    }
}

/**
 * Get current ToyyibPay mode
 * 
 * @return string 'staging' or 'production'
 */
function getToyyibPayMode() {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'toyyibpay_mode' LIMIT 1");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return ($result && isset($result['setting_value'])) ? $result['setting_value'] : 'staging';
    } catch (PDOException $e) {
        error_log('Error getting ToyyibPay mode: ' . $e->getMessage());
        return 'staging';
    }
}

