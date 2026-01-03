<?php
/**
 * AJAX Handler: Update Stock Status
 * POST parameters: order_id (or variant), new_stock_status
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
$variant = trim($_POST['variant'] ?? '');
$newStockStatus = trim($_POST['new_stock_status'] ?? '');

// Validate stock status
$validStockStatuses = ['ready', 'out_of_stock', 'pre_order'];
if (!in_array($newStockStatus, $validStockStatuses)) {
    echo json_encode(['success' => false, 'error' => 'Invalid stock status']);
    exit;
}

try {
    $pdo = getDBConnection();
    
    if ($orderId > 0) {
        // Check if updated_at column exists
        $columns = $pdo->query("SHOW COLUMNS FROM orders")->fetchAll(PDO::FETCH_COLUMN);
        $hasUpdatedAt = in_array('updated_at', $columns);
        
        // Update specific order's stock status
        if ($hasUpdatedAt) {
            $stmt = $pdo->prepare("
                UPDATE orders 
                SET stock_status = :stock_status, 
                    updated_at = NOW()
                WHERE id = :id
            ");
        } else {
            $stmt = $pdo->prepare("
                UPDATE orders 
                SET stock_status = :stock_status
                WHERE id = :id
            ");
        }
        
        $stmt->execute([
            ':stock_status' => $newStockStatus,
            ':id' => $orderId
        ]);
        
        if ($stmt->rowCount() > 0) {
            error_log(sprintf(
                "Admin Action: User %s updated order #%d stock status to '%s'",
                $_SESSION['admin_username'] ?? 'unknown',
                $orderId,
                $newStockStatus
            ));
            
            echo json_encode([
                'success' => true,
                'message' => 'Stock status updated successfully',
                'order_id' => $orderId,
                'new_stock_status' => $newStockStatus
            ]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Order not found']);
        }
    } elseif (!empty($variant)) {
        // Check if updated_at column exists
        $columns = $pdo->query("SHOW COLUMNS FROM orders")->fetchAll(PDO::FETCH_COLUMN);
        $hasUpdatedAt = in_array('updated_at', $columns);
        
        // Update all orders with this variant (bulk update)
        if ($hasUpdatedAt) {
            $stmt = $pdo->prepare("
                UPDATE orders 
                SET stock_status = :stock_status, 
                    updated_at = NOW()
                WHERE variant = :variant
            ");
        } else {
            $stmt = $pdo->prepare("
                UPDATE orders 
                SET stock_status = :stock_status
                WHERE variant = :variant
            ");
        }
        
        $stmt->execute([
            ':stock_status' => $newStockStatus,
            ':variant' => $variant
        ]);
        
        $affectedRows = $stmt->rowCount();
        
        error_log(sprintf(
            "Admin Action: User %s updated stock status for variant '%s' to '%s' (%d orders affected)",
            $_SESSION['admin_username'] ?? 'unknown',
            $variant,
            $newStockStatus,
            $affectedRows
        ));
        
        echo json_encode([
            'success' => true,
            'message' => 'Stock status updated for all orders with this variant',
            'variant' => $variant,
            'new_stock_status' => $newStockStatus,
            'affected_rows' => $affectedRows
        ]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Order ID or variant required']);
    }
    
} catch (PDOException $e) {
    error_log('Stock status update error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Database error']);
}

