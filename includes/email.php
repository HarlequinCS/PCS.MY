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
 * @param array $attachments Array of attachment file paths (optional)
 * @return bool True on success, false on failure
 */
function sendSMTPEmail($to, $subject, $body, $toName = '', $attachments = []) {
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
        
        // Add attachments if provided
        if (!empty($attachments) && is_array($attachments)) {
            foreach ($attachments as $attachment) {
                if (is_array($attachment)) {
                    // If attachment is an array with 'path' and optional 'name'
                    $filePath = $attachment['path'] ?? $attachment;
                    $fileName = $attachment['name'] ?? basename($filePath);
                    
                    if (file_exists($filePath) && is_readable($filePath)) {
                        $mail->addAttachment($filePath, $fileName);
                    } else {
                        error_log("Attachment file not found or not readable: " . $filePath);
                    }
                } else {
                    // If attachment is just a file path string
                    if (file_exists($attachment) && is_readable($attachment)) {
                        $mail->addAttachment($attachment);
                    } else {
                        error_log("Attachment file not found or not readable: " . $attachment);
                    }
                }
            }
        }
        
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
 * Generate receipt PDF and save to file
 * 
 * @param string $refNo Order reference number
 * @param array $orderItems Array of all order items
 * @return string|false File path on success, false on failure
 */
function generateReceiptPDFFile($refNo, $orderItems) {
    // Load Dompdf if not already loaded
    if (!class_exists('Dompdf\Dompdf')) {
        if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
            require_once __DIR__ . '/../vendor/autoload.php';
        } else {
            error_log('Dompdf not found. Cannot generate receipt PDF.');
            return false;
        }
    }
    
    try {
        // Load required files
        if (!function_exists('formatDateTime')) {
            require_once __DIR__ . '/../config/timezone.php';
        }
        
        // Load database connection for timezone functions
        if (!function_exists('getDBConnection')) {
            require_once __DIR__ . '/db.php';
        }
        
        if (empty($orderItems)) {
            error_log('Cannot generate receipt: no order items provided');
            return false;
        }
        
        $firstItem = $orderItems[0];
        
        // Calculate totals
        $subtotal = 0;
        $totalDeliveryFee = 0;
        $totalDiscountAmount = 0;
        
        foreach ($orderItems as $item) {
            $itemAmount = floatval($item['amount'] ?? 0);
            $subtotal += $itemAmount;
            $totalDeliveryFee += floatval($item['delivery_fee'] ?? 0);
            $totalDiscountAmount += floatval($item['discount_amount'] ?? 0);
        }
        
        $totalAmount = $subtotal + $totalDeliveryFee;
        
        // Mappings
        $variantNames = [
            'hitamdepan' => 'Classic Black',
            'hijaufront' => 'Forest Green',
            'merahdepan' => 'Royal Red'
        ];
        
        $orderDate = formatDateTime(dbToAppTime($firstItem['created_at']), 'd/m/Y H:i:s');
        $logoUrl = 'https://projectcutisem.com/images/android-chrome-512x512.png';
        
        // PDF Setup
        $options = new \Dompdf\Options();
        $options->set('isRemoteEnabled', true);
        $options->set('defaultFont', 'Courier');
        $options->set('isHtml5ParserEnabled', true);
        $dompdf = new \Dompdf\Dompdf($options);
        
        // Generate HTML (same as receipt generator)
        $html = generateReceiptHTML($refNo, $orderItems, $firstItem, $subtotal, $totalDeliveryFee, $totalDiscountAmount, $totalAmount, $variantNames, $orderDate, $logoUrl);
        
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        
        // Create temporary directory if it doesn't exist
        $tempDir = __DIR__ . '/../uploads/receipts/';
        if (!file_exists($tempDir)) {
            mkdir($tempDir, 0755, true);
        }
        
        // Generate unique filename
        $filename = 'Receipt_' . $refNo . '_' . time() . '.pdf';
        $filePath = $tempDir . $filename;
        
        // Save PDF to file
        file_put_contents($filePath, $dompdf->output());
        
        return $filePath;
        
    } catch (Exception $e) {
        error_log("Receipt PDF generation error: " . $e->getMessage());
        return false;
    }
}

/**
 * Generate receipt HTML content
 * 
 * @param string $refNo Order reference
 * @param array $orderItems Order items
 * @param array $firstItem First order item
 * @param float $subtotal Subtotal
 * @param float $totalDeliveryFee Total delivery fee
 * @param float $totalDiscountAmount Total discount
 * @param float $totalAmount Total amount
 * @param array $variantNames Variant name mappings
 * @param string $orderDate Formatted order date
 * @param string $logoUrl Logo URL
 * @return string HTML content
 */
