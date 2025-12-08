<?php
/**
 * Comments API
 * Handles comment submission and retrieval
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

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
    // Submit new comment
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($input['comment']) || empty(trim($input['comment']))) {
            http_response_code(400);
            echo json_encode(['error' => 'Comment is required']);
            exit;
        }
        
        $comment = sanitizeInput($input['comment']);
        
        // Validate comment length
        if (strlen($comment) > 1000) {
            http_response_code(400);
            echo json_encode(['error' => 'Comment is too long (max 1000 characters)']);
            exit;
        }
        
        // Basic spam check (simple rate limiting)
        $ip = getVisitorIP();
        $pdo = getDBConnection();
        
        // Check for recent comments from same IP (max 1 per 5 minutes)
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM comment WHERE ip_address = ? AND created_at > DATE_SUB(NOW(), INTERVAL 5 MINUTE)");
        $stmt->execute([$ip]);
        $result = $stmt->fetch();
        
        if ($result['count'] > 0) {
            http_response_code(429);
            echo json_encode(['error' => 'Please wait a few minutes before posting again']);
            exit;
        }
        
        // Ensure table has required columns
        try {
            $pdo->exec("ALTER TABLE comment ADD COLUMN IF NOT EXISTS ip_address VARCHAR(45)");
            $pdo->exec("ALTER TABLE comment ADD COLUMN IF NOT EXISTS created_at DATETIME DEFAULT CURRENT_TIMESTAMP");
            $pdo->exec("ALTER TABLE comment ADD COLUMN IF NOT EXISTS status VARCHAR(20) DEFAULT 'approved'");
        } catch (PDOException $e) {
            // Columns might already exist, continue
        }
        
        // Insert comment
        $stmt = $pdo->prepare("INSERT INTO comment (comment, ip_address, created_at, status) VALUES (?, ?, NOW(), 'approved')");
        $stmt->execute([$comment, $ip]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Thank you for your comment!',
            'comment_id' => $pdo->lastInsertId()
        ]);
    } catch (Exception $e) {
        error_log("Comment submission error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'Failed to submit comment. Please try again.']);
    }
} elseif ($method === 'GET') {
    // Get all comments
    try {
        $pdo = getDBConnection();
        
        // Check if table exists and has required columns
        try {
            $pdo->exec("ALTER TABLE comment ADD COLUMN IF NOT EXISTS created_at DATETIME DEFAULT CURRENT_TIMESTAMP");
            $pdo->exec("ALTER TABLE comment ADD COLUMN IF NOT EXISTS status VARCHAR(20) DEFAULT 'approved'");
        } catch (PDOException $e) {
            // Continue if columns exist
        }
        
        $stmt = $pdo->query("SELECT comment_id, comment, created_at FROM comment WHERE status = 'approved' ORDER BY created_at DESC LIMIT 50");
        $comments = $stmt->fetchAll();
        
        echo json_encode([
            'success' => true,
            'comments' => $comments,
            'count' => count($comments)
        ]);
    } catch (Exception $e) {
        error_log("Comment retrieval error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'Failed to retrieve comments']);
    }
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}

