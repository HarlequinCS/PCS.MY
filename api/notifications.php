<?php
/**
 * Email Notifications API
 * Handles email subscription for "Coming Soon" notifications
 */

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Check if required files exist
if (!file_exists(__DIR__ . '/../config/database.php')) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Configuration file not found']);
    exit;
}

if (!file_exists(__DIR__ . '/../includes/db.php')) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database helper file not found']);
    exit;
}

define('DB_CONFIG_LOADED', true);
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/db.php';

try {
    $pdo = getDBConnection();
    
    // Create table if it doesn't exist
    $createTableSQL = "
    CREATE TABLE IF NOT EXISTS `email_notifications` (
      `notification_id` INT AUTO_INCREMENT PRIMARY KEY,
      `email` VARCHAR(255) NOT NULL,
      `ip_address` VARCHAR(45) DEFAULT NULL,
      `user_agent` TEXT,
      `status` VARCHAR(20) DEFAULT 'active',
      `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
      `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      UNIQUE KEY `unique_email` (`email`),
      INDEX `idx_email` (`email`),
      INDEX `idx_status` (`status`),
      INDEX `idx_created` (`created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";
    
    $pdo->exec($createTableSQL);
    
} catch (PDOException $e) {
    error_log("Database setup error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database setup failed']);
    exit;
}

// Handle POST request (subscribe email)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Validate email
    if (!isset($input['email']) || empty(trim($input['email']))) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Email is required'
        ]);
        exit;
    }
    
    $email = filter_var(trim($input['email']), FILTER_SANITIZE_EMAIL);
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Invalid email format'
        ]);
        exit;
    }
    
    // Get IP address and user agent
    $ip_address = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? null;
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;
    
    try {
        // Check if email already exists
        $checkStmt = $pdo->prepare("SELECT notification_id, status FROM email_notifications WHERE email = ?");
        $checkStmt->execute([$email]);
        $existing = $checkStmt->fetch();
        
        if ($existing) {
            // Email already exists
            if ($existing['status'] === 'active') {
                http_response_code(200);
                echo json_encode([
                    'success' => true,
                    'message' => 'You\'re already on our notification list!',
                    'already_subscribed' => true
                ]);
                exit;
            } else {
                // Reactivate if previously unsubscribed
                $updateStmt = $pdo->prepare("UPDATE email_notifications SET status = 'active', updated_at = NOW(), ip_address = ?, user_agent = ? WHERE email = ?");
                $updateStmt->execute([$ip_address, $user_agent, $email]);
                
                http_response_code(200);
                echo json_encode([
                    'success' => true,
                    'message' => 'Welcome back! You\'ve been re-added to our notification list.',
                    'reactivated' => true
                ]);
                exit;
            }
        }
        
        // Insert new email
        $stmt = $pdo->prepare("INSERT INTO email_notifications (email, ip_address, user_agent, status) VALUES (?, ?, ?, 'active')");
        $stmt->execute([$email, $ip_address, $user_agent]);
        
        $notificationId = $pdo->lastInsertId();
        
        http_response_code(201);
        echo json_encode([
            'success' => true,
            'message' => 'Thank you! We\'ll notify you when we launch.',
            'notification_id' => $notificationId
        ]);
        
    } catch (PDOException $e) {
        error_log("Notification subscription error: " . $e->getMessage());
        
        // Check if it's a duplicate entry error
        if ($e->getCode() == 23000) {
            http_response_code(200);
            echo json_encode([
                'success' => true,
                'message' => 'You\'re already on our notification list!',
                'already_subscribed' => true
            ]);
        } else {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => 'Failed to subscribe. Please try again later.'
            ]);
        }
    }
    
} else if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Optional: Get notification stats (for admin use, can add authentication)
    try {
        $totalStmt = $pdo->query("SELECT COUNT(*) as total FROM email_notifications WHERE status = 'active'");
        $total = $totalStmt->fetch()['total'];
        
        echo json_encode([
            'success' => true,
            'total_subscribers' => (int)$total
        ]);
    } catch (PDOException $e) {
        error_log("Notification stats error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Failed to fetch stats'
        ]);
    }
} else {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'error' => 'Method not allowed'
    ]);
}

