<?php
/**
 * Generate PDF Receipt for Order
 * Only available for paid orders
 */

// Enable error reporting for debugging (remove in production)
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Increase memory limit for PDF generation
ini_set('memory_limit', '256M');
ini_set('max_execution_time', 60);

define('DB_CONFIG_LOADED', true);
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../config.php';

// Load DomPDF
$autoloadPath = __DIR__ . '/../../vendor/autoload.php';
if (!file_exists($autoloadPath)) {
    error_log("Autoload file not found at: " . $autoloadPath);
    http_response_code(500);
    die('PDF library not found. Please run: composer install');
}

require_once $autoloadPath;

use Dompdf\Dompdf;
use Dompdf\Options;

// Require admin login
if (!isAdminLoggedIn()) {
    http_response_code(401);
    die('Unauthorized');
}

// Load timezone helper
if (!function_exists('formatDateTime')) {
    require_once __DIR__ . '/../../config/timezone.php';
}

// Get order reference number
$refNo = $_GET['ref'] ?? '';

if (empty($refNo)) {
    http_response_code(400);
    die('Order reference number is required');
}

try {
    // Test database connection
    $pdo = getDBConnection();
    if (!$pdo) {
        throw new Exception('Database connection failed');
    }
    
    // Get all order items for this reference number
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE billExternalReferenceNo = ? ORDER BY id ASC");
    $stmt->execute([$refNo]);
    $orderItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($orderItems)) {
        http_response_code(404);
        die('Order not found');
    }
    
    // Check if order is paid
    $firstItem = $orderItems[0];
    if ($firstItem['status'] !== 'paid') {
        http_response_code(403);
        die('Receipt can only be generated for paid orders');
    }
    
    // Calculate totals
    $subtotal = 0;
    foreach ($orderItems as $item) {
        $amount = floatval($item['final_amount'] ?? $item['amount'] ?? 0);
        $subtotal += $amount;
    }
    
    $deliveryFee = floatval($firstItem['delivery_fee'] ?? 0);
    $discountAmount = floatval($firstItem['discount_amount'] ?? 0);
    $totalAmount = $subtotal + $deliveryFee - $discountAmount;
    
    // Variant names mapping
    $variantNames = [
        'hitamdepan' => 'Classic Black',
        'hijaufront' => 'Forest Green',
        'merahdepan' => 'Royal Red'
    ];
    
    // Configure DomPDF
    $options = new Options();
    $options->set('isRemoteEnabled', false);
    $options->set('isHtml5ParserEnabled', true);
    $options->set('defaultFont', 'DejaVu Sans');
    $options->set('isFontSubsettingEnabled', true);
    $options->set('isPhpEnabled', false);
    $options->set('chroot', __DIR__ . '/../../');
    
    $dompdf = new Dompdf($options);
    
    // Format date
    $orderDate = formatDateTime(dbToAppTime($firstItem['created_at']), 'F d, Y \a\t g:i A');
    
    // Generate HTML for receipt
    $html = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <style>
            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
            }
            body {
                font-family: DejaVu Sans, Arial, Helvetica, sans-serif;
                font-size: 12px;
                color: #333;
                line-height: 1.6;
                padding: 20px;
                background: #f5f5f5;
            }
            .receipt-container {
                max-width: 800px;
                margin: 0 auto;
                background: #ffffff;
                padding: 40px;
                box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            }
            .header {
                text-align: center;
                border-bottom: 3px solid #2563eb;
                padding-bottom: 20px;
                margin-bottom: 30px;
            }
            .header h1 {
                color: #2563eb;
                font-size: 28px;
                font-weight: 700;
                margin-bottom: 10px;
                letter-spacing: 1px;
            }
            .header p {
                color: #6b7280;
                font-size: 14px;
            }
            .receipt-info {
                width: 100%;
                margin-bottom: 30px;
            }
            .info-row {
                margin-bottom: 10px;
                overflow: hidden;
            }
            .info-label {
                float: left;
                font-weight: 600;
                color: #4b5563;
                padding: 8px 15px 8px 0;
                width: 140px;
            }
            .info-value {
                color: #111827;
                padding: 8px 0;
                overflow: hidden;
            }
            .section-title {
                font-size: 16px;
                font-weight: 700;
                color: #111827;
                margin: 25px 0 15px 0;
                padding-bottom: 8px;
                border-bottom: 2px solid #e5e7eb;
            }
            .customer-info {
                background: #f9fafb;
                padding: 20px;
                border-radius: 8px;
                margin-bottom: 25px;
            }
            .items-table {
                width: 100%;
                border-collapse: collapse;
                margin: 20px 0;
            }
            .items-table thead {
                background: #2563eb;
                color: #ffffff;
            }
            .items-table th {
                padding: 12px;
                text-align: left;
                font-weight: 600;
                font-size: 13px;
            }
            .items-table td {
                padding: 12px;
                border-bottom: 1px solid #e5e7eb;
            }
            .items-table tbody tr:hover {
                background: #f9fafb;
            }
            .text-right {
                text-align: right;
            }
            .text-center {
                text-align: center;
            }
            .totals-section {
                margin-top: 25px;
                padding-top: 20px;
                border-top: 2px solid #e5e7eb;
            }
            .total-row {
                width: 100%;
                margin: 8px 0;
                overflow: hidden;
            }
            .total-label {
                float: left;
                text-align: right;
                padding-right: 20px;
                color: #4b5563;
                font-weight: 500;
                width: calc(100% - 140px);
            }
            .total-value {
                float: right;
                text-align: right;
                color: #111827;
                font-weight: 600;
                width: 120px;
            }
            .grand-total {
                margin-top: 15px;
                padding-top: 15px;
                border-top: 2px solid #2563eb;
            }
            .grand-total .total-label {
                font-size: 16px;
                font-weight: 700;
                color: #111827;
            }
            .grand-total .total-value {
                font-size: 18px;
                font-weight: 700;
                color: #2563eb;
            }
            .footer {
                margin-top: 40px;
                padding-top: 20px;
                border-top: 1px solid #e5e7eb;
                text-align: center;
                color: #6b7280;
                font-size: 11px;
            }
            .status-badge {
                display: inline-block;
                padding: 6px 12px;
                background: #10b981;
                color: #ffffff;
                border-radius: 20px;
                font-weight: 600;
                font-size: 11px;
                text-transform: uppercase;
                letter-spacing: 0.5px;
            }
            .discount {
                color: #10b981;
            }
        </style>
    </head>
    <body>
        <div class="receipt-container">
            <div class="header">
                <h1>PROJEK CUTI SEM</h1>
                <p>Official Receipt</p>
            </div>
            
            <div class="receipt-info">
                <div class="info-row">
                    <div class="info-label">Receipt Number:</div>
                    <div class="info-value"><strong>' . htmlspecialchars($refNo) . '</strong></div>
                </div>
                <div class="info-row">
                    <div class="info-label">Order Date:</div>
                    <div class="info-value">' . htmlspecialchars($orderDate) . '</div>
                </div>
                <div class="info-row">
                    <div class="info-label">Payment Status:</div>
                    <div class="info-value"><span class="status-badge">PAID</span></div>
                </div>
            </div>
            
            <div class="section-title">Customer Information</div>
            <div class="customer-info">
                <div class="info-row">
                    <div class="info-label">Name:</div>
                    <div class="info-value"><strong>' . htmlspecialchars($firstItem['fullname'] ?: $firstItem['name']) . '</strong></div>
                </div>
                <div class="info-row">
                    <div class="info-label">Email:</div>
                    <div class="info-value">' . htmlspecialchars($firstItem['email']) . '</div>
                </div>
                <div class="info-row">
                    <div class="info-label">Phone:</div>
                    <div class="info-value">' . htmlspecialchars($firstItem['phone']) . '</div>
                </div>';
    
    if (!empty($firstItem['address1'])) {
        $html .= '
                <div class="info-row">
                    <div class="info-label">Address:</div>
                    <div class="info-value">' . htmlspecialchars($firstItem['address1']);
        if (!empty($firstItem['district']) || !empty($firstItem['state'])) {
            $html .= '<br>' . htmlspecialchars($firstItem['district'] ?? '') . ', ' . htmlspecialchars($firstItem['state'] ?? '');
        }
        $html .= '</div>
                </div>';
    }
    
    $html .= '
            </div>
            
            <div class="section-title">Order Items</div>
            <table class="items-table">
                <thead>
                    <tr>
                        <th>Item</th>
                        <th>Size</th>
                        <th class="text-center">Quantity</th>
                        <th class="text-right">Unit Price</th>
                        <th class="text-right">Total</th>
                    </tr>
                </thead>
                <tbody>';
    
    foreach ($orderItems as $item) {
        $variantName = $variantNames[$item['variant']] ?? $item['variant'];
        $itemAmount = floatval($item['final_amount'] ?? $item['amount'] ?? 0);
        $quantity = intval($item['quantity'] ?? 1);
        $unitPrice = $quantity > 0 ? $itemAmount / $quantity : 0;
        
        $html .= '
                    <tr>
                        <td><strong>' . htmlspecialchars($variantName) . '</strong></td>
                        <td>' . htmlspecialchars($item['size'] ?? 'N/A') . '</td>
                        <td class="text-center">' . $quantity . '</td>
                        <td class="text-right">RM ' . number_format($unitPrice, 2) . '</td>
                        <td class="text-right"><strong>RM ' . number_format($itemAmount, 2) . '</strong></td>
                    </tr>';
    }
    
    $html .= '
                </tbody>
            </table>
            
            <div class="totals-section">
                <div class="total-row">
                    <div class="total-label">Subtotal:</div>
                    <div class="total-value">RM ' . number_format($subtotal, 2) . '</div>
                </div>';
    
    if ($deliveryFee > 0) {
        $html .= '
                <div class="total-row">
                    <div class="total-label">Delivery Fee:</div>
                    <div class="total-value">RM ' . number_format($deliveryFee, 2) . '</div>
                </div>';
    }
    
    if ($discountAmount > 0) {
        $html .= '
                <div class="total-row">
                    <div class="total-label discount">Discount:</div>
                    <div class="total-value discount">-RM ' . number_format($discountAmount, 2) . '</div>
                </div>';
    }
    
    $html .= '
                <div class="total-row grand-total">
                    <div class="total-label">Total Amount:</div>
                    <div class="total-value">RM ' . number_format($totalAmount, 2) . '</div>
                </div>
            </div>
            
            <div class="footer">
                <p>Thank you for your purchase!</p>
                <p>This is an official receipt for your records.</p>
                <p style="margin-top: 15px;">Generated on ' . date('F d, Y \a\t g:i A') . '</p>
            </div>
        </div>
    </body>
    </html>';
    
    // Load HTML into DomPDF
    $dompdf->loadHtml($html);
    
    // Set paper size and orientation
    $dompdf->setPaper('A4', 'portrait');
    
    // Render PDF
    try {
        $dompdf->render();
    } catch (Exception $renderError) {
        error_log("PDF render error: " . $renderError->getMessage());
        throw new Exception("Failed to render PDF: " . $renderError->getMessage());
    }
    
    // Output PDF with proper headers
    $filename = 'Receipt_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $refNo) . '_' . date('Ymd') . '.pdf';
    
    // Clear any previous output
    if (ob_get_level()) {
        ob_end_clean();
    }
    
    // Set headers
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: private, max-age=0, must-revalidate');
    header('Pragma: public');
    
    // Output PDF
    $dompdf->stream($filename, ['Attachment' => 1]);
    
} catch (Exception $e) {
    error_log("Receipt generation error: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    http_response_code(500);
    
    // For debugging - show error details (remove in production)
    $errorMsg = 'Error generating receipt: ' . $e->getMessage();
    if (isset($_GET['debug']) && $_GET['debug'] === '1') {
        $errorMsg .= "\n\nStack trace:\n" . $e->getTraceAsString();
    }
    die($errorMsg);
} catch (Error $e) {
    error_log("Receipt generation fatal error: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    http_response_code(500);
    
    $errorMsg = 'Fatal error generating receipt: ' . $e->getMessage();
    if (isset($_GET['debug']) && $_GET['debug'] === '1') {
        $errorMsg .= "\n\nStack trace:\n" . $e->getTraceAsString();
    }
    die($errorMsg);
}
