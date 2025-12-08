<?php
/**
 * Simplified Comments API - Clean Version
 * This is a simplified version to test if the basic functionality works
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

// Database credentials (direct connection for testing)
$host = 'localhost';
$dbname = 'u284911514_pcsmy';
$username = 'u284911514_pcsadmin';
$password = 'PCSadmin25';

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST') {
    try {
        // Get input
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($input['comment']) || empty(trim($input['comment']))) {
            http_response_code(400);
            echo json_encode(['error' => 'Comment is required']);
            exit;
        }
        
        $comment = trim($input['comment']);
        
        // Connect to database
        $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);
        
        // Simple insert - just comment (status will use default, then we update it)
        $stmt = $pdo->prepare("INSERT INTO comment (comment) VALUES (?)");
        $stmt->execute([$comment]);
        $commentId = $pdo->lastInsertId();
        
        // Update status to pending if column exists
        try {
            $pdo->prepare("UPDATE comment SET status = 'pending' WHERE comment_id = ?")->execute([$commentId]);
        } catch (PDOException $e) {
            // Status column might not exist or update failed, continue anyway
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Thank you for your comment! It will be reviewed by our admin.',
            'comment_id' => $commentId
        ]);
        
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode([
            'error' => 'Database error occurred',
            'message' => $e->getMessage(),
            'code' => $e->getCode()
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'error' => 'An error occurred',
            'message' => $e->getMessage()
        ]);
    }
} elseif ($method === 'GET') {
    try {
        $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);
        
        // Get approved comments
        try {
            $stmt = $pdo->query("SELECT comment_id, comment, created_at FROM comment WHERE status = 'approved' ORDER BY created_at DESC LIMIT 50");
            $comments = $stmt->fetchAll();
        } catch (PDOException $e) {
            // Fallback if status column doesn't exist
            $stmt = $pdo->query("SELECT comment_id, comment, created_at FROM comment ORDER BY comment_id DESC LIMIT 50");
            $comments = $stmt->fetchAll();
        }
        
        echo json_encode([
            'success' => true,
            'comments' => $comments,
            'count' => count($comments)
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to retrieve comments: ' . $e->getMessage()]);
    }
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}

