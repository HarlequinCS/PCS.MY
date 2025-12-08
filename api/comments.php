<?php
/**
 * Comments API
 * Handles comment submission and retrieval
 */

// Disable all error reporting to frontend
error_reporting(0);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

// Check if required files exist
if (!file_exists(__DIR__ . '/../config/database.php') || !file_exists(__DIR__ . '/../includes/db.php')) {
    http_response_code(500);
    echo json_encode(['error' => 'Configuration error']);
    exit;
}

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

// Sanitize input
function sanitizeInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST') {
    // Wrap entire POST handler in try/catch to suppress all errors
    try {
        // Get input
        $rawInput = file_get_contents('php://input');
        $input = json_decode($rawInput, true);
        
        // Validate input
        if (json_last_error() !== JSON_ERROR_NONE || !isset($input['comment']) || empty(trim($input['comment']))) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid input']);
            exit;
        }
        
        $comment = sanitizeInput($input['comment']);
        
        // Validate comment length
        if (strlen($comment) > 1000) {
            http_response_code(400);
            echo json_encode(['error' => 'Comment too long']);
            exit;
        }
        
        // Get database connection
        $ip = getVisitorIP();
        $pdo = getDBConnection();
        
        if (!$pdo) {
            http_response_code(500);
            echo json_encode(['error' => 'Database connection failed']);
            exit;
        }
        
        // Ensure table exists (silent failure if it doesn't)
        try {
            $tableExists = $pdo->query("SHOW TABLES LIKE 'comment'")->rowCount() > 0;
            if (!$tableExists) {
                $pdo->exec("CREATE TABLE IF NOT EXISTS comment (
                    comment_id INT AUTO_INCREMENT PRIMARY KEY,
                    comment LONGTEXT NOT NULL,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    status VARCHAR(20) DEFAULT 'pending',
                    ip_address VARCHAR(45) DEFAULT NULL,
                    INDEX idx_status (status),
                    INDEX idx_created (created_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            }
        } catch (Exception $e) {
            // Silently continue
        }
        
        // ============================================
        // INSERT COMMENT - MAIN OPERATION
        // ============================================
        // Execute INSERT - this is the critical operation
        $stmt = $pdo->prepare("INSERT INTO comment (comment, status, ip_address) VALUES (?, ?, ?)");
        $stmt->execute([$comment, 'pending', $ip]);
        
        // Get the inserted ID
        $commentId = $pdo->lastInsertId();
        
        // If we got an ID (even if 0), consider it successful
        // Immediately return success and exit - nothing else should run
        echo json_encode([
            'success' => true,
            'message' => 'Thank you. Your comment has been submitted and will be reviewed.'
        ]);
        exit; // CRITICAL: Exit immediately after successful insert
        
    } catch (Exception $e) {
        // Suppress all exceptions - return generic error
        http_response_code(500);
        echo json_encode(['error' => 'Submission failed']);
        exit;
    } catch (Error $e) {
        // Suppress all fatal errors - return generic error
        http_response_code(500);
        echo json_encode(['error' => 'Submission failed']);
        exit;
    } catch (Throwable $e) {
        // Catch any other throwable - return generic error
        http_response_code(500);
        echo json_encode(['error' => 'Submission failed']);
        exit;
    }
    
} elseif ($method === 'GET') {
    // Get all approved comments
    try {
        $pdo = getDBConnection();
        
        if (!$pdo) {
            echo json_encode([
                'success' => true,
                'comments' => [],
                'count' => 0
            ]);
            exit;
        }
        
        // Check if table exists
        $tableExists = $pdo->query("SHOW TABLES LIKE 'comment'")->rowCount() > 0;
        
        if (!$tableExists) {
            echo json_encode([
                'success' => true,
                'comments' => [],
                'count' => 0
            ]);
            exit;
        }
        
        // Query approved comments
        try {
            $stmt = $pdo->query("SELECT comment_id, comment, created_at FROM comment WHERE status = 'approved' ORDER BY created_at DESC LIMIT 50");
            $comments = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            // Fallback if status column doesn't exist
            try {
                $stmt = $pdo->query("SELECT comment_id, comment, NOW() as created_at FROM comment ORDER BY comment_id DESC LIMIT 50");
                $comments = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (PDOException $e2) {
                $comments = [];
            }
        }
        
        echo json_encode([
            'success' => true,
            'comments' => $comments,
            'count' => count($comments)
        ]);
        
    } catch (Exception $e) {
        // Return empty comments on any error
        echo json_encode([
            'success' => true,
            'comments' => [],
            'count' => 0
        ]);
    } catch (Error $e) {
        echo json_encode([
            'success' => true,
            'comments' => [],
            'count' => 0
        ]);
    }
    
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}
