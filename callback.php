<?php
/**
 * ToyyibPay SANDBOX / Staging Payment Callback Handler
 * Receives server-to-server POST notifications from ToyyibPay
 * 
 * IMPORTANT: Callback ini adalah sumber kebenaran sebenar untuk payment confirmation
 * Jangan guna return.php untuk validate payment status
 */

// ============================================
// STEP 1: Set Timezone
// ============================================
date_default_timezone_set('Asia/Kuala_Lumpur');

// ============================================
// STEP 2: Capture POST Data from ToyyibPay
// ============================================
// ToyyibPay akan POST data ke sini selepas payment complete
$postData = $_POST;

// ============================================
// STEP 3: Log All Callback Data
// ============================================
// Prepare log entry with timestamp
$timestamp = date('Y-m-d H:i:s');
$logEntry = sprintf(
    "[%s] POST Data: %s\n",
    $timestamp,
    json_encode($postData, JSON_PRETTY_PRINT)
);

// Log to file (append mode dengan file lock)
$logFile = __DIR__ . '/toyyibpay_callback.log';
file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);

// ============================================
// STEP 4: Extract Payment Information
// ============================================
// Extract important data dari POST
$statusId = isset($postData['status_id']) ? (int)$postData['status_id'] : 0;
$billCode = isset($postData['billcode']) ? $postData['billcode'] : '';
$orderId = isset($postData['order_id']) ? $postData['order_id'] : '';
$transactionId = isset($postData['transaction_id']) ? $postData['transaction_id'] : '';
$amount = isset($postData['amount']) ? $postData['amount'] : '';

// ============================================
// STEP 5: Update Database
// ============================================
// Update payment status dalam database jika payment successful
// Status ID: 1 = Success, 2 = Pending, 3 = Failed
if ($statusId == 1) {
    // Payment successful - update database
    try {
        // Include database connection
        define('DB_CONFIG_LOADED', true);
        require_once __DIR__ . '/config/database.php';
        require_once __DIR__ . '/includes/db.php';
        
        $pdo = getDBConnection();
        
        // Update order status to paid
        $updateStmt = $pdo->prepare("
            UPDATE orders 
            SET status = 'paid' 
            WHERE billExternalReferenceNo = :ref_no
        ");
        $updateStmt->execute([':ref_no' => $orderId]);
        
        // Get all orders for this reference (may have multiple items)
        $ordersStmt = $pdo->prepare("
            SELECT variant, size, quantity 
            FROM orders 
            WHERE billExternalReferenceNo = :ref_no
        ");
        $ordersStmt->execute([':ref_no' => $orderId]);
        $orderItems = $ordersStmt->fetchAll();
        
        // Decrease inventory for each item (only if product is in_stock)
        $productStmt = $pdo->query("SELECT id, stock_status FROM products LIMIT 1");
        $product = $productStmt->fetch();
        
        if ($product && $product['stock_status'] === 'in_stock') {
            foreach ($orderItems as $item) {
                // Decrease inventory quantity
                $decreaseStmt = $pdo->prepare("
                    UPDATE product_inventory pi
                    JOIN product_variants pv ON pi.variant_id = pv.id
                    SET pi.quantity = GREATEST(0, pi.quantity - :qty)
                    WHERE pv.variant_key = :variant_key AND pi.size = :size
                ");
                $decreaseStmt->execute([
                    ':qty' => intval($item['quantity']),
                    ':variant_key' => $item['variant'],
                    ':size' => $item['size']
                ]);
                
                // Log inventory decrease
                error_log(sprintf(
                    "Inventory decreased: Variant %s, Size %s, Quantity -%d (Order: %s)",
                    $item['variant'],
                    $item['size'],
                    intval($item['quantity']),
                    $orderId
                ));
            }
        }
        
        // Log success
        error_log("Payment updated successfully: Order ID {$orderId}");
        
    } catch (PDOException $e) {
        // Log error but don't fail callback
        error_log("Database update failed: " . $e->getMessage());
    }
}

// ============================================
// STEP 6: Return HTTP 200 to ToyyibPay
// ============================================
// Sentiasa return HTTP 200 untuk acknowledge receipt
// Jangan output apa-apa content
http_response_code(200);
exit;
