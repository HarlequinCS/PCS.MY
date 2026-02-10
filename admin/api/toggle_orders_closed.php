<?php
/**
 * Toggle Orders Closed API
 * Handles opening/closing order access for order-baju.php page
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
    
    // Get current status
    $currentStatusStmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'orders_closed' LIMIT 1");
    $currentStatusStmt->execute();
    $currentStatusResult = $currentStatusStmt->fetch(PDO::FETCH_ASSOC);
    $currentStatus = ($currentStatusResult && isset($currentStatusResult['setting_value'])) 
        ? $currentStatusResult['setting_value'] 
        : '0'; // Default: orders are open
    
    // Toggle status
    $newStatus = ($currentStatus === '1' || $currentStatus === 'true') ? '0' : '1';
    
    // Update status in database using prepared statement
    $updateStmt = $pdo->prepare("
        INSERT INTO settings (setting_key, setting_value) 
        VALUES ('orders_closed', :status)
        ON DUPLICATE KEY UPDATE setting_value = :status2
    ");
    $updateStmt->execute([
        ':status' => $newStatus,
        ':status2' => $newStatus
    ]);
    
    // Clear query cache for this setting
    if (file_exists(__DIR__ . '/../../includes/db_cache.php')) {
        require_once __DIR__ . '/../../includes/db_cache.php';
        clearSettingCache('orders_closed');
    }
    
    // Log the change
    $statusText = ($newStatus === '1') ? 'CLOSED' : 'OPENED';
    error_log("Orders access {$statusText} by admin: " . ($_SESSION['admin_username'] ?? 'unknown'));
    
    echo json_encode([
        'success' => true,
        'orders_closed' => $newStatus === '1',
        'message' => "Orders access {$statusText}"
    ]);
    
} catch (PDOException $e) {
    $errorMessage = $e->getMessage();
    error_log('Error toggling orders closed: ' . $errorMessage);
    
    // Check if it's a table doesn't exist error
    if (strpos($errorMessage, "doesn't exist") !== false || 
        strpos($errorMessage, "Table") !== false ||
        strpos($errorMessage, "Unknown table") !== false) {
        $errorMessage = 'Settings table does not exist. Please run the database setup script.';
    }
    
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'error' => $errorMessage
    ]);
} catch (Exception $e) {
    error_log('Error toggling orders closed: ' . $e->getMessage());
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
