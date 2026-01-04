<?php
/**
 * Email Helper Functions
 * Functions for sending order confirmation emails via Hostinger SMTP
 * Uses PHPMailer library for SMTP email sending
 */

// Prevent direct access
if (!defined('DB_CONFIG_LOADED') && !defined('SMTP_CONFIG_LOADED')) {
    http_response_code(403);
    die('Direct access not allowed');
}

// Load PHPMailer
// Option 1: Using Composer (recommended)
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}
// Option 2: Manual PHPMailer installation
elseif (file_exists(__DIR__ . '/../vendor/phpmailer/phpmailer/src/PHPMailer.php')) {
    require_once __DIR__ . '/../vendor/phpmailer/phpmailer/src/PHPMailer.php';
    require_once __DIR__ . '/../vendor/phpmailer/phpmailer/src/SMTP.php';
    require_once __DIR__ . '/../vendor/phpmailer/phpmailer/src/Exception.php';
}
// Fallback: Use native PHP mail() if PHPMailer not available (not recommended for SMTP)
else {
    error_log('PHPMailer not found. Please install via Composer: composer require phpmailer/phpmailer');
    // Will use fallback method if PHPMailer class doesn't exist
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/**
 * Get SMTP configuration
 * @return array SMTP configuration array
 */
function getSMTPConfig() {
    static $config = null;
    if ($config === null) {
        $configFile = __DIR__ . '/../config/smtp.php';
        if (file_exists($configFile)) {
            $config = require $configFile;
        } else {
            error_log('SMTP configuration file not found: ' . $configFile);
            throw new Exception('SMTP configuration not found');
        }
    }
    return $config;
}

/**
 * Send email using SMTP via PHPMailer
 * 
 * @param string $to Recipient email address
 * @param string $subject Email subject
 * @param string $body Email body (HTML)
 * @param string $toName Recipient name (optional)
 * @return bool True on success, false on failure
 */
function sendSMTPEmail($to, $subject, $body, $toName = '') {
    // Check if PHPMailer is available
    if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
        error_log('PHPMailer class not found. Cannot send email.');
        return false;
    }
    
    try {
        $config = getSMTPConfig();
        
        $mail = new PHPMailer(true);
        
        // Server settings
        $mail->isSMTP();
        $mail->Host = $config['host'];
        $mail->SMTPAuth = true;
        $mail->Username = $config['username'];
        $mail->Password = $config['password'];
        $mail->SMTPSecure = $config['encryption']; // 'ssl' for port 465
        $mail->Port = $config['port'];
        $mail->CharSet = 'UTF-8';
        
        // Optional: Enable debug output (disable in production)
        // $mail->SMTPDebug = 2; // 0 = off, 2 = verbose
        
        // Recipients
        $mail->setFrom($config['from_email'], $config['from_name']);
        $mail->addAddress($to, $toName);
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $body;
        $mail->AltBody = strip_tags($body); // Plain text version
        
        // Send email
        $mail->send();
        return true;
        
    } catch (Exception $e) {
        error_log("Email sending failed: " . $mail->ErrorInfo);
        error_log("Exception: " . $e->getMessage());
        return false;
    }
}

/**
 * Send order confirmation email to customer
 * 
 * @param array $orderData Order data (first order record with customer info)
 * @param array $orderItems Array of all order items for this order reference
 * @return bool True on success, false on failure
 */
