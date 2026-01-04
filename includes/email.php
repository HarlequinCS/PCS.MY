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
    
    // Load variant mapping functions
    require_once __DIR__ . '/email_variant_mapping.php';
    
    // Map order items to use display names
    $orderItems = mapOrderItemsVariants($orderItems);
    
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
    
    // Prepare template variables
    $customerName = $orderData['fullname'] ?? $orderData['name'] ?? 'Customer';
    $orderReference = $orderData['billExternalReferenceNo'];
    $orderDate = date('d M Y, h:i A', strtotime($orderData['created_at']));
    $orderType = $orderData['order_type'] ?? 'delivery';
    
    // Prepare delivery address if delivery order
    $deliveryAddress = null;
    if ($orderType === 'delivery' && !empty($orderData['address1'])) {
        $deliveryAddress = [
            'fullname' => $orderData['fullname'] ?? $orderData['name'] ?? '',
            'name' => $orderData['name'] ?? '',
            'address1' => $orderData['address1'] ?? '',
            'address2' => $orderData['address2'] ?? '',
            'address3' => $orderData['address3'] ?? '',
            'state' => $orderData['state'] ?? '',
            'district' => $orderData['district'] ?? '',
            'postcode' => $orderData['postcode'] ?? ''
        ];
    }
    
    // Logo and support email
    // IMPORTANT: Many email clients (especially Outlook) don't support SVG images
    // Use PNG version for better compatibility: 'https://projectcutisem.com/images/android-chrome-512x512.png'
    // Or convert SVG to PNG and upload to server
    $logoUrl = 'https://projectcutisem.com/images/android-chrome-512x512.png'; // Using PNG for better email compatibility
    $supportEmail = 'info@projectcutisem.com';
    
    // Load email template
    ob_start();
    require __DIR__ . '/email_template_order_confirmation.php';
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

