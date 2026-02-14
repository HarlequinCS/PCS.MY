<?php
/**
 * Generate PDF Receipt for Order
 * Streetwear Theme: Red / Black / White
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
    // IMPORTANT: Enable remote to load the logo URL
    $options->set('isRemoteEnabled', true); 
    $options->set('isHtml5ParserEnabled', true);
    $options->set('defaultFont', 'Courier'); // Monospace fits streetwear better
    $options->set('isFontSubsettingEnabled', true);
    $options->set('isPhpEnabled', false);
    $options->set('chroot', __DIR__ . '/../../');
    
    $dompdf = new Dompdf($options);
    
    // Format date
    $orderDate = formatDateTime(dbToAppTime($firstItem['created_at']), 'd M Y / H:i');
    
    // Logo URL
    $logoUrl = 'https://projectcutisem.com/images/android-chrome-512x512.png';

    // Generate HTML for receipt
    $html = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <style>
            @page { margin: 0px; }
            body {
                font-family: "Courier New", Courier, monospace;
                font-size: 12px;
                color: #000;
                line-height: 1.4;
                margin: 0;
                padding: 40px;
                background: #fff;
            }
            .container {
                border: 4px solid #000;
                padding: 0;
                position: relative;
                max-width: 100%;
            }
            /* HEADER STYLE */
            .header-top {
                background: #000;
                color: #fff;
                padding: 20px;
                overflow: hidden;
                border-bottom: 4px solid #000;
            }
            .logo-wrap {
                float: left;
                width: 60px;
                height: 60px;
                background: #fff;
                border: 2px solid #fff;
            }
            .logo-wrap img {
                width: 100%;
                height: auto;
                display: block;
            }
            .brand-title {
                float: left;
                margin-left: 20px;
                font-weight: 900;
                font-size: 24px;
                text-transform: uppercase;
                letter-spacing: 2px;
                line-height: 60px; /* Vertically center with logo */
            }
            .receipt-tag {
                float: right;
                background: #ff0000;
                color: #fff;
                padding: 5px 15px;
                font-weight: bold;
                text-transform: uppercase;
                font-size: 14px;
                margin-top: 15px;
                transform: rotate(-2deg);
            }

            /* INFO GRID */
            .info-grid {
                width: 100%;
                border-bottom: 4px solid #000;
                overflow: hidden;
            }
            .col-left {
                float: left;
                width: 50%;
                padding: 20px;
                border-right: 4px solid #000;
                box-sizing: border-box;
            }
            .col-right {
                float: left;
                width: 50%;
                padding: 20px;
                box-sizing: border-box;
            }
            .label {
                font-weight: bold;
                text-transform: uppercase;
                font-size: 10px;
                color: #ff0000;
                margin-bottom: 2px;
                letter-spacing: 1px;
            }
            .value {
                font-size: 14px;
                font-weight: bold;
                margin-bottom: 15px;
                word-wrap: break-word;
            }
            .value.ref {
                font-family: Helvetica, Arial, sans-serif;
                font-size: 16px;
                letter-spacing: 1px;
            }

            /* TABLE STYLE */
            table {
                width: 100%;
                border-collapse: collapse;
            }
            th {
                background: #000;
                color: #fff;
                text-transform: uppercase;
                padding: 12px;
                text-align: left;
                font-size: 11px;
                border-bottom: 4px solid #000;
            }
            td {
                padding: 15px 12px;
                border-bottom: 2px solid #000;
                font-weight: bold;
            }
            .text-right { text-align: right; }
            .text-center { text-align: center; }

            /* TOTALS */
            .totals-area {
                float: right;
                width: 50%;
                padding: 20px 0;
            }
            .total-row {
                overflow: hidden;
                margin-bottom: 5px;
                padding: 0 20px;
            }
            .total-label {
                float: left;
                text-transform: uppercase;
                font-weight: bold;
            }
            .total-val {
                float: right;
                font-weight: bold;
            }
            .grand-total {
                background: #ff0000;
                color: #fff;
                padding: 15px 20px;
                margin-top: 10px;
                border-top: 4px solid #000;
                border-bottom: 4px solid #000; /* Create a stripe effect */
                font-size: 18px;
            }

            /* FOOTER / SUPPORT */
            .footer {
                clear: both;
                padding: 30px 20px;
                text-align: center;
                background: #000;
                color: #fff;
                margin-top: 40px; 
            }
            .support-line {
                font-size: 14px;
                font-weight: bold;
                text-transform: uppercase;
                margin-bottom: 5px;
            }
            .support-contact {
                color: #ff0000;
                font-family: "Courier New", monospace;
            }
            .barcode-fake {
                margin-top: 15px;
                letter-spacing: 8px;
                font-size: 24px;
                opacity: 0.5;
            }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header-top">
                <div class="logo-wrap">
                    <img src="' . $logoUrl . '" alt="Logo">
                </div>
                <div class="brand-title">PROJECT CUTI SEM</div>
                <div class="receipt-tag">OFFICIAL RECEIPT</div>
            </div>

            <div class="info-grid">
                <div class="col-left">
                    <div class="label">Billed To</div>
                    <div class="value">' . htmlspecialchars($firstItem['fullname'] ?: $firstItem['name']) . '</div>
                    
                    <div class="label">Contact</div>
                    <div class="value">' . htmlspecialchars($firstItem['phone']) . '<br>' . htmlspecialchars($firstItem['email']) . '</div>

                    <div class="label">Shipment Address</div>
                    <div class="value">
                        ' . htmlspecialchars($firstItem['address1']) . '<br>
                        ' . ($firstItem['district'] ? htmlspecialchars($firstItem['district']) . ', ' : '') . htmlspecialchars($firstItem['state'] ?? '') . '
                    </div>
                </div>
                <div class="col-right">
                    <div class="label">Receipt No.</div>
                    <div class="value ref">#' . htmlspecialchars($refNo) . '</div>

                    <div class="label">Date / Time</div>
                    <div class="value">' . htmlspecialchars($orderDate) . '</div>

                    <div class="label">Status</div>
                    <div class="value" style="color:#ff0000;">PAID / CONFIRMED</div>
                </div>
            </div>

            <table>
                <thead>
                    <tr>
                        <th style="width: 40%;">Item / Variant</th>
                        <th class="text-center">Size</th>
                        <th class="text-center">Qty</th>
                        <th class="text-right">Price</th>
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
                        <td>
                            <span style="display:block; text-transform:uppercase;">' . htmlspecialchars($variantName) . '</span>
                        </td>
                        <td class="text-center">' . htmlspecialchars($item['size'] ?? '-') . '</td>
                        <td class="text-center">x' . $quantity . '</td>
                        <td class="text-right">' . number_format($unitPrice, 2) . '</td>
                        <td class="text-right">' . number_format($itemAmount, 2) . '</td>
                    </tr>';
    }
    
    $html .= '
                </tbody>
            </table>

            <div style="overflow: hidden;">
                <div class="totals-area">
                    <div class="total-row">
                        <div class="total-label">Subtotal</div>
                        <div class="total-val">RM ' . number_format($subtotal, 2) . '</div>
                    </div>';
    
    if ($deliveryFee > 0) {
        $html .= '
                    <div class="total-row">
                        <div class="total-label">Shipping</div>
                        <div class="total-val">RM ' . number_format($deliveryFee, 2) . '</div>
                    </div>';
    }
    
    if ($discountAmount > 0) {
        $html .= '
                    <div class="total-row">
                        <div class="total-label" style="color:#ff0000">Discount</div>
                        <div class="total-val" style="color:#ff0000">-RM ' . number_format($discountAmount, 2) . '</div>
                    </div>';
    }
    
    $html .= '
                    <div class="grand-total">
                        <div style="float:left;">TOTAL PAID</div>
                        <div style="float:right;">RM ' . number_format($totalAmount, 2) . '</div>
                        <div style="clear:both;"></div>
                    </div>
                </div>
            </div>

            <div class="footer">
                <div class="support-line">Need Help?</div>
                <div class="support-contact">
                    ' . htmlspecialchars('info@projectcutisem.com') . ' <span style="color:#666">|</span> DM our IG
                </div>
                <div class="barcode-fake">||| || ||| || |||||| |||</div>
                <div style="margin-top:10px; font-size:9px; color:#666;">
                    COMPUTER GENERATED RECEIPT. NO SIGNATURE REQUIRED.
                </div>
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
    $filename = 'Receipt_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $refNo) . '.pdf';
    
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
    http_response_code(500);
    die('Error generating receipt. Please try again later.');
} catch (Error $e) {
    error_log("Receipt generation fatal error: " . $e->getMessage());
    http_response_code(500);
    die('System error generating receipt.');
}