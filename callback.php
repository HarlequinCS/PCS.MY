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
// STEP 5: Update Database (Optional)
// ============================================
// Update payment status dalam database jika payment successful
// Status ID: 1 = Success, 2 = Pending, 3 = Failed
if ($statusId == 1) {
    // Payment successful - update database
    // CONTOH PSEUDO CODE (gantikan dengan database logic sebenar):
    /*
    try {
        // Connect to database
        $pdo = new PDO('mysql:host=localhost;dbname=your_db', 'username', 'password');
        
        // Update payment status
        $stmt = $pdo->prepare("
            UPDATE payments 
            SET 
                status = 'paid',
                transaction_id = :transaction_id,
                bill_code = :bill_code,
                updated_at = NOW()
            WHERE 
                external_reference_no = :order_id
        ");
        
        $stmt->execute([
            ':transaction_id' => $transactionId,
            ':bill_code' => $billCode,
            ':order_id' => $orderId
        ]);
        
        // Log success
        error_log("Payment updated successfully: Order ID {$orderId}");
        
    } catch (PDOException $e) {
        // Log error but don't fail callback
        error_log("Database update failed: " . $e->getMessage());
    }
    */
}

// ============================================
// STEP 6: Return HTTP 200 to ToyyibPay
// ============================================
// Sentiasa return HTTP 200 untuk acknowledge receipt
// Jangan output apa-apa content
http_response_code(200);
exit;

