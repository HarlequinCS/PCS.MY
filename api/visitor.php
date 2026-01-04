<?php
/**
 * Visitor Counter API
 * Tracks and returns visitor count
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

// Rate limiting - prevent abuse
require_once __DIR__ . '/../includes/rate_limit.php';
checkRateLimit('visitor', 30, 60); // 30 requests per minute per IP

define('DB_CONFIG_LOADED', true);
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/db.php';

// Get visitor IP
function getVisitorIP() {
    $ipKeys = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];
    foreach ($ipKeys as $key) {
        if (array_key_exists($key, $_SERVER) === true) {
            foreach (explode(',', $_SERVER[$key]) as $ip) {
                $ip = trim($ip);
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                    return $ip;
                }
            }
        }
    }
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST') {
    // Track new visitor
    try {
        $pdo = getDBConnection();
        
        // Check if visitors table exists, if not create it
        $pdo->exec("CREATE TABLE IF NOT EXISTS visitors (
            visitor_id INT AUTO_INCREMENT PRIMARY KEY,
            ip_address VARCHAR(45) NOT NULL,
            visit_date DATETIME DEFAULT CURRENT_TIMESTAMP,
            user_agent TEXT,
            UNIQUE KEY unique_visit (ip_address, visit_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        
        $ip = getVisitorIP();
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        // Insert visitor (ignore duplicates)
        // visit_date: Use UTC timezone helper to store in UTC
        $stmt = $pdo->prepare("INSERT IGNORE INTO visitors (ip_address, user_agent, visit_date) VALUES (?, ?, ?)");
        $stmt->execute([$ip, $userAgent, getCurrentDateTimeUTC()]);
        
        // Get total count
        $stmt = $pdo->query("SELECT COUNT(DISTINCT ip_address) as total FROM visitors");
        $result = $stmt->fetch();
        
        echo json_encode([
            'success' => true,
            'total_visitors' => (int)$result['total'],
            'message' => 'Visit recorded'
        ]);
    } catch (Exception $e) {
        error_log("Visitor tracking error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'Failed to record visit']);
    }
} elseif ($method === 'GET') {
    // Get visitor count
    try {
        $pdo = getDBConnection();
        
        // Check if table exists
        $stmt = $pdo->query("SHOW TABLES LIKE 'visitors'");
        if ($stmt->rowCount() === 0) {
            echo json_encode(['success' => true, 'total_visitors' => 0]);
            exit;
        }
        
        $stmt = $pdo->query("SELECT COUNT(DISTINCT ip_address) as total FROM visitors");
        $result = $stmt->fetch();
        
        echo json_encode([
            'success' => true,
            'total_visitors' => (int)$result['total']
        ]);
    } catch (Exception $e) {
        error_log("Visitor count error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'Failed to get visitor count']);
    }
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}

