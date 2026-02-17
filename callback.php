<?php
/**
 * ToyyibPay SANDBOX / Staging Payment Callback Handler
 * Receives server-to-server POST notifications from ToyyibPay
 * 
 * IMPORTANT: Callback ini adalah sumber kebenaran sebenar untuk payment confirmation
 * Jangan guna return.php untuk validate payment status
 */

// ============================================
// STEP 1: Load Timezone Configuration
// ============================================
require_once __DIR__ . '/config/timezone.php';

/**
 * Add customer email to newsletter list
 * Automatically adds customer emails from purchases to newsletter with 'customer' source
 * 
 * @param PDO $pdo Database connection
 * @param string $email Customer email address
 * @return bool True on success, false on failure
 */
function addCustomerEmailToNewsletter($pdo, $email) {
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return false;
    }
    
    try {
        // Check if source column exists, if not add it
        $columns = $pdo->query("SHOW COLUMNS FROM email_notifications LIKE 'source'")->fetchAll();
        if (empty($columns)) {
            $pdo->exec("ALTER TABLE email_notifications ADD COLUMN source VARCHAR(20) DEFAULT 'subscriber' AFTER status");
            error_log("Added 'source' column to email_notifications table");
        }
        
        // Check if email already exists
        $checkStmt = $pdo->prepare("SELECT notification_id, source FROM email_notifications WHERE email = ?");
        $checkStmt->execute([$email]);
        $existing = $checkStmt->fetch();
        
        if ($existing) {
            // Email exists - update source to 'customer' if it's currently 'subscriber' or null
            // This way, if someone subscribed first then made a purchase, they become a customer
            // But if they're already a customer, don't update
            if (empty($existing['source']) || $existing['source'] === 'subscriber') {
                $updateStmt = $pdo->prepare("UPDATE email_notifications SET source = 'customer', updated_at = ? WHERE email = ?");
                $updateStmt->execute([getCurrentDateTimeUTC(), $email]);
                error_log("Updated email source to 'customer' for: {$email}");
            }
            return true;
        }
        
        // Insert new email with 'customer' source
        $stmt = $pdo->prepare("INSERT INTO email_notifications (email, ip_address, user_agent, status, source, created_at, updated_at) VALUES (?, NULL, NULL, 'active', 'customer', ?, ?)");
        $nowUTC = getCurrentDateTimeUTC();
        $stmt->execute([$email, $nowUTC, $nowUTC]);
        
        error_log("Added customer email to newsletter: {$email}");
        return true;
        
    } catch (PDOException $e) {
        // If duplicate entry, that's okay - email already exists
        if ($e->getCode() == 23000) {
            // Try to update source if it's a duplicate
            try {
                $updateStmt = $pdo->prepare("UPDATE email_notifications SET source = 'customer', updated_at = ? WHERE email = ?");
                $updateStmt->execute([getCurrentDateTimeUTC(), $email]);
                return true;
            } catch (Exception $updateError) {
                error_log("Failed to update duplicate email source: " . $updateError->getMessage());
            }
        }
        error_log("Failed to add customer email to newsletter: " . $e->getMessage());
        return false;
    }
}

// ============================================
// STEP 2: Capture POST Data from ToyyibPay
// ============================================
// ToyyibPay akan POST data ke sini selepas payment complete
$postData = $_POST;

// ============================================
// STEP 3: Log All Callback Data
// ============================================
// Prepare log entry with timestamp (in app timezone for readability)
$timestamp = getCurrentDateTime();
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
        // IMPORTANT: Only update status, NEVER modify amounts (final_amount, pushed_amount, etc.)
        // Amounts are set during checkout and must remain unchanged
        // TEMPORARY WORKAROUND: Gateway fee absorption is handled in checkout.php
        // This callback only confirms payment and updates status
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
        
        // ============================================
        // STEP 6: Send Order Confirmation Email (with duplicate protection)
        // ============================================
        // Check if email has already been sent to prevent duplicates
        $checkEmailStmt = $pdo->prepare("
            SELECT email_sent 
            FROM orders 
            WHERE billExternalReferenceNo = :ref_no 
            AND status = 'paid' 
            LIMIT 1
        ");
        $checkEmailStmt->execute([':ref_no' => $orderId]);
        $emailCheck = $checkEmailStmt->fetch();
        
        // Send email only if payment is successful AND email not yet sent
        if ($emailCheck && ($emailCheck['email_sent'] == 0 || $emailCheck['email_sent'] === false)) {
            try {
                // Include email functions
                require_once __DIR__ . '/includes/email.php';
                
                // Get order data (first order record contains customer info)
                $orderDataStmt = $pdo->prepare("
                    SELECT DISTINCT 
                        name, fullname, email, phone, address1, address2, address3,
                        state, district, postcode, order_type, billExternalReferenceNo, created_at
                    FROM orders 
                    WHERE billExternalReferenceNo = :ref_no
                    LIMIT 1
                ");
                $orderDataStmt->execute([':ref_no' => $orderId]);
                $orderData = $orderDataStmt->fetch();
                
                // Get all order items for this reference
                $itemsStmt = $pdo->prepare("
                    SELECT variant, size, quantity, amount, discount_amount, 
                           delivery_fee, final_amount
                    FROM orders 
                    WHERE billExternalReferenceNo = :ref_no
                ");
                $itemsStmt->execute([':ref_no' => $orderId]);
                $orderItems = $itemsStmt->fetchAll();
                
                // Send email if we have valid order data and email address
                if ($orderData && !empty($orderData['email']) && !empty($orderItems)) {
                    // Add customer email to newsletter list (if not already exists)
                    try {
                        addCustomerEmailToNewsletter($pdo, $orderData['email']);
                    } catch (Exception $e) {
                        error_log("Failed to add customer email to newsletter: " . $e->getMessage());
                        // Don't fail the callback if newsletter addition fails
                    }
                    
                    $emailSent = sendOrderConfirmationEmail($orderData, $orderItems);
                    
                    if ($emailSent) {
                        // Update email_sent flag to 1 for all orders with this reference
                        $updateEmailStmt = $pdo->prepare("
                            UPDATE orders 
                            SET email_sent = 1 
                            WHERE billExternalReferenceNo = :ref_no
                        ");
                        $updateEmailStmt->execute([':ref_no' => $orderId]);
                        
                        error_log("Order confirmation email sent successfully: Order ID {$orderId}, Email: {$orderData['email']}");
                    } else {
                        error_log("Failed to send order confirmation email: Order ID {$orderId}, Email: {$orderData['email']}");
                        // Don't update email_sent flag if email failed - allow retry
                    }
                } else {
                    error_log("Cannot send email: Invalid order data or missing email address for Order ID {$orderId}");
                }
                
            } catch (Exception $e) {
                // Log email error but don't fail the callback
                error_log("Email sending error for Order ID {$orderId}: " . $e->getMessage());
                // Don't update email_sent flag on error - allow retry
            }
        } else {
            // Email already sent, skip
            error_log("Email already sent for Order ID {$orderId}, skipping duplicate send");
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