function sendOrderConfirmationEmail($orderData, $orderItems) {
    if (empty($orderData['email'])) {
        error_log('Cannot send email: customer email is empty');
        return false;
    }
    
    // Calculate totals
    $subtotal = 0;
    $totalDiscount = 0;
    $totalDeliveryFee = 0;
    $finalTotal = 0;
    
    foreach ($orderItems as $item) {
        $itemSubtotal = ($item['amount'] ?? 0) + ($item['discount_amount'] ?? 0);
        $subtotal += $itemSubtotal;
        $totalDiscount += ($item['discount_amount'] ?? 0);
        $totalDeliveryFee += ($item['delivery_fee'] ?? 0);
        $finalTotal += ($item['final_amount'] ?? 0);
    }
    
    // Build email subject
    $subject = "Order Confirmation - #{$orderData['billExternalReferenceNo']} - Projek Cuti Sem";
    
    // Build email body (HTML)
    ob_start();
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; background-color: #f4f4f4; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background-color: #1a1a1a; color: white; padding: 30px 20px; text-align: center; }
            .header h1 { margin: 0; font-size: 24px; }
            .content { background-color: #ffffff; padding: 30px 20px; }
            .order-details { background-color: #f9f9f9; padding: 20px; margin: 20px 0; border-left: 4px solid #1a1a1a; }
            .order-details h2 { margin-top: 0; color: #1a1a1a; }
            .order-details h3 { color: #1a1a1a; border-bottom: 1px solid #ddd; padding-bottom: 10px; }
            .item-row { padding: 10px 0; border-bottom: 1px solid #eee; }
            .item-row:last-child { border-bottom: none; }
            .total-row { font-weight: bold; padding: 15px 0; border-top: 2px solid #333; margin-top: 10px; }
            .total-row p { margin: 5px 0; }
            .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; background-color: #ffffff; }
            .info-row { margin: 10px 0; }
            .info-label { font-weight: bold; display: inline-block; width: 150px; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1>Order Confirmation</h1>
                <p style="margin: 10px 0 0 0;">Thank you for your order!</p>
            </div>
            <div class="content">
                <p>Dear <?php echo htmlspecialchars($orderData['fullname'] ?? $orderData['name']); ?>,</p>
                
                <p>Your order has been confirmed and payment has been received. We're preparing your items for <?php echo ($orderData['order_type'] ?? 'delivery') === 'pickup' ? 'pickup' : 'delivery'; ?>.</p>
                
                <div class="order-details">
                    <h2>Order Details</h2>
                    <div class="info-row">
                        <span class="info-label">Order Reference:</span>
                        <span><?php echo htmlspecialchars($orderData['billExternalReferenceNo']); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Order Date:</span>
                        <span><?php echo date('d M Y, h:i A', strtotime($orderData['created_at'])); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Order Type:</span>
                        <span><?php echo ucfirst($orderData['order_type'] ?? 'delivery'); ?></span>
                    </div>
                    
                    <h3>Items Ordered</h3>
                    <?php foreach ($orderItems as $item): ?>
                    <div class="item-row">
                        <strong><?php echo htmlspecialchars($item['variant'] ?? 'N/A'); ?></strong> - 
                        Size: <?php echo htmlspecialchars($item['size'] ?? 'N/A'); ?> - 
                        Quantity: <?php echo ($item['quantity'] ?? 0); ?> - 
                        RM <?php echo number_format($item['final_amount'] ?? 0, 2); ?>
                    </div>
                    <?php endforeach; ?>
                    
                    <div class="total-row">
                        <p>Subtotal: RM <?php echo number_format($subtotal, 2); ?></p>
                        <?php if ($totalDiscount > 0): ?>
                        <p>Discount: -RM <?php echo number_format($totalDiscount, 2); ?></p>
                        <?php endif; ?>
                        <?php if (($orderData['order_type'] ?? 'delivery') === 'delivery' && $totalDeliveryFee > 0): ?>
                        <p>Delivery Fee: RM <?php echo number_format($totalDeliveryFee, 2); ?></p>
                        <?php endif; ?>
                        <p style="font-size: 18px; margin-top: 10px;">Total: RM <?php echo number_format($finalTotal, 2); ?></p>
                    </div>
                </div>
                
                <?php if (($orderData['order_type'] ?? 'delivery') === 'delivery' && !empty($orderData['address1'])): ?>
                <div class="order-details">
                    <h3>Delivery Address</h3>
                    <p>
                        <?php echo htmlspecialchars($orderData['fullname'] ?? $orderData['name']); ?><br>
                        <?php echo htmlspecialchars($orderData['address1']); ?><br>
                        <?php if (!empty($orderData['address2'])): ?>
                            <?php echo htmlspecialchars($orderData['address2']); ?><br>
                        <?php endif; ?>
                        <?php if (!empty($orderData['address3'])): ?>
                            <?php echo htmlspecialchars($orderData['address3']); ?><br>
                        <?php endif; ?>
                        <?php echo htmlspecialchars($orderData['postcode'] ?? ''); ?> <?php echo htmlspecialchars($orderData['district'] ?? ''); ?><br>
                        <?php echo htmlspecialchars($orderData['state'] ?? ''); ?><br>
                        Malaysia
                    </p>
                </div>
                <?php endif; ?>
                
                <p>We will notify you once your order is ready for <?php echo ($orderData['order_type'] ?? 'delivery') === 'pickup' ? 'pickup' : 'shipment'; ?>.</p>
                
                <p>If you have any questions, please contact us.</p>
                
                <p>Thank you for choosing Projek Cuti Sem!</p>
            </div>
            <div class="footer">
                <p>&copy; <?php echo date('Y'); ?> Projek Cuti Sem. All rights reserved.</p>
            </div>
        </div>
    </body>
    </html>
    <?php
    $body = ob_get_clean();
    
    // Send email
    $result = sendSMTPEmail(
        $orderData['email'],
        $subject,
        $body,
        $orderData['fullname'] ?? $orderData['name'] ?? ''
    );
    
    return $result;
}

