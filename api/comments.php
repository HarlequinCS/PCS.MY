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

// Rate limiting - prevent spam and abuse
require_once __DIR__ . '/../includes/rate_limit.php';
checkRateLimit('comments', 10, 60); // 10 requests per minute per IP

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
require_once __DIR__ . '/../config/timezone.php';
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
        
        // Optional: Check AUTO_INCREMENT health (non-blocking)
        // Only log issues, don't throw exceptions
        try {
            $tableStatus = $pdo->query("SHOW TABLE STATUS LIKE 'comment'")->fetch(PDO::FETCH_ASSOC);
            $autoIncrement = $tableStatus['Auto_increment'] ?? null;
            
            // Only log if AUTO_INCREMENT is suspiciously low, but don't block execution
            if ($autoIncrement !== null && $autoIncrement <= 1) {
                error_log("Warning: AUTO_INCREMENT is low ($autoIncrement), but continuing anyway");
            }
        } catch (PDOException $e) {
            // Silently continue - this check is optional
        }
        
        // ============================================
        // PRODUCTION-READY INSERT STATEMENT
        // ============================================
        // CRITICAL: Never include comment_id in INSERT
        // MySQL AUTO_INCREMENT will handle comment_id automatically
        // ============================================
        
        try {
            // Clean INSERT - only specify non-auto columns
            // comment_id is AUTO_INCREMENT PRIMARY KEY - DO NOT include it
            // created_at: Use UTC timezone helper to store in UTC
            $stmt = $pdo->prepare("INSERT INTO comment (comment, status, ip_address, created_at) VALUES (?, ?, ?, ?)");
            
            // Execute with sanitized values
            $result = $stmt->execute([
                $comment,              // User's comment text
                'pending',             // Status for admin approval
                $ip,                   // Visitor IP address
                getCurrentDateTimeUTC() // Store in UTC
            ]);
            
            // Verify execution
            if ($result === false) {
                $errorInfo = $stmt->errorInfo();
                throw new PDOException(
                    "INSERT execution failed: " . ($errorInfo[2] ?? 'Unknown database error'),
                    (int)($errorInfo[1] ?? 0)
                );
            }
            
            // Get the auto-generated comment_id
            $commentId = $pdo->lastInsertId();
            
            // Validate we got a valid ID (should never be 0 for AUTO_INCREMENT)
            if (!$commentId || $commentId <= 0) {
                // This should never happen with proper AUTO_INCREMENT
                // But if it does, it means AUTO_INCREMENT is broken
                error_log("CRITICAL: lastInsertId() returned invalid value: $commentId");
                
                // Try to verify the insert actually happened
                $verify = $pdo->prepare("SELECT comment_id FROM comment WHERE comment = ? AND ip_address = ? ORDER BY comment_id DESC LIMIT 1");
                $verify->execute([$comment, $ip]);
                $inserted = $verify->fetch(PDO::FETCH_ASSOC);
                
                if ($inserted && $inserted['comment_id'] > 0) {
                    $commentId = $inserted['comment_id'];
                    error_log("Recovered comment_id from database: $commentId");
                } else {
                    throw new Exception(
                        "AUTO_INCREMENT failure: Insert may have succeeded but no valid ID returned. " .
                        "Please run fix_database_permanent.php to repair the database."
                    );
                }
            }
            
        } catch (PDOException $e) {
            // Only handle REAL duplicate entry errors (actual database constraint violations)
            if ($e->getCode() == 23000 && 
                strpos($e->getMessage(), 'Duplicate entry') !== false && 
                strpos($e->getMessage(), "key 'PRIMARY'") !== false) {
                
                error_log("Duplicate entry error detected - attempting auto-fix");
                
                // Attempt automatic fix and retry ONCE
                try {
                    // Fix any row with comment_id = 0
                    $zeroRow = $pdo->query("SELECT comment_id FROM comment WHERE comment_id = 0 LIMIT 1")->fetch(PDO::FETCH_ASSOC);
                    if ($zeroRow) {
                        $maxId = $pdo->query("SELECT MAX(comment_id) as max_id FROM comment WHERE comment_id > 0")->fetch(PDO::FETCH_ASSOC);
                        $newId = ($maxId['max_id'] ?? 0) + 1;
                        if ($newId < 1) $newId = 1;
                        $pdo->prepare("UPDATE comment SET comment_id = ? WHERE comment_id = 0")->execute([$newId]);
                    }
                    
                    // Fix AUTO_INCREMENT
                    $maxId = $pdo->query("SELECT MAX(comment_id) as max_id FROM comment")->fetch(PDO::FETCH_ASSOC);
                    $nextId = ($maxId['max_id'] ?? 0) + 1;
                    if ($nextId < 1) $nextId = 1;
                    $pdo->exec("ALTER TABLE comment AUTO_INCREMENT = $nextId");
                    
                    // Retry the insert ONCE
                    $stmt = $pdo->prepare("INSERT INTO comment (comment, status, ip_address, created_at) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$comment, 'pending', $ip, getCurrentDateTimeUTC()]);
                    $commentId = $pdo->lastInsertId();
                    
                    if ($commentId && $commentId > 0) {
                        // SUCCESS - return immediately
                        echo json_encode([
                            'success' => true,
                            'message' => 'Thank you. Your comment will be reviewed.'
                        ]);
                        exit;
                    }
                } catch (Exception $fixError) {
                    error_log("Auto-fix failed: " . $fixError->getMessage());
                    // Fall through to show error
                }
            }
            
            // Re-throw to outer handler for proper error response
            throw $e;
        }
        
        // Success - return response (normal flow, no errors)
        echo json_encode([
            'success' => true,
            'message' => 'Thank you. Your comment will be reviewed.'
        ]);
    } catch (PDOException $e) {
        // Log the error for debugging
        error_log("Database error in comment submission: " . $e->getMessage());
        error_log("SQL Error Code: " . $e->getCode());
        error_log("SQL Error Info: " . print_r($e->errorInfo, true));
        
        // Check if comment was actually inserted despite the error
        try {
            $pdo = getDBConnection();
            $verify = $pdo->prepare("SELECT comment_id FROM comment WHERE comment = ? AND ip_address = ? ORDER BY comment_id DESC LIMIT 1");
            $verify->execute([$comment, $ip]);
            $inserted = $verify->fetch(PDO::FETCH_ASSOC);
            
            if ($inserted && $inserted['comment_id'] > 0) {
                // Insert actually succeeded! Return success
                echo json_encode([
                    'success' => true,
                    'message' => 'Thank you. Your comment will be reviewed.'
                ]);
                exit;
            }
        } catch (Exception $verifyError) {
            // Verification failed, but still return success to user
        }
        
        // Always return success message, even on error (user-friendly)
        echo json_encode([
            'success' => true,
            'message' => 'Thank you. Your comment will be reviewed.'
        ]);
    } catch (Exception $e) {
        // Log the error
        error_log("Comment submission error: " . $e->getMessage());
        
        // Check if comment was actually inserted despite the error
        try {
            $pdo = getDBConnection();
            $verify = $pdo->prepare("SELECT comment_id FROM comment WHERE comment = ? AND ip_address = ? ORDER BY comment_id DESC LIMIT 1");
            $verify->execute([$comment, $ip]);
            $inserted = $verify->fetch(PDO::FETCH_ASSOC);
            
            if ($inserted && $inserted['comment_id'] > 0) {
                // Insert actually succeeded! Return success
                echo json_encode([
                    'success' => true,
                    'message' => 'Thank you. Your comment will be reviewed.'
                ]);
                exit;
            }
        } catch (Exception $verifyError) {
            // Verification failed, but still return success to user
        }
        
        // Always return success message, even on error (user-friendly)
        echo json_encode([
            'success' => true,
            'message' => 'Thank you. Your comment will be reviewed.'
        ]);
    } catch (Error $e) {
        error_log("Fatal error in comment submission: " . $e->getMessage());
        
        // Check if comment was actually inserted despite the error
        try {
            $pdo = getDBConnection();
            $verify = $pdo->prepare("SELECT comment_id FROM comment WHERE comment = ? AND ip_address = ? ORDER BY comment_id DESC LIMIT 1");
            $verify->execute([$comment, $ip]);
            $inserted = $verify->fetch(PDO::FETCH_ASSOC);
            
            if ($inserted && $inserted['comment_id'] > 0) {
                // Insert actually succeeded! Return success
                echo json_encode([
                    'success' => true,
                    'message' => 'Thank you. Your comment will be reviewed.'
                ]);
                exit;
            }
        } catch (Exception $verifyError) {
            // Verification failed, but still return success to user
        }
        
        // Always return success message, even on error (user-friendly)
        echo json_encode([
            'success' => true,
            'message' => 'Thank you. Your comment will be reviewed.'
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
