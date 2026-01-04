<?php
/**
 * Email Template Preview
 * Preview the order confirmation email design in browser
 * 
 * SECURITY: Delete this file after preview or restrict access
 */

// Load variant mapping functions
require_once __DIR__ . '/includes/email_variant_mapping.php';

// Sample data for preview
$orderData = [
    'fullname' => 'Ahmad bin Abdullah',
    'name' => 'Ahmad bin Abdullah',
    'email' => 'customer@example.com',
    'phone' => '+60123456789',
    'address1' => '123 Jalan Contoh',
    'address2' => 'Taman Contoh',
    'address3' => '',
    'state' => 'Selangor',
    'district' => 'Petaling Jaya',
    'postcode' => '47300',
    'order_type' => 'delivery',
    'billExternalReferenceNo' => 'BILL-1704067200-abc123def456',
    'created_at' => date('Y-m-d H:i:s')
];

$orderItems = [
    [
        'variant' => 'hitamdepan',  // Internal code - will be mapped
        'size' => 'M',
        'quantity' => 2,
        'amount' => 100.00,
        'discount_amount' => 0.00,
        'delivery_fee' => 10.00,
        'final_amount' => 110.00
    ],
    [
        'variant' => 'hijaufront',  // Internal code - will be mapped
        'size' => 'L',
        'quantity' => 1,
        'amount' => 50.00,
        'discount_amount' => 5.00,
        'delivery_fee' => 5.00,
        'final_amount' => 50.00
    ]
];

// Map order items to use display names (same as in email.php)
$orderItems = mapOrderItemsVariants($orderItems);

// Calculate totals (same logic as in email.php)
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

// Prepare template variables (same as in email.php)
$customerName = $orderData['fullname'] ?? $orderData['name'] ?? 'Customer';
$orderReference = $orderData['billExternalReferenceNo'];
$orderDate = date('d M Y, h:i A', strtotime($orderData['created_at']));
$orderType = $orderData['order_type'] ?? 'delivery';

// Prepare delivery address
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

// Logo and support email
$logoUrl = 'https://projectcutisem.com/images/PCS%20logo.svg';
$supportEmail = 'info@projectcutisem.com';

// Define template constants (required by template)
if (!defined('EMAIL_TEMPLATE_LOADED')) {
    define('EMAIL_TEMPLATE_LOADED', true);
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Preview - Order Confirmation</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: #f0f0f0;
            padding: 20px;
            margin: 0;
        }
        .preview-wrapper {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .preview-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px 30px;
            border-bottom: 1px solid #ddd;
        }
        .preview-header h1 {
            margin: 0 0 10px 0;
            font-size: 24px;
        }
        .preview-header p {
            margin: 0;
            opacity: 0.9;
            font-size: 14px;
        }
        .preview-content {
            padding: 30px;
        }
        .preview-note {
            background: #fff3cd;
            border: 1px solid #ffc107;
            border-radius: 5px;
            padding: 15px;
            margin-bottom: 30px;
            color: #856404;
        }
        .preview-note strong {
            display: block;
            margin-bottom: 5px;
        }
        .email-container {
            border: 2px dashed #ddd;
            border-radius: 5px;
            overflow: hidden;
            background: #f5f5f5;
        }
    </style>
</head>
<body>
    <div class="preview-wrapper">
        <div class="preview-header">
            <h1>📧 Email Template Preview</h1>
            <p>Order Confirmation Email - Projek Cuti Sem</p>
        </div>
        <div class="preview-content">
            <div class="preview-note">
                <strong>Preview Notice:</strong>
                This is how the order confirmation email will look when sent to customers. 
                Variant names are automatically mapped from internal codes (hitamdepan, hijaufront, merahdepan) 
                to customer-facing names (Classic Black, Forest Green, Royal Red).
            </div>
            
            <div class="email-container">
                <?php require __DIR__ . '/includes/email_template_order_confirmation.php'; ?>
            </div>
        </div>
    </div>
</body>
</html>
