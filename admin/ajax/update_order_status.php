<?php
/**
 * AJAX Handler: Update Order Status
 * POST parameters: order_id, new_status
 */

require_once __DIR__ . '/../config.php';
requireAdminLogin();

define('DB_CONFIG_LOADED', true);
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$orderId = intval($_POST['order_id'] ?? 0);
$newStatus = trim($_POST['new_status'] ?? '');

// Validate status
$validStatuses = ['pending', 'packing', 'ready', 'shipped', 'paid', 'failed', 'cancelled'];
if (!in_array($newStatus, $validStatuses)) {
    echo json_encode(['success' => false, 'error' => 'Invalid status']);
    exit;
}

if ($orderId <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid order ID']);
    exit;
}

try {
    $pdo = getDBConnection();
    
    // Get current status for logging
    $currentStmt = $pdo->prepare("SELECT status FROM orders WHERE id = :id");
    $currentStmt->execute([':id' => $orderId]);
    $currentOrder = $currentStmt->fetch();
    
    if (!$currentOrder) {
        echo json_encode(['success' => false, 'error' => 'Order not found']);
        exit;
    }
    
    // Check if updated_at column exists
    $columns = $pdo->query("SHOW COLUMNS FROM orders")->fetchAll(PDO::FETCH_COLUMN);
    $hasUpdatedAt = in_array('updated_at', $columns);
    
    // Update order status
    if ($hasUpdatedAt) {
        $stmt = $pdo->prepare("
            UPDATE orders 
            SET status = :status, 
                updated_at = NOW()
            WHERE id = :id
        ");
    } else {
        $stmt = $pdo->prepare("
            UPDATE orders 
            SET status = :status
            WHERE id = :id
        ");
    }
    
    $stmt->execute([
        ':status' => $newStatus,
        ':id' => $orderId
    ]);
    
    if ($stmt->rowCount() > 0) {
        // Log the change
        error_log(sprintf(
            "Admin Action: User %s updated order #%d status from '%s' to '%s'",
            $_SESSION['admin_username'] ?? 'unknown',
            $orderId,
            $currentOrder['status'],
            $newStatus
        ));
        
        echo json_encode([
            'success' => true,
            'message' => 'Order status updated successfully',
            'order_id' => $orderId,
            'old_status' => $currentOrder['status'],
            'new_status' => $newStatus
        ]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Order not found or no changes made']);
    }
    
} catch (PDOException $e) {
    error_log('Order status update error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Database error']);
}

