<?php
/**
 * AJAX Handler: Adjust Order Price
 * POST parameters: order_id, adjustment_amount, adjustment_type (add/subtract/override), reason
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
$adjustmentAmount = floatval($_POST['adjustment_amount'] ?? 0);
$adjustmentType = trim($_POST['adjustment_type'] ?? 'override'); // add, subtract, override
$reason = trim($_POST['reason'] ?? '');

if ($orderId <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid order ID']);
    exit;
}

if (!in_array($adjustmentType, ['add', 'subtract', 'override'])) {
    echo json_encode(['success' => false, 'error' => 'Invalid adjustment type']);
    exit;
}

if ($adjustmentAmount < 0) {
    echo json_encode(['success' => false, 'error' => 'Adjustment amount cannot be negative']);
    exit;
}

try {
    $pdo = getDBConnection();
    
    // Get current order data
    $stmt = $pdo->prepare("
        SELECT final_amount, amount, delivery_fee, discount_amount, billExternalReferenceNo
        FROM orders 
        WHERE id = :id
    ");
    $stmt->execute([':id' => $orderId]);
    $order = $stmt->fetch();
    
    if (!$order) {
        echo json_encode(['success' => false, 'error' => 'Order not found']);
        exit;
    }
    
    $currentFinalAmount = floatval($order['final_amount']);
    $currentAmount = floatval($order['amount']);
    $deliveryFee = floatval($order['delivery_fee']);
    
    // Calculate new final amount
    $newFinalAmount = $currentFinalAmount;
    
    if ($adjustmentType === 'add') {
        $newFinalAmount = $currentFinalAmount + $adjustmentAmount;
    } elseif ($adjustmentType === 'subtract') {
        $newFinalAmount = $currentFinalAmount - $adjustmentAmount;
    } else { // override
        $newFinalAmount = $adjustmentAmount;
    }
    
    // Validate: final amount cannot be negative
    if ($newFinalAmount < 0) {
        echo json_encode(['success' => false, 'error' => 'Final amount cannot be negative']);
        exit;
    }
    
    // Calculate proportional adjustment for amount (excluding delivery fee)
    $adjustedAmount = $newFinalAmount - $deliveryFee; // Remove delivery fee from final to get item amount
    
    // Check if updated_at column exists
    $columns = $pdo->query("SHOW COLUMNS FROM orders")->fetchAll(PDO::FETCH_COLUMN);
    $hasUpdatedAt = in_array('updated_at', $columns);
    
    // Update order
    if ($hasUpdatedAt) {
        $updateStmt = $pdo->prepare("
            UPDATE orders 
            SET final_amount = :final_amount,
                amount = :amount,
                updated_at = NOW()
            WHERE id = :id
        ");
    } else {
        $updateStmt = $pdo->prepare("
            UPDATE orders 
            SET final_amount = :final_amount,
                amount = :amount
            WHERE id = :id
        ");
    }
    
    $updateStmt->execute([
        ':final_amount' => $newFinalAmount,
        ':amount' => max(0, $adjustedAmount), // Ensure amount is not negative
        ':id' => $orderId
    ]);
    
    // Log price adjustment
    error_log(sprintf(
        "Admin Action: User %s adjusted order #%d (Ref: %s): %s RM %.2f, Reason: %s, Old: RM %.2f, New: RM %.2f",
        $_SESSION['admin_username'] ?? 'unknown',
        $orderId,
        $order['billExternalReferenceNo'],
        $adjustmentType,
        $adjustmentAmount,
        $reason ?: 'No reason provided',
        $currentFinalAmount,
        $newFinalAmount
    ));
    
    echo json_encode([
        'success' => true,
        'message' => 'Order price adjusted successfully',
        'order_id' => $orderId,
        'old_final_amount' => $currentFinalAmount,
        'new_final_amount' => $newFinalAmount,
        'adjustment' => $newFinalAmount - $currentFinalAmount
    ]);
    
} catch (PDOException $e) {
    error_log('Price adjustment error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Database error']);
}

