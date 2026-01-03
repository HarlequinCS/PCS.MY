<?php
/**
 * AJAX Handler: Update Product Stock Status
 * POST parameters: product_id, new_status
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

$productId = intval($_POST['product_id'] ?? 0);
$newStatus = trim($_POST['new_status'] ?? '');

// Validate status
$validStatuses = ['in_stock', 'out_of_stock', 'pre_order'];
if (!in_array($newStatus, $validStatuses)) {
    echo json_encode(['success' => false, 'error' => 'Invalid status']);
    exit;
}

if ($productId <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid product ID']);
    exit;
}

try {
    $pdo = getDBConnection();

    // Get current status for logging
    $currentStmt = $pdo->prepare("SELECT stock_status, name FROM products WHERE id = :id");
    $currentStmt->execute([':id' => $productId]);
    $currentProduct = $currentStmt->fetch();

    if (!$currentProduct) {
        echo json_encode(['success' => false, 'error' => 'Product not found']);
        exit;
    }

    $oldStatus = $currentProduct['stock_status'];

    // Check if 'updated_at' column exists
    $columns = $pdo->query("SHOW COLUMNS FROM products LIKE 'updated_at'")->fetch();
    $updateSql = "UPDATE products SET stock_status = :status";
    $params = [':status' => $newStatus, ':id' => $productId];

    if ($columns) {
        $updateSql .= ", updated_at = NOW()";
    }
    $updateSql .= " WHERE id = :id";

    // Update product status
    $stmt = $pdo->prepare($updateSql);
    $stmt->execute($params);

    if ($stmt->rowCount() > 0) {
        // Log the change
        error_log(sprintf(
            "Admin Action: User %s updated product #%d (%s) status from '%s' to '%s'",
            $_SESSION['admin_username'] ?? 'unknown',
            $productId,
            $currentProduct['name'],
            $oldStatus,
            $newStatus
        ));

        echo json_encode([
            'success' => true,
            'message' => 'Product status updated successfully',
            'product_id' => $productId,
            'old_status' => $oldStatus,
            'new_status' => $newStatus
        ]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Product not found or no changes made']);
    }

} catch (PDOException $e) {
    error_log('Product status update error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Database error']);
}