function generateReceiptHTML($refNo, $orderItems, $firstItem, $subtotal, $totalDeliveryFee, $totalDiscountAmount, $totalAmount, $variantNames, $orderDate, $logoUrl) {
    $html = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <style>
            @page { margin: 0; }
            body {
                font-family: "Courier New", Courier, monospace;
                background: #f4f4f4;
                margin: 0;
                padding: 30px;
                font-size: 11px;
                color: #000;
            }
            .container {
                background: #fff;
                border: 3px solid #000;
                padding: 0;
                width: 100%;
            }
            .text-upper { text-transform: uppercase; }
            .text-bold { font-weight: bold; }
            .text-right { text-align: right; }
            .text-center { text-align: center; }
            .text-red { color: #d00000; }
            .bg-black { background: #000; color: #fff; }
            table { width: 100%; border-collapse: collapse; }
            td { vertical-align: top; }
            .header-top td {
                padding: 20px;
                border-bottom: 3px solid #000;
            }
            .brand-name { font-size: 20px; font-weight: 900; letter-spacing: 2px; }
            .company-details { font-size: 9px; margin-top: 5px; color: #444; }
            .info-grid td {
                padding: 15px;
                border-bottom: 3px solid #000;
                width: 33.33%;
            }
            .info-grid td:not(:last-child) { border-right: 2px solid #000; }
            .label {
                font-size: 9px;
                color: #d00000;
                font-weight: bold;
                text-transform: uppercase;
                margin-bottom: 5px;
                letter-spacing: 0.5px;
            }
            .data { font-size: 11px; font-weight: bold; line-height: 1.4; }
            .items-header th {
                background: #000;
                color: #fff;
                padding: 8px 10px;
                text-align: left;
                font-size: 9px;
                text-transform: uppercase;
                font-weight: normal;
            }
            .item-row td {
                padding: 12px 10px;
                border-bottom: 1px solid #ccc;
                font-weight: bold;
            }
            .sku { font-size: 9px; color: #666; margin-top: 2px; display: block; }
            .totals-row td {
                padding: 5px 20px;
                text-align: right;
            }
            .grand-total {
                background: #000;
                color: #fff;
                font-size: 14px;
                padding: 10px 20px;
            }
            .footer {
                padding: 20px;
                text-align: center;
                border-top: 3px solid #000;
                margin-top: 0;
            }
            .terms {
                font-size: 8px;
                color: #666;
                text-align: justify;
                margin-top: 15px;
                border-top: 1px dotted #000;
                padding-top: 10px;
            }
            .barcode {
                font-size: 24px;
                letter-spacing: 5px;
                margin-bottom: 10px;
                font-weight: bold;
            }
        </style>
    </head>
    <body>
        <div class="container">
            <table class="header-top">
                <tr>
                    <td width="70">
                        <img src="' . $logoUrl . '" width="60" style="display:block;">
                    </td>
                    <td>
                        <div class="brand-name text-upper">Project Cuti Sem</div>
                        <div class="company-details">
                            OFFICIAL MERCHANDISE STORE<br>
                            MALAYSIA<br>
                            REG: PCS-2024-MY
                        </div>
                    </td>
                    <td class="text-right">
                        <div class="brand-name text-red">INVOICE</div>
                        <div class="text-bold">PAID</div>
                    </td>
                </tr>
            </table>

            <table class="info-grid">
                <tr>
                    <td>
                        <div class="label">Billed To</div>
                        <div class="data">
                            ' . htmlspecialchars($firstItem['fullname'] ?: $firstItem['name']) . '<br>
                            ' . htmlspecialchars($firstItem['phone']) . '<br>
                            ' . htmlspecialchars($firstItem['email']) . '
                        </div>
                    </td>
                    <td>
                        <div class="label">Shipping Address</div>
                        <div class="data">
                            ' . htmlspecialchars($firstItem['address1']) . '<br>
                            ' . htmlspecialchars($firstItem['district']) . ', ' . htmlspecialchars($firstItem['state']) . '
                        </div>
                    </td>
                    <td>
                        <div class="label">Order Details</div>
                        <div class="data">
                            Ref: #' . htmlspecialchars($refNo) . '<br>
                            Date: ' . explode(' ', $orderDate)[0] . '<br>
                            Time: ' . explode(' ', $orderDate)[1] . '<br>
                            Method: FPX/Online
                        </div>
                    </td>
                </tr>
            </table>

            <table style="width:100%; border-collapse:collapse;">
                <thead class="items-header">
                    <tr>
                        <th width="15%">SKU</th>
                        <th width="35%">Description / Variant</th>
                        <th width="10%" class="text-center">Size</th>
                        <th width="10%" class="text-center">Qty</th>
                        <th width="15%" class="text-right">Unit Price</th>
                        <th width="15%" class="text-right">Total</th>
                    </tr>
                </thead>
                <tbody>';
    
    foreach ($orderItems as $item) {
        $variantRaw = $item['variant'];
        $variantName = $variantNames[$variantRaw] ?? $variantRaw;
        $size = $item['size'] ?? 'OS';
        $sku = strtoupper(substr($variantRaw, 0, 3) . '-' . $size . '-01');
        
        $itemAmount = floatval($item['amount'] ?? 0);
        $qty = intval($item['quantity'] ?? 1);
        $unitPrice = $qty > 0 ? $itemAmount / $qty : 0;
        $lineTotal = $itemAmount;
        
        $html .= '
                <tr class="item-row">
                    <td><span class="sku">' . $sku . '</span></td>
                    <td>
                        <div class="text-upper">' . htmlspecialchars($variantName) . '</div>
                    </td>
                    <td class="text-center">' . htmlspecialchars($size) . '</td>
                    <td class="text-center">' . $qty . '</td>
                    <td class="text-right">RM ' . number_format($unitPrice, 2) . '</td>
                    <td class="text-right">RM ' . number_format($lineTotal, 2) . '</td>
                </tr>';
    }

    $html .= '
                </tbody>
            </table>

            <table style="margin-top: 0; border-top: 2px solid #000;">
                <tr>
                    <td width="60%" style="padding: 20px; vertical-align: middle;">
                         <div style="border: 1px solid #000; padding: 10px; display: inline-block;">
                            <div class="label" style="margin:0;">Payment Status</div>
                            <div class="text-upper text-bold" style="font-size: 14px;">Confirmed / Paid</div>
                         </div>
                    </td>
                    <td width="40%" style="padding:0;">
                        <table style="width:100%;">
                            <tr class="totals-row">
                                <td style="padding-top:15px;">Subtotal (Before Discount)</td>
                                <td style="padding-top:15px;">RM ' . number_format($subtotal + $totalDiscountAmount, 2) . '</td>
                            </tr>';
    
    if ($totalDiscountAmount > 0) {
        $html .= '<tr class="totals-row"><td class="text-red">Discount Applied</td><td class="text-red">-RM ' . number_format($totalDiscountAmount, 2) . '</td></tr>';
    }
    
    $html .= '<tr class="totals-row"><td>Subtotal (After Discount)</td><td>RM ' . number_format($subtotal, 2) . '</td></tr>';
    
    if ($totalDeliveryFee > 0) {
        $html .= '<tr class="totals-row"><td>Shipping</td><td>RM ' . number_format($totalDeliveryFee, 2) . '</td></tr>';
    }
    
    $html .= '
                            <tr>
                                <td colspan="2" class="grand-total">
                                    <div style="float:left;">TOTAL MYR</div>
                                    <div style="float:right;">' . number_format($totalAmount, 2) . '</div>
                                    <div style="clear:both;"></div>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>

            <div class="footer">
                <div class="barcode">||| ||| || |||||| || |||</div>
                <div class="text-bold" style="margin-bottom:5px;">THANK YOU FOR YOUR SUPPORT</div>
                <div>Questions? Email <span class="text-red">info@projectcutisem.com</span> or DM our IG</div>
                
                <div class="terms">
                    TERMS & CONDITIONS: This receipt is proof of purchase. Please retain for your records. 
                    Items purchased during promotional periods or "Clearance" are final sale and non-refundable. 
                    Exchanges are subject to stock availability and must be requested within 3 days of receipt.
                    <br><br>
                    SYSTEM GENERATED: ' . date('Y-m-d H:i:s') . ' | TERMINAL: WEB-01
                </div>
            </div>
        </div>
    </body>
    </html>';
    
    return $html;
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
    
    // Generate receipt PDF and attach to email
    $attachments = [];
    $receiptFilePath = generateReceiptPDFFile($orderReference, $orderItems);
    
    if ($receiptFilePath && file_exists($receiptFilePath)) {
        $attachments[] = [
            'path' => $receiptFilePath,
            'name' => 'Receipt_' . $orderReference . '.pdf'
        ];
    }
    
    // Send email with receipt attachment
    $result = sendSMTPEmail(
        $orderData['email'],
        $subject,
        $body,
        $orderData['fullname'] ?? $orderData['name'] ?? '',
        $attachments
    );
    
    // Clean up receipt file after sending
    if ($receiptFilePath && file_exists($receiptFilePath)) {
        @unlink($receiptFilePath);
    }
    
    return $result;
}

