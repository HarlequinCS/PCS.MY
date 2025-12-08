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
        
        // Fix AUTO_INCREMENT BEFORE inserting (comment_id = 0 issue)
        // This happens when AUTO_INCREMENT tries to use 0 which already exists
        try {
            // Check if there's a row with comment_id = 0
            $zeroRow = $pdo->query("SELECT comment_id FROM comment WHERE comment_id = 0 LIMIT 1")->fetch();
            
            // Get current AUTO_INCREMENT value
            $tableStatus = $pdo->query("SHOW TABLE STATUS LIKE 'comment'")->fetch();
            $autoIncrement = $tableStatus['Auto_increment'] ?? null;
            
            // Get max comment_id (excluding 0)
            $maxId = $pdo->query("SELECT MAX(comment_id) as max_id FROM comment WHERE comment_id > 0")->fetch();
            $maxCommentId = $maxId['max_id'] ?? 0;
            
            // Calculate next ID
            $nextId = $maxCommentId + 1;
            if ($nextId < 1) $nextId = 1; // Ensure it's at least 1
            
            // If AUTO_INCREMENT is 0, 1, or less than max, OR if there's a row with ID 0, fix it
            if ($zeroRow || $autoIncrement == 0 || $autoIncrement == 1 || $autoIncrement <= $maxCommentId) {
                $pdo->exec("ALTER TABLE comment AUTO_INCREMENT = $nextId");
                error_log("Fixed AUTO_INCREMENT from $autoIncrement to: $nextId (found row with ID 0: " . ($zeroRow ? 'yes' : 'no') . ")");
            }
        } catch (PDOException $e) {
            error_log("AUTO_INCREMENT check failed: " . $e->getMessage());
            // Continue anyway - will try to fix on error
        }
        
        // Insert comment - Based on your exact table structure
        // IMPORTANT: Do NOT specify comment_id - let AUTO_INCREMENT handle it
        $maxRetries = 2;
        $commentId = null;
        
        for ($attempt = 0; $attempt < $maxRetries; $attempt++) {
            try {
                // Insert with comment, status, and ip_address
                // comment_id will auto-increment (don't specify it)
                $stmt = $pdo->prepare("INSERT INTO comment (comment, status, ip_address) VALUES (?, ?, ?)");
                $result = $stmt->execute([$comment, 'pending', $ip]);
                
                if ($result === false) {
                    $errorInfo = $stmt->errorInfo();
                    throw new PDOException("INSERT returned false. Error: " . ($errorInfo[2] ?? 'Unknown error'));
                }
                
                $commentId = $pdo->lastInsertId();
                
                // Success - break out of retry loop
                if ($commentId && $commentId > 0) {
                    break;
                }
                
            } catch (PDOException $e) {
                // Check if it's the duplicate entry error
                if ($e->getCode() == 23000 && strpos($e->getMessage(), 'Duplicate entry') !== false && strpos($e->getMessage(), "key 'PRIMARY'") !== false) {
                    // This is the AUTO_INCREMENT = 0 issue
                    error_log("Detected duplicate entry '0' error - fixing AUTO_INCREMENT");
                    
                    try {
                        // Fix AUTO_INCREMENT
                        $maxId = $pdo->query("SELECT MAX(comment_id) as max_id FROM comment")->fetch();
                        $nextId = ($maxId['max_id'] ?? 0) + 1;
                        if ($nextId < 1) $nextId = 1;
                        $pdo->exec("ALTER TABLE comment AUTO_INCREMENT = $nextId");
                        error_log("Fixed AUTO_INCREMENT to: $nextId");
                        
                        // If this was the first attempt, retry
                        if ($attempt < $maxRetries - 1) {
                            continue; // Retry the insert
                        }
                    } catch (PDOException $e2) {
                        error_log("Failed to fix AUTO_INCREMENT: " . $e2->getMessage());
                    }
                }
                
                // If we're on the last attempt or it's not the duplicate error, throw
                if ($attempt == $maxRetries - 1) {
                    throw $e;
                }
            }
        }
        
        // Verify we got a valid ID
        if (!$commentId || $commentId == 0) {
            throw new Exception("Failed to insert comment - AUTO_INCREMENT may be broken. Please run fix_zero_id.php");
        }
        
        // Success - return response
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
        
        // Check if it's the duplicate entry error and try to fix it
        if ($e->getCode() == 23000 && strpos($e->getMessage(), 'Duplicate entry') !== false) {
            try {
                // Try to fix AUTO_INCREMENT one more time
                $pdo = getDBConnection();
                $maxId = $pdo->query("SELECT MAX(comment_id) as max_id FROM comment WHERE comment_id > 0")->fetch();
                $nextId = ($maxId['max_id'] ?? 0) + 1;
                if ($nextId < 1) $nextId = 1;
                $pdo->exec("ALTER TABLE comment AUTO_INCREMENT = $nextId");
                
                http_response_code(500);
                echo json_encode([
                    'error' => 'AUTO_INCREMENT was broken but has been fixed. Please try submitting again.',
                    'message' => 'The database has been fixed. Please refresh and try again.',
                    'fixed' => true,
                    'debug' => $e->getMessage()
                ]);
                exit;
            } catch (Exception $e2) {
                // Fix failed, show error
            }
        }
        
        http_response_code(500);
        echo json_encode([
            'error' => 'Database error occurred',
            'message' => $e->getMessage(),
            'code' => $e->getCode(),
            'sql_state' => $e->errorInfo[0] ?? null,
            'driver_code' => $e->errorInfo[1] ?? null,
            'driver_message' => $e->errorInfo[2] ?? null
        ]);
    } catch (Exception $e) {
        error_log("Comment submission error: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());
        http_response_code(500);
        echo json_encode([
            'error' => 'Failed to submit comment',
            'message' => $e->getMessage(),
            'type' => get_class($e)
        ]);
    } catch (Error $e) {
        error_log("Fatal error in comment submission: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());
        http_response_code(500);
        echo json_encode([
            'error' => 'A system error occurred',
            'message' => $e->getMessage(),
            'type' => get_class($e)
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

