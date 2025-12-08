<?php
/**
 * Approve/Reject Comment API
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
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST') {
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($input['comment_id']) || !isset($input['action'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing required parameters']);
            exit;
        }
        
        $commentId = (int)$input['comment_id'];
        $action = $input['action']; // 'approve' or 'reject'
        
        if (!in_array($action, ['approve', 'reject'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
            exit;
        }
        
        $pdo = getDBConnection();
        
        if ($action === 'approve') {
            $stmt = $pdo->prepare("UPDATE comment SET status = 'approved' WHERE comment_id = ?");
            $stmt->execute([$commentId]);
            $message = 'Comment approved successfully';
        } else {
            $stmt = $pdo->prepare("UPDATE comment SET status = 'rejected' WHERE comment_id = ?");
            $stmt->execute([$commentId]);
            $message = 'Comment rejected';
        }
        
        if ($stmt->rowCount() > 0) {
            echo json_encode([
                'success' => true,
                'message' => $message
            ]);
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'Comment not found']);
        }
    } catch (Exception $e) {
        error_log("Approve comment error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'Failed to update comment']);
    }
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}

