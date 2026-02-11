<?php
/**
 * AJAX Handler: Delete Order
 * POST parameters: order_ref (billExternalReferenceNo)
 * Deletes all orders with the same billExternalReferenceNo
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

$orderRef = trim($_POST['order_ref'] ?? '');

if (empty($orderRef)) {
    echo json_encode(['success' => false, 'error' => 'Invalid order reference']);
    exit;
}

try {
    $pdo = getDBConnection();
    
    // First, get order details for logging
    $checkStmt = $pdo->prepare("SELECT id, billExternalReferenceNo, fullname, email, status, final_amount FROM orders WHERE billExternalReferenceNo = :ref LIMIT 1");
    $checkStmt->execute([':ref' => $orderRef]);
    $orderInfo = $checkStmt->fetch();
    
    if (!$orderInfo) {
        echo json_encode(['success' => false, 'error' => 'Order not found']);
        exit;
    }
    
    // Count how many order records will be deleted
    $countStmt = $pdo->prepare("SELECT COUNT(*) as count FROM orders WHERE billExternalReferenceNo = :ref");
    $countStmt->execute([':ref' => $orderRef]);
    $count = $countStmt->fetch()['count'];
    
    // Delete all orders with the same billExternalReferenceNo
    $stmt = $pdo->prepare("DELETE FROM orders WHERE billExternalReferenceNo = :ref");
    $stmt->execute([':ref' => $orderRef]);
    
    if ($stmt->rowCount() > 0) {
        // Log the deletion
        error_log(sprintf(
            "Admin Action: User %s deleted order reference %s (%d record(s) deleted) - Customer: %s (%s), Status: %s",
            $_SESSION['admin_username'] ?? 'unknown',
            $orderRef,
            $count,
            $orderInfo['fullname'] ?? 'N/A',
            $orderInfo['email'] ?? 'N/A',
            $orderInfo['status'] ?? 'N/A'
        ));
        
        echo json_encode([
            'success' => true,
            'message' => 'Order deleted successfully',
            'order_ref' => $orderRef,
            'records_deleted' => $count
        ]);
    } else {
        echo json_encode(['success' => false, 'error' => 'No orders were deleted']);
    }
    
} catch (PDOException $e) {
    error_log('Order delete error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Database error']);
}
