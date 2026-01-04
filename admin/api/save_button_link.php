<?php
/**
 * Save Button Link API
 * Handles saving the Buy Now button link setting to database
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
    // Get JSON input
    $rawInput = file_get_contents('php://input');
    $input = json_decode($rawInput, true);
    
    // Validate input
    if (!isset($input['link']) || empty(trim($input['link']))) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Button link is required'
        ]);
        exit;
    }
    
    $buttonLink = trim($input['link']);
    
    // Validate link format (basic validation)
    // Allow relative paths, absolute paths, and external URLs
    // Remove any potentially dangerous characters
    // Note: FILTER_SANITIZE_STRING is deprecated in PHP 8.1+, using htmlspecialchars instead
    $buttonLink = htmlspecialchars($buttonLink, ENT_QUOTES, 'UTF-8');
    
    // Ensure relative paths start with /
    if (!preg_match('/^(https?:\/\/|\/)/', $buttonLink)) {
        $buttonLink = '/' . ltrim($buttonLink, '/');
    }
    
    // Limit link length (reasonable limit)
    if (strlen($buttonLink) > 500) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Button link is too long (max 500 characters)'
        ]);
        exit;
    }
    
    $pdo = getDBConnection();
    
    // Save to database using INSERT ... ON DUPLICATE KEY UPDATE
    $stmt = $pdo->prepare("
        INSERT INTO settings (setting_key, setting_value) 
        VALUES ('button_link', :link)
        ON DUPLICATE KEY UPDATE setting_value = :link2
    ");
    $stmt->execute([
        ':link' => $buttonLink,
        ':link2' => $buttonLink
    ]);
    
    // Log the change
    error_log("Button link changed to '{$buttonLink}' by admin: " . ($_SESSION['admin_username'] ?? 'unknown'));
    
    echo json_encode([
        'success' => true,
        'link' => $buttonLink,
        'message' => 'Button link saved successfully'
    ]);
    
} catch (PDOException $e) {
    $errorMessage = $e->getMessage();
    error_log('Error saving button link: ' . $errorMessage);
    
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
    error_log('Error saving button link: ' . $e->getMessage());
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

