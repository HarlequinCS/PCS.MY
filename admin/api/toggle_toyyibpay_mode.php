<?php
/**
 * Toggle ToyyibPay Mode API
 * Handles switching between staging and production ToyyibPay API
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

define('DB_CONFIG_LOADED', true);
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../config.php';

// Require admin login
if (!isAdminLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

try {
    $pdo = getDBConnection();
    
    // Get current mode
    $currentModeStmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'toyyibpay_mode' LIMIT 1");
    $currentModeStmt->execute();
    $currentModeResult = $currentModeStmt->fetch(PDO::FETCH_ASSOC);
    $currentMode = ($currentModeResult && isset($currentModeResult['setting_value'])) 
        ? $currentModeResult['setting_value'] 
        : 'staging';
    
    // Toggle mode
    $newMode = ($currentMode === 'staging') ? 'production' : 'staging';
    
    // Validate mode value (security: only allow staging or production)
    if (!in_array($newMode, ['staging', 'production'])) {
        throw new Exception('Invalid mode');
    }
    
    // Update mode in database using prepared statement
    $updateStmt = $pdo->prepare("
        INSERT INTO settings (setting_key, setting_value) 
        VALUES ('toyyibpay_mode', :mode)
        ON DUPLICATE KEY UPDATE setting_value = :mode2
    ");
    $updateStmt->execute([
        ':mode' => $newMode,
        ':mode2' => $newMode
    ]);
    
    // Log the change
    error_log("ToyyibPay mode changed from '{$currentMode}' to '{$newMode}' by admin: " . ($_SESSION['admin_username'] ?? 'unknown'));
    
    echo json_encode([
        'success' => true,
        'mode' => $newMode,
        'message' => "Switched to {$newMode} mode"
    ]);
    
} catch (PDOException $e) {
    $errorMessage = $e->getMessage();
    error_log('Error toggling ToyyibPay mode: ' . $errorMessage);
    
    // Check if it's a table doesn't exist error
    if (strpos($errorMessage, "doesn't exist") !== false || 
        strpos($errorMessage, "Table") !== false ||
        strpos($errorMessage, "Unknown table") !== false) {
        $errorMessage = 'Settings table does not exist. Please run the database setup script: DB Update/add_toyyibpay_settings_table.php';
    }
    
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'error' => $errorMessage
    ]);
} catch (Exception $e) {
    error_log('Error toggling ToyyibPay mode: ' . $e->getMessage());
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

