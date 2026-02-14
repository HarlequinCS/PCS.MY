<?php
/**
 * Generate PDF Receipt for Order
 * Theme: Streetwear Detail (Red/Black/White)
 * Features: SKU generation, Terms, Payment Details
 */

// 1. SETUP & CONFIGURATION
error_reporting(E_ALL);
ini_set('display_errors', 0); 
ini_set('log_errors', 1);
ini_set('memory_limit', '256M');
ini_set('max_execution_time', 60);

define('DB_CONFIG_LOADED', true);

// Adjust paths as needed
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

// 2. SECURITY
if (!isAdminLoggedIn()) {
    http_response_code(401);
    die('Unauthorized');
}

if (!function_exists('formatDateTime')) {
    require_once __DIR__ . '/../../config/timezone.php';
}

$refNo = $_GET['ref'] ?? '';
if (empty($refNo)) {
    http_response_code(400);
    die('Ref required');
}

try {
    // 3. DATA RETRIEVAL
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE billExternalReferenceNo = ? ORDER BY id ASC");
    $stmt->execute([$refNo]);
    $orderItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($orderItems)) die('Order not found');
    
    $firstItem = $orderItems[0];
    if ($firstItem['status'] !== 'paid') die('Receipt available for paid orders only');
    
    // 4. CALCULATIONS
    $subtotal = 0;
    foreach ($orderItems as $item) {
        $amount = floatval($item['final_amount'] ?? $item['amount'] ?? 0);
        $subtotal += $amount;
    }
    
    $deliveryFee = floatval($firstItem['delivery_fee'] ?? 0);
    $discountAmount = floatval($firstItem['discount_amount'] ?? 0);
    $totalAmount = $subtotal + $deliveryFee - $discountAmount;
    
    // Mappings
    $variantNames = [
        'hitamdepan' => 'Classic Black',
        'hijaufront' => 'Forest Green',
        'merahdepan' => 'Royal Red'
    ];
    
    $orderDate = formatDateTime(dbToAppTime($firstItem['created_at']), 'd/m/Y H:i:s');
    $logoUrl = 'https://projectcutisem.com/images/android-chrome-512x512.png';

    // 5. PDF SETUP
    $options = new Options();
    $options->set('isRemoteEnabled', true);
    $options->set('defaultFont', 'Courier');
    $options->set('isHtml5ParserEnabled', true);
    $dompdf = new Dompdf($options);
    
    // 6. HTML GENERATION
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
            
            /* UTILS */
            .text-upper { text-transform: uppercase; }
            .text-bold { font-weight: bold; }
            .text-right { text-align: right; }
            .text-center { text-align: center; }
            .text-red { color: #d00000; }
            .bg-black { background: #000; color: #fff; }
            
            /* TABLES */
            table { width: 100%; border-collapse: collapse; }
            td { vertical-align: top; }
            
            /* HEADER */
            .header-top td {
                padding: 20px;
                border-bottom: 3px solid #000;
            }
            .brand-name { font-size: 20px; font-weight: 900; letter-spacing: 2px; }
            .company-details { font-size: 9px; margin-top: 5px; color: #444; }
            
            /* INFO GRID */
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
            
            /* ITEMS TABLE */
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

            /* TOTALS */
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

            /* FOOTER */
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
        // Generate a fake SKU for detail: e.g. HIT-XL-001
        $sku = strtoupper(substr($variantRaw, 0, 3) . '-' . $size . '-01');
        
        $amount = floatval($item['final_amount'] ?? $item['amount'] ?? 0);
        $qty = intval($item['quantity'] ?? 1);
        $unit = $qty > 0 ? $amount / $qty : 0;
        
        $html .= '
                <tr class="item-row">
                    <td><span class="sku">' . $sku . '</span></td>
                    <td>
                        <div class="text-upper">' . htmlspecialchars($variantName) . '</div>
                    </td>
                    <td class="text-center">' . htmlspecialchars($size) . '</td>
                    <td class="text-center">' . $qty . '</td>
                    <td class="text-right">' . number_format($unit, 2) . '</td>
                    <td class="text-right">' . number_format($amount, 2) . '</td>
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
                                <td style="padding-top:15px;">Subtotal</td>
                                <td style="padding-top:15px;">RM ' . number_format($subtotal, 2) . '</td>
                            </tr>';
    
    if ($deliveryFee > 0) {
        $html .= '<tr class="totals-row"><td>Shipping</td><td>RM ' . number_format($deliveryFee, 2) . '</td></tr>';
    }
    if ($discountAmount > 0) {
        $html .= '<tr class="totals-row"><td class="text-red">Discount</td><td class="text-red">-RM ' . number_format($discountAmount, 2) . '</td></tr>';
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

    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();
    
    // Clean buffer and output
    if (ob_get_level()) ob_end_clean();
    
    $dompdf->stream('Receipt_' . $refNo . '.pdf', ['Attachment' => 1]);

} catch (Exception $e) {
    error_log($e->getMessage());
    die('Error generating receipt.');
}