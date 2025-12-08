<?php
/**
 * Comments API
 * Handles comment submission and retrieval
 */

// Enable error reporting for debugging (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors to users
ini_set('log_errors', 1);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

// Check if required files exist
if (!file_exists(__DIR__ . '/../config/database.php')) {
    http_response_code(500);
    echo json_encode(['error' => 'Configuration file not found']);
    exit;
}

if (!file_exists(__DIR__ . '/../includes/db.php')) {
    http_response_code(500);
    echo json_encode(['error' => 'Database helper file not found']);
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
    // Submit new comment
    try {
        // Get input
        $rawInput = file_get_contents('php://input');
        $input = json_decode($rawInput, true);
        
        // Check if JSON decode failed
        if (json_last_error() !== JSON_ERROR_NONE) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid JSON data']);
            exit;
        }
        
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
        
        // Get database connection
        $ip = getVisitorIP();
        $pdo = getDBConnection();
        
        if (!$pdo) {
            throw new Exception("Database connection failed");
        }
        
        // Check if table exists first
        $tableExists = false;
        try {
            $tableExists = $pdo->query("SHOW TABLES LIKE 'comment'")->rowCount() > 0;
        } catch (PDOException $e) {
            error_log("Table check error: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
            http_response_code(500);
            echo json_encode(['error' => 'Database error. Please contact support.']);
            exit;
        }
        
        if (!$tableExists) {
            // Create table if it doesn't exist
            try {
                $pdo->exec("CREATE TABLE IF NOT EXISTS comment (
                    comment_id INT AUTO_INCREMENT PRIMARY KEY,
                    comment TEXT NOT NULL,
                    ip_address VARCHAR(45) DEFAULT NULL,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    status VARCHAR(20) DEFAULT 'pending',
                    INDEX idx_status (status),
                    INDEX idx_created (created_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            } catch (PDOException $e) {
                error_log("Table creation error: " . $e->getMessage());
                http_response_code(500);
                echo json_encode(['error' => 'Database setup error. Please contact support.']);
                exit;
            }
        }
        
        // Ensure table has required columns (MySQL doesn't support IF NOT EXISTS in ALTER TABLE)
        try {
            $columns = $pdo->query("SHOW COLUMNS FROM comment")->fetchAll(PDO::FETCH_COLUMN);
            
            if (!in_array('ip_address', $columns)) {
                $pdo->exec("ALTER TABLE comment ADD COLUMN ip_address VARCHAR(45) DEFAULT NULL");
            }
            if (!in_array('created_at', $columns)) {
                $pdo->exec("ALTER TABLE comment ADD COLUMN created_at DATETIME DEFAULT CURRENT_TIMESTAMP");
            }
            if (!in_array('status', $columns)) {
                $pdo->exec("ALTER TABLE comment ADD COLUMN status VARCHAR(20) DEFAULT 'approved'");
            }
        } catch (PDOException $e) {
            // Columns might already exist, continue
            error_log("Column check error: " . $e->getMessage());
        }
        
        // Rate limiting removed - Admin approval system provides spam protection
        // Comments are set to 'pending' status and require admin approval before display
        
        // Insert comment - Based on your table structure: comment_id, comment, created_at, status
        // created_at has default, so we only need to specify comment and status
        try {
            // First, try to update status column default if it's set to 'approved'
            // Then insert with explicit 'pending' status
            $stmt = $pdo->prepare("INSERT INTO comment (comment, status) VALUES (?, ?)");
            $stmt->execute([$comment, 'pending']);
            $commentId = $pdo->lastInsertId();
            
            if (!$commentId) {
                // Try to get the last insert ID differently
                $lastId = $pdo->query("SELECT LAST_INSERT_ID() as id")->fetch();
                $commentId = $lastId['id'] ?? null;
                
                if (!$commentId) {
                    // If still no ID, the insert might have failed silently
                    // Try a simple insert without status to see if that works
                    $stmt2 = $pdo->prepare("INSERT INTO comment (comment) VALUES (?)");
                    $stmt2->execute([$comment]);
                    $commentId = $pdo->lastInsertId();
                    
                    if ($commentId) {
                        // Update status to pending after insert
                        $pdo->prepare("UPDATE comment SET status = 'pending' WHERE comment_id = ?")->execute([$commentId]);
                    } else {
                        throw new Exception("Failed to insert comment - no ID returned");
                    }
                }
            }
        } catch (PDOException $e) {
            error_log("Insert failed: " . $e->getMessage());
            error_log("SQL Error Code: " . $e->getCode());
            error_log("SQL Error Info: " . print_r($e->errorInfo, true));
            error_log("Comment text length: " . strlen($comment));
            error_log("Comment preview: " . substr($comment, 0, 100));
            
            // Try fallback - insert without status, then update
            try {
                error_log("Trying fallback insert method");
                $stmt = $pdo->prepare("INSERT INTO comment (comment) VALUES (?)");
                $stmt->execute([$comment]);
                $commentId = $pdo->lastInsertId();
                
                if ($commentId) {
                    // Try to update status if column exists
                    try {
                        $pdo->prepare("UPDATE comment SET status = 'pending' WHERE comment_id = ?")->execute([$commentId]);
                    } catch (PDOException $e2) {
                        error_log("Status update failed (column might not exist): " . $e2->getMessage());
                        // Continue anyway - comment is inserted
                    }
                } else {
                    throw new Exception("Fallback insert also failed");
                }
            } catch (Exception $e2) {
                error_log("Fallback insert failed: " . $e2->getMessage());
                throw new Exception("Database insert error: " . $e->getMessage() . " | Fallback: " . $e2->getMessage());
            }
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Thank you for your comment! It will be reviewed by our admin before being displayed.',
            'comment_id' => $commentId
        ]);
    } catch (PDOException $e) {
        error_log("Database error in comment submission: " . $e->getMessage());
        error_log("SQL Error Code: " . $e->getCode());
        error_log("SQL Error Info: " . print_r($e->errorInfo, true));
        error_log("Stack trace: " . $e->getTraceAsString());
        http_response_code(500);
        echo json_encode([
            'error' => 'Database error. Please try again or contact support.',
            'debug' => (ini_get('display_errors') ? $e->getMessage() : null)
        ]);
    } catch (Exception $e) {
        error_log("Comment submission error: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());
        http_response_code(500);
        echo json_encode([
            'error' => 'Failed to submit comment. Please try again.',
            'debug' => (ini_get('display_errors') ? $e->getMessage() : null)
        ]);
    } catch (Error $e) {
        error_log("Fatal error in comment submission: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());
        http_response_code(500);
        echo json_encode([
            'error' => 'A system error occurred. Please try again later.',
            'debug' => (ini_get('display_errors') ? $e->getMessage() : null)
        ]);
    }
} elseif ($method === 'GET') {
    // Get all comments
    try {
        $pdo = getDBConnection();
        
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
        
        // Check if table has required columns
        try {
            $columns = $pdo->query("SHOW COLUMNS FROM comment")->fetchAll(PDO::FETCH_COLUMN);
            
            if (!in_array('created_at', $columns)) {
                $pdo->exec("ALTER TABLE comment ADD COLUMN created_at DATETIME DEFAULT CURRENT_TIMESTAMP");
            }
            if (!in_array('status', $columns)) {
                $pdo->exec("ALTER TABLE comment ADD COLUMN status VARCHAR(20) DEFAULT 'approved'");
            }
        } catch (PDOException $e) {
            // Continue if columns exist
            error_log("Column check error: " . $e->getMessage());
        }
        
        // Query comments - handle both old and new table structures
        try {
            $stmt = $pdo->query("SELECT comment_id, comment, created_at FROM comment WHERE status = 'approved' ORDER BY created_at DESC LIMIT 50");
            $comments = $stmt->fetchAll();
        } catch (PDOException $e) {
            // Fallback to basic query if status column doesn't exist
            try {
                $stmt = $pdo->query("SELECT comment_id, comment, NOW() as created_at FROM comment ORDER BY comment_id DESC LIMIT 50");
                $comments = $stmt->fetchAll();
            } catch (PDOException $e2) {
                throw new Exception("Database error: " . $e2->getMessage());
            }
        }
        
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

