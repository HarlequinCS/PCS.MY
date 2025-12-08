<?php
/**
 * Get Comments API for Admin
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
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

try {
    $pdo = getDBConnection();
    $filter = $_GET['filter'] ?? 'all';
    
    // Build query based on filter
    $query = "SELECT comment_id, comment, ip_address, created_at, status FROM comment";
    $params = [];
    
    if ($filter === 'pending') {
        $query .= " WHERE status = 'pending'";
    } elseif ($filter === 'approved') {
        $query .= " WHERE status = 'approved'";
    }
    
    $query .= " ORDER BY created_at DESC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $comments = $stmt->fetchAll();
    
    // Get counts
    $pendingCount = $pdo->query("SELECT COUNT(*) as count FROM comment WHERE status = 'pending'")->fetch()['count'];
    $approvedCount = $pdo->query("SELECT COUNT(*) as count FROM comment WHERE status = 'approved'")->fetch()['count'];
    $totalCount = $pdo->query("SELECT COUNT(*) as count FROM comment")->fetch()['count'];
    
    echo json_encode([
        'success' => true,
        'comments' => $comments,
        'counts' => [
            'pending' => (int)$pendingCount,
            'approved' => (int)$approvedCount,
            'total' => (int)$totalCount
        ]
    ]);
} catch (Exception $e) {
    error_log("Get comments error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Failed to retrieve comments']);
}

