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
    
    // Cache config to avoid multiple database queries (per request only)
    // Note: Static cache is cleared on each new HTTP request, so mode changes take effect immediately
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
        // Production credentials are hardcoded here (primary source)
        // Database settings are only used as fallback if hardcoded values are empty
        $productionConfig = [
            'endpoint' => 'https://toyyibpay.com/index.php/api/createBill', // Production endpoint
            'secret_key' => '58bq974i-wcu2-ypkz-4mbn-cvjuxtpil8kr', // Production secret key (hardcoded)
            'category_code' => 'lfhcsj8w' // Production category code (hardcoded)
        ];
        
        // If production mode, use hardcoded credentials first, database as fallback
        if ($mode === 'production') {
            // Only check database if hardcoded values are empty
            if (empty($productionConfig['secret_key']) || empty($productionConfig['category_code'])) {
                // Get production secret key from database (fallback)
                $secretStmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'toyyibpay_production_secret_key' LIMIT 1");
                $secretStmt->execute();
                $secretResult = $secretStmt->fetch(PDO::FETCH_ASSOC);
                if ($secretResult && !empty($secretResult['setting_value'])) {
                    $productionConfig['secret_key'] = $secretResult['setting_value'];
                }
                
                // Get production category code from database (fallback)
                $categoryStmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'toyyibpay_production_category_code' LIMIT 1");
                $categoryStmt->execute();
                $categoryResult = $categoryStmt->fetch(PDO::FETCH_ASSOC);
                if ($categoryResult && !empty($categoryResult['setting_value'])) {
                    $productionConfig['category_code'] = $categoryResult['setting_value'];
                }
            }
            
            // Validate production credentials are set
            if (empty($productionConfig['secret_key']) || empty($productionConfig['category_code'])) {
                error_log('ToyyibPay Production mode enabled but credentials not configured. Falling back to staging.');
                $config = $stagingConfig;
                return $config;
            }
            
            $config = $productionConfig;
            error_log('ToyyibPay: Production mode active - Using endpoint: ' . $config['endpoint']);
        } else {
            // Staging mode (default)
            $config = $stagingConfig;
            error_log('ToyyibPay: Staging mode active - Using endpoint: ' . $config['endpoint']);
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

