<?php
/**
 * Toggle Button Mode API
 * Handles switching between "launch" and "coming_soon" button modes
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
    $currentModeStmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'button_mode' LIMIT 1");
    $currentModeStmt->execute();
    $currentModeResult = $currentModeStmt->fetch(PDO::FETCH_ASSOC);
    $currentMode = ($currentModeResult && isset($currentModeResult['setting_value'])) 
        ? $currentModeResult['setting_value'] 
        : 'coming_soon';
    
    // Toggle mode
    $newMode = ($currentMode === 'coming_soon') ? 'launch' : 'coming_soon';
    
    // Validate mode value (security: only allow launch or coming_soon)
    if (!in_array($newMode, ['launch', 'coming_soon'])) {
        throw new Exception('Invalid mode');
    }
    
    // Update mode in database using prepared statement
    $updateStmt = $pdo->prepare("
        INSERT INTO settings (setting_key, setting_value) 
        VALUES ('button_mode', :mode)
        ON DUPLICATE KEY UPDATE setting_value = :mode2
    ");
    $updateStmt->execute([
        ':mode' => $newMode,
        ':mode2' => $newMode
    ]);
    
    // Log the change
    error_log("Button mode changed from '{$currentMode}' to '{$newMode}' by admin: " . ($_SESSION['admin_username'] ?? 'unknown'));
    
    echo json_encode([
        'success' => true,
        'mode' => $newMode,
        'message' => "Switched to {$newMode} mode"
    ]);
    
} catch (PDOException $e) {
    $errorMessage = $e->getMessage();
    error_log('Error toggling button mode: ' . $errorMessage);
    
    // Check if it's a table doesn't exist error
    if (strpos($errorMessage, "doesn't exist") !== false || 
        strpos($errorMessage, "Table") !== false ||
        strpos($errorMessage, "Unknown table") !== false) {
        $errorMessage = 'Settings table does not exist. Please run the database setup script first.';
    }
    
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'error' => $errorMessage
    ]);
} catch (Exception $e) {
    error_log('Error toggling button mode: ' . $e->getMessage());
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

