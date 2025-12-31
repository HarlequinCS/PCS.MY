<?php
/**
 * Enhanced Checkout Page with Full Address Form and Promotion Code
 * Displays cart items, collects complete user information, applies discounts, and processes payment
 */

// ============================================
// STEP 1: Start Session
// ============================================
session_start();

// Redirect to order page if cart is empty
if (empty($_SESSION['cart'])) {
    header('Location: order-baju.php');
    exit;
}

// ============================================
// STEP 2: Configuration & Constants
// ============================================
// Define database config constant untuk include db.php
if (!defined('DB_CONFIG_LOADED')) {
    define('DB_CONFIG_LOADED', true);
}

// Include database connection
require_once __DIR__ . '/includes/db.php';

// ToyyibPay Staging Credentials
define('STAGING_SECRET_KEY', 'dh1kldj2-j8db-ofm0-qauv-e037egz4oiui');
define('STAGING_CATEGORY_CODE', 'g3ii2ms2');

// Base URL untuk staging environment
$baseUrl = 'https://projectcutisem.com';

// Product Configuration
define('PRICE_PER_SHIRT', 50.00); // RM50 per baju

// Debug mode: Set to false in production
define('DEBUG_MODE', true);

// Variant names for display
$variantNames = [
    'hitamdepan' => 'Hitam Depan',
    'hijaufront' => 'Hijau Front',
    'hijaudepan' => 'Merah Depan'
];

// Malaysia States
$malaysiaStates = [
    'Johor',
    'Kedah',
    'Kelantan',
    'Kuala Lumpur',
    'Labuan',
    'Malacca',
    'Negeri Sembilan',
    'Pahang',
    'Penang',
    'Perak',
    'Perlis',
    'Putrajaya',
    'Sabah',
    'Sarawak',
    'Selangor',
    'Terengganu'
];

// ============================================
// STEP 3: Calculate Cart Totals
// ============================================
$cart = $_SESSION['cart'];
$cartCount = count($cart);
$cartSubtotal = 0;
foreach ($cart as $item) {
    $cartSubtotal += $item['subtotal'];
}

// Initialize discount variables
$promoCode = '';
$discountAmount = 0.00;
$finalAmount = $cartSubtotal;
$promoError = '';
$promoSuccess = '';

// ============================================
// STEP 4: Handle Promotion Code Validation (AJAX)
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'validate_promo') {
    $promoCodeInput = strtoupper(trim($_POST['promo_code'] ?? ''));
    
    if (empty($promoCodeInput)) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Promotion code is required']);
        exit;
    }
    
    try {
        $pdo = getDBConnection();
        
        // Check promotion code
        $stmt = $pdo->prepare("
            SELECT * FROM promotions 
            WHERE code = :code 
            AND active = 1
            AND (valid_from IS NULL OR valid_from <= NOW())
            AND (valid_until IS NULL OR valid_until >= NOW())
        ");
        
        $stmt->execute([':code' => $promoCodeInput]);
        $promotion = $stmt->fetch();
        
        if (!$promotion) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Invalid or expired promotion code']);
            exit;
        }
        
        // Check minimum purchase
        if ($promotion['min_purchase'] > 0 && $cartSubtotal < $promotion['min_purchase']) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false, 
                'error' => 'Minimum purchase of RM ' . number_format($promotion['min_purchase'], 2) . ' required'
            ]);
            exit;
        }
        
        // Check usage limit
        if ($promotion['usage_limit'] !== null && $promotion['used_count'] >= $promotion['usage_limit']) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Promotion code has reached usage limit']);
            exit;
        }
        
        // Calculate discount
        $discount = 0;
        if ($promotion['type'] === 'percentage') {
            $discount = ($cartSubtotal * $promotion['value']) / 100;
            // Apply max discount if set
            if ($promotion['max_discount'] !== null && $discount > $promotion['max_discount']) {
                $discount = $promotion['max_discount'];
            }
        } else { // fixed
            $discount = $promotion['value'];
            // Don't allow discount more than subtotal
            if ($discount > $cartSubtotal) {
                $discount = $cartSubtotal;
            }
        }
        
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'code' => $promotion['code'],
            'type' => $promotion['type'],
            'value' => $promotion['value'],
            'discount' => round($discount, 2),
            'subtotal' => $cartSubtotal,
            'final' => round($cartSubtotal - $discount, 2)
        ]);
        exit;
        
    } catch (PDOException $e) {
        error_log('Promotion validation error: ' . $e->getMessage());
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Error validating promotion code']);
        exit;
    }
}

// ============================================
// STEP 5: Handle Form Submission
// ============================================
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'checkout') {
    // Validate and collect all form fields
    $fullname = trim($_POST['fullname'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address1 = trim($_POST['address1'] ?? '');
    $address2 = trim($_POST['address2'] ?? '');
    $address3 = trim($_POST['address3'] ?? '');
    $state = trim($_POST['state'] ?? '');
    $district = trim($_POST['district'] ?? '');
    $postcode = trim($_POST['postcode'] ?? '');
    $promoCodeInput = strtoupper(trim($_POST['promo_code'] ?? ''));
    
    // Validation
    if (empty($fullname)) {
        $errors[] = 'Nama penuh diperlukan';
    }
    
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Email yang sah diperlukan';
    }
    
    if (empty($phone)) {
        $errors[] = 'Nombor telefon diperlukan';
    }
    
    if (empty($address1)) {
        $errors[] = 'Alamat Baris 1 diperlukan';
    }
    
    if (empty($state) || !in_array($state, $malaysiaStates)) {
        $errors[] = 'Negeri yang sah diperlukan';
    }
    
    if (empty($district)) {
        $errors[] = 'Daerah/Bandar diperlukan';
    }
    
    if (empty($postcode)) {
        $errors[] = 'Poskod diperlukan';
    } elseif (!preg_match('/^\d{5}$/', $postcode)) {
        $errors[] = 'Poskod mestilah 5 digit nombor';
    }
    
    // Validate promotion code if provided
    if (!empty($promoCodeInput)) {
        try {
            $pdo = getDBConnection();
            $stmt = $pdo->prepare("
                SELECT * FROM promotions 
                WHERE code = :code 
                AND active = 1
                AND (valid_from IS NULL OR valid_from <= NOW())
                AND (valid_until IS NULL OR valid_until >= NOW())
            ");
            
            $stmt->execute([':code' => $promoCodeInput]);
            $promotion = $stmt->fetch();
            
            if ($promotion) {
                // Check minimum purchase
                if ($promotion['min_purchase'] > 0 && $cartSubtotal < $promotion['min_purchase']) {
                    $errors[] = 'Kod promosi memerlukan pembelian minimum RM ' . number_format($promotion['min_purchase'], 2);
                } elseif ($promotion['usage_limit'] !== null && $promotion['used_count'] >= $promotion['usage_limit']) {
                    $errors[] = 'Kod promosi telah mencapai had penggunaan';
                } else {
                    // Calculate discount
                    if ($promotion['type'] === 'percentage') {
                        $discountAmount = ($cartSubtotal * $promotion['value']) / 100;
                        if ($promotion['max_discount'] !== null && $discountAmount > $promotion['max_discount']) {
                            $discountAmount = $promotion['max_discount'];
                        }
                    } else { // fixed
                        $discountAmount = $promotion['value'];
                        if ($discountAmount > $cartSubtotal) {
                            $discountAmount = $cartSubtotal;
                        }
                    }
                    $promoCode = $promoCodeInput;
                }
            } else {
                $errors[] = 'Kod promosi tidak sah atau telah tamat tempoh';
            }
        } catch (PDOException $e) {
            error_log('Promotion validation error: ' . $e->getMessage());
            $errors[] = 'Ralat semasa mengesahkan kod promosi';
        }
    }
    
    // Calculate final amount
    $finalAmount = $cartSubtotal - $discountAmount;
    if ($finalAmount < 0) {
        $finalAmount = 0;
    }
    
    // If no errors, process checkout
    if (empty($errors)) {
        try {
            // ============================================
            // STEP 6: Generate Unique Bill Reference
            // ============================================
            $billExternalReferenceNo = 'BILL-' . time() . '-' . uniqid();
            
            // ============================================
            // STEP 7: Save All Cart Items to Database
            // ============================================
            $pdo = getDBConnection();
            
            // Prepare insert statement with all new fields
            $stmt = $pdo->prepare("
                INSERT INTO orders (
                    name, fullname, email, phone, 
                    address1, address2, address3, state, district, postcode,
                    variant, size, quantity, 
                    amount, promo_code, discount_amount,
                    billExternalReferenceNo, status, created_at
                ) VALUES (
                    :name, :fullname, :email, :phone,
                    :address1, :address2, :address3, :state, :district, :postcode,
                    :variant, :size, :quantity,
                    :amount, :promo_code, :discount_amount,
                    :billExternalReferenceNo, 'pending', NOW()
                )
            ");
            
            // Insert each cart item as a separate order record
            foreach ($cart as $item) {
                // Calculate item amount (proportional discount if applicable)
                $itemAmount = $item['subtotal'];
                if ($discountAmount > 0) {
                    // Apply proportional discount to each item
                    $itemDiscount = ($item['subtotal'] / $cartSubtotal) * $discountAmount;
                    $itemAmount = $item['subtotal'] - $itemDiscount;
                }
                
                $stmt->execute([
                    ':name' => $fullname, // Keep for backward compatibility
                    ':fullname' => $fullname,
                    ':email' => $email,
                    ':phone' => $phone,
                    ':address1' => $address1,
                    ':address2' => $address2 ?: null,
                    ':address3' => $address3 ?: null,
                    ':state' => $state,
                    ':district' => $district,
                    ':postcode' => $postcode,
                    ':variant' => $item['variant'],
                    ':size' => $item['size'],
                    ':quantity' => $item['quantity'],
                    ':amount' => $itemAmount,
                    ':promo_code' => $promoCode ?: null,
                    ':discount_amount' => $discountAmount > 0 ? ($itemAmount - $item['subtotal']) : 0,
                    ':billExternalReferenceNo' => $billExternalReferenceNo
                ]);
            }
            
            // Update promotion usage count if code was used
            if (!empty($promoCode)) {
                $updateStmt = $pdo->prepare("
                    UPDATE promotions 
                    SET used_count = used_count + 1 
                    WHERE code = :code
                ");
                $updateStmt->execute([':code' => $promoCode]);
            }
            
            // ============================================
            // STEP 8: Prepare Bill Data for ToyyibPay
            // ============================================
            // Validate final amount
            if ($finalAmount <= 0) {
                throw new Exception('Invalid order amount. Final amount must be greater than 0.');
            }
            
            // Convert RM to sen (RM50.00 = 5000 sen)
            $billAmount = (int)($finalAmount * 100);
            
            // Validate bill amount
            if ($billAmount <= 0) {
                throw new Exception('Invalid bill amount. Amount must be greater than 0.');
            }
            
            // Build description with order details
            // IMPORTANT: ToyyibPay limit is 100 characters for billDescription
            $totalItems = 0;
            $totalQuantity = 0;
            foreach ($cart as $item) {
                $totalItems++;
                $totalQuantity += $item['quantity'];
            }
            
            // Create short description within 100 character limit
            if ($totalItems == 1) {
                // Single item: "Baju Premium - Hitam Depan, Size M, Qty 2"
                $item = $cart[0];
                $billDescription = sprintf(
                    "Baju Premium - %s, Size %s, Qty %d",
                    $item['variant_name'],
                    $item['size'],
                    $item['quantity']
                );
            } else {
                // Multiple items: "Baju Premium - 3 items, Total Qty 5"
                $billDescription = sprintf(
                    "Baju Premium - %d items, Total Qty %d",
                    $totalItems,
                    $totalQuantity
                );
            }
            
            // Add discount info if applicable (keep within 100 char limit)
            if ($discountAmount > 0) {
                $discountText = " | Disc: RM" . number_format($discountAmount, 0);
                if (strlen($billDescription . $discountText) <= 100) {
                    $billDescription .= $discountText;
                }
            }
            
            // Final check: Ensure description is within 100 character limit
            if (strlen($billDescription) > 100) {
                $billDescription = substr($billDescription, 0, 97) . '...';
            }
            
            // Prepare bill data (matching sandbox-test.php structure exactly)
            $billData = [
                // Required: ToyyibPay credentials
                'userSecretKey' => STAGING_SECRET_KEY,
                'categoryCode' => STAGING_CATEGORY_CODE,
                
                // Bill information
                'billName' => 'Baju Premium Order',
                'billDescription' => $billDescription,
                
                // Price setting: 1 = Fixed amount, 2 = Multiple options
                'billPriceSetting' => 1,
                
                // Collect payer info: 1 = Yes (name, email, phone), 0 = No
                'billPayorInfo' => 1,
                
                // Bill amount in sen (RM1.00 = 100 sen)
                'billAmount' => $billAmount,
                
                // Unique reference number untuk tracking
                'billExternalReferenceNo' => $billExternalReferenceNo,
                
                // Payer information (required even if billPayorInfo = 1)
                // User boleh override nilai ini di payment page
                'billTo' => $fullname,
                'billEmail' => $email,
                'billPhone' => $phone,
                
                // Return URL: User akan di-redirect ke sini selepas payment
                'billReturnUrl' => $baseUrl . '/return.php',
                
                // Callback URL: ToyyibPay akan POST data ke sini (server-to-server)
                'billCallbackUrl' => $baseUrl . '/callback.php',
                
                // Optional parameters
                'billSplitPayment' => 0,
                'billSplitPaymentArgs' => '',
                'billPaymentChannel' => 0,
                'billContentEmail' => '',
                'billChargeToCustomer' => 0
            ];
            
            // Log bill data for debugging
            error_log('ToyyibPay Bill Data: ' . json_encode($billData, JSON_PRETTY_PRINT));
            
            // ============================================
            // STEP 9: Call ToyyibPay API to Create Bill
            // ============================================
            // Initialize cURL untuk API call
            $ch = curl_init('https://dev.toyyibpay.com/index.php/api/createBill');
            
            // Set cURL options
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($billData));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/x-www-form-urlencoded'
            ]);
            
            // Execute API request
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);
            
            // ============================================
            // STEP 10: Handle API Response & Error Handling
            // ============================================
            // Check for cURL errors
            if ($curlError) {
                error_log('cURL Error: ' . $curlError);
                error_log('Full Response: ' . $response);
                throw new Exception('cURL request failed: ' . $curlError);
            }
            
            // Check HTTP status code
            if ($httpCode !== 200) {
                error_log('HTTP Error: Status ' . $httpCode . ', Response: ' . $response);
                throw new Exception('HTTP request failed with status code ' . $httpCode . '. Response: ' . htmlspecialchars($response));
            }
            
            // Decode JSON response safely
            $result = json_decode($response, true);
            
            // Check if JSON decode was successful
            if (json_last_error() !== JSON_ERROR_NONE) {
                error_log('JSON Decode Error: ' . json_last_error_msg() . ', Response: ' . $response);
                throw new Exception('Invalid JSON response: ' . json_last_error_msg() . '. Response: ' . htmlspecialchars($response));
            }
            
            // Log full response for debugging
            error_log('ToyyibPay API Response: ' . json_encode($result, JSON_PRETTY_PRINT));
            
            // Check if result is empty or not an array
            if (empty($result) || !is_array($result)) {
                error_log('Empty or invalid response structure. Response: ' . $response);
                throw new Exception('Invalid response structure from ToyyibPay API. Response: ' . htmlspecialchars($response));
            }
            
            // Check for API error response (check both 'status' and 'Status' keys)
            if (isset($result[0]['status']) && ($result[0]['status'] === 'error' || $result[0]['status'] === 'Error')) {
                $errorMsg = isset($result[0]['msg']) ? $result[0]['msg'] : (isset($result[0]['Message']) ? $result[0]['Message'] : 'Unknown error');
                error_log('ToyyibPay API Error: ' . $errorMsg . ', Full Response: ' . json_encode($result));
                throw new Exception('ToyyibPay API Error: ' . $errorMsg);
            }
            
            // Check if response contains BillCode (check both 'BillCode' and 'billcode' keys)
            if (!isset($result[0]['BillCode']) && !isset($result[0]['billcode'])) {
                error_log('BillCode Missing: Full Response: ' . json_encode($result, JSON_PRETTY_PRINT));
                error_log('Response structure: ' . print_r($result, true));
                error_log('Raw Response: ' . $response);
                
                // Show detailed error in debug mode
                $errorMsg = 'BillCode not returned in response.';
                if (DEBUG_MODE) {
                    $errorMsg .= ' Response: ' . htmlspecialchars($response);
                    $errorMsg .= ' | Decoded: ' . json_encode($result, JSON_PRETTY_PRINT);
                }
                throw new Exception($errorMsg);
            }
            
            // Get BillCode (try both cases)
            $billCode = isset($result[0]['BillCode']) ? $result[0]['BillCode'] : $result[0]['billcode'];
            
            // Validate BillCode is not empty
            if (empty($billCode)) {
                error_log('BillCode is empty: Full Response: ' . json_encode($result));
                error_log('Raw Response: ' . $response);
                
                $errorMsg = 'BillCode is empty in response.';
                if (DEBUG_MODE) {
                    $errorMsg .= ' Response: ' . htmlspecialchars($response);
                }
                throw new Exception($errorMsg);
            }
            
            // ============================================
            // STEP 11: Redirect User to Payment Page
            // ============================================
            // Log success
            error_log('Bill created successfully: BillCode = ' . $billCode);
            
            // Redirect user to ToyyibPay payment page
            header('Location: https://dev.toyyibpay.com/' . $billCode);
            exit;
            
        } catch (Exception $e) {
            // Log detailed error for debugging
            $errorMessage = $e->getMessage();
            error_log('Checkout processing error: ' . $errorMessage);
            error_log('Stack trace: ' . $e->getTraceAsString());
            
            // Show error message based on debug mode
            if (DEBUG_MODE) {
                // Show detailed error in development
                $errors[] = 'Ralat semasa memproses checkout: ' . htmlspecialchars($errorMessage);
            } else {
                // Show generic error in production
                $errors[] = 'Ralat semasa memproses checkout. Sila cuba lagi atau hubungi kami.';
            }
        }
    }
}

// Calculate display totals (for initial page load or after promo validation)
if (isset($_POST['promo_discount'])) {
    $discountAmount = floatval($_POST['promo_discount']);
    $promoCode = $_POST['promo_code_applied'] ?? '';
}
$finalAmount = $cartSubtotal - $discountAmount;
if ($finalAmount < 0) {
    $finalAmount = 0;
}
?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout - Baju Premium</title>
    <link rel="stylesheet" href="css/order-baju.css">
    <style>
        .checkout-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .checkout-grid {
            display: grid;
            grid-template-columns: 1fr 1.2fr;
            gap: 30px;
        }
        
        @media (max-width: 968px) {
            .checkout-grid {
                grid-template-columns: 1fr;
            }
        }
        
        .checkout-section {
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        
        .checkout-section h2 {
            margin-top: 0;
            margin-bottom: 20px;
            color: #1a202c;
            font-size: 24px;
            padding-bottom: 15px;
            border-bottom: 2px solid #e5e7eb;
        }
        
        .cart-summary-item {
            display: flex;
            align-items: center;
            padding: 15px;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .cart-summary-item:last-child {
            border-bottom: none;
        }
        
        .cart-summary-item-image {
            width: 50px;
            height: 50px;
            object-fit: contain;
            margin-right: 15px;
            border-radius: 4px;
        }
        
        .cart-summary-item-details {
            flex: 1;
        }
        
        .cart-summary-item-details h4 {
            margin: 0 0 5px 0;
            font-size: 14px;
            color: #1a202c;
        }
        
        .cart-summary-item-details p {
            margin: 0;
            font-size: 12px;
            color: #6b7280;
        }
        
        .cart-summary-item-price {
            font-weight: 600;
            color: #1a202c;
        }
        
        .order-total {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 2px solid #e5e7eb;
        }
        
        .order-total-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            font-size: 14px;
        }
        
        .order-total-row.discount {
            color: #10b981;
        }
        
        .order-total-row.final {
            font-size: 24px;
            font-weight: 700;
            color: #1a202c;
            margin-top: 10px;
            padding-top: 10px;
            border-top: 2px solid #e5e7eb;
        }
        
        .promo-section {
            margin-top: 20px;
            padding: 15px;
            background: #f9fafb;
            border-radius: 8px;
        }
        
        .promo-input-group {
            display: flex;
            gap: 10px;
        }
        
        .promo-input-group input {
            flex: 1;
            padding: 10px;
            border: 2px solid #e5e7eb;
            border-radius: 6px;
            font-size: 14px;
        }
        
        .promo-input-group button {
            padding: 10px 20px;
            background: #667eea;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
        }
        
        .promo-input-group button:hover {
            background: #5568d3;
        }
        
        .promo-message {
            margin-top: 10px;
            padding: 10px;
            border-radius: 6px;
            font-size: 13px;
        }
        
        .promo-message.success {
            background: #d1fae5;
            color: #065f46;
        }
        
        .promo-message.error {
            background: #fee2e2;
            color: #991b1b;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
        }
        
        .form-group.required label::after {
            content: ' *';
            color: #ef4444;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            font-size: 16px;
            transition: border-color 0.3s;
            font-family: inherit;
        }
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .form-group textarea {
            resize: vertical;
            min-height: 80px;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        
        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }
        }
        
        .errors {
            background: #fee2e2;
            border: 1px solid #fca5a5;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
            color: #991b1b;
        }
        
        .errors ul {
            list-style: none;
            padding-left: 0;
        }
        
        .errors li {
            margin-bottom: 5px;
        }
        
        .submit-btn {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 18px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
            margin-top: 20px;
        }
        
        .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
        }
        
        .submit-btn:disabled {
            background: #9ca3af;
            cursor: not-allowed;
            transform: none;
        }
        
        .back-link {
            display: inline-block;
            margin-bottom: 20px;
            color: #667eea;
            text-decoration: none;
            font-weight: 500;
        }
        
        .back-link:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="checkout-container">
        <a href="order-baju.php" class="back-link">← Kembali ke Order</a>
        
        <h1 style="text-align: center; margin-bottom: 20px; color: #1a202c;">Checkout</h1>
        
        <!-- Pre-Order Notice Banner -->
        <div style="background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%); border: 2px solid #fbbf24; border-radius: 12px; padding: 20px; margin-bottom: 30px; text-align: center;">
            <div style="display: inline-flex; align-items: center; gap: 12px; margin-bottom: 10px;">
                <div style="font-size: 32px;">📦</div>
                <h2 style="margin: 0; color: #92400e; font-size: 20px; font-weight: 700;">PRE-ORDER</h2>
            </div>
            <p style="margin: 0; color: #78350f; font-size: 15px; line-height: 1.6; max-width: 800px; margin: 0 auto;">
                <strong>Ini adalah pre-order.</strong> Kami akan menghantar kemaskini kemajuan pesanan anda melalui email pada setiap peringkat proses. 
                Sila pastikan alamat email anda betul dan aktif untuk menerima notifikasi.
            </p>
        </div>
        
        <?php if (!empty($errors)): ?>
            <div class="errors">
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="" id="checkoutForm">
            <input type="hidden" name="action" value="checkout">
            <input type="hidden" name="promo_code_applied" id="promoCodeApplied" value="<?php echo htmlspecialchars($promoCode); ?>">
            <input type="hidden" name="promo_discount" id="promoDiscount" value="<?php echo $discountAmount; ?>">
            
            <div class="checkout-grid">
                <!-- Order Summary -->
                <div class="checkout-section">
                    <h2>Order Summary</h2>
                    
                    <!-- Pre-Order Notice -->
                    <div style="background: #fef3c7; border: 2px solid #fbbf24; border-radius: 8px; padding: 15px; margin-bottom: 20px;">
                        <div style="display: flex; align-items: start; gap: 10px;">
                            <div style="font-size: 20px;">📦</div>
                            <div>
                                <strong style="color: #92400e; font-size: 14px;">Pre-Order</strong>
                                <p style="margin: 5px 0 0 0; color: #78350f; font-size: 12px; line-height: 1.5;">
                                    Kami akan menghantar kemaskini kemajuan pesanan melalui email pada setiap peringkat.
                                </p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="cart-items-summary">
                        <?php foreach ($cart as $item): ?>
                            <div class="cart-summary-item">
                                <img src="<?php echo htmlspecialchars($item['variant_image']); ?>" 
                                     alt="<?php echo htmlspecialchars($item['variant_name']); ?>"
                                     class="cart-summary-item-image">
                                <div class="cart-summary-item-details">
                                    <h4><?php echo htmlspecialchars($item['variant_name']); ?></h4>
                                    <p>Saiz: <?php echo htmlspecialchars($item['size']); ?> | Kuantiti: <?php echo $item['quantity']; ?></p>
                                </div>
                                <div class="cart-summary-item-price">
                                    RM <?php echo number_format($item['subtotal'], 2); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <!-- Promotion Code Section -->
                    <div class="promo-section">
                        <label style="margin-bottom: 10px; display: block; font-weight: 600;">Promotion Code</label>
                        <div class="promo-input-group">
                            <input type="text" 
                                   id="promoCodeInput" 
                                   placeholder="Enter code" 
                                   value="<?php echo htmlspecialchars($promoCode); ?>"
                                   style="text-transform: uppercase;">
                            <button type="button" id="applyPromoBtn">Apply</button>
                        </div>
                        <div id="promoMessage"></div>
                    </div>
                    
                    <div class="order-total">
                        <div class="order-total-row">
                            <span>Subtotal:</span>
                            <span>RM <?php echo number_format($cartSubtotal, 2); ?></span>
                        </div>
                        <?php if ($discountAmount > 0): ?>
                            <div class="order-total-row discount">
                                <span>Discount (<?php echo htmlspecialchars($promoCode); ?>):</span>
                                <span>- RM <?php echo number_format($discountAmount, 2); ?></span>
                            </div>
                        <?php endif; ?>
                        <div class="order-total-row final">
                            <span>Total:</span>
                            <span>RM <?php echo number_format($finalAmount, 2); ?></span>
                        </div>
                    </div>
                </div>
                
                <!-- Customer Information -->
                <div class="checkout-section">
                    <h2>Maklumat Pelanggan</h2>
                    
                    <div class="form-group required">
                        <label for="fullname">Nama Penuh</label>
                        <input type="text" name="fullname" id="fullname" 
                               value="<?php echo isset($_POST['fullname']) ? htmlspecialchars($_POST['fullname']) : ''; ?>" 
                               required>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group required">
                            <label for="email">Email</label>
                            <input type="email" name="email" id="email" 
                                   value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" 
                                   required>
                        </div>
                        
                        <div class="form-group required">
                            <label for="phone">Nombor Telefon</label>
                            <input type="tel" name="phone" id="phone" 
                                   value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>" 
                                   required>
                        </div>
                    </div>
                    
                    <h3 style="margin-top: 30px; margin-bottom: 15px; font-size: 18px; color: #1a202c;">Alamat Penghantaran</h3>
                    
                    <div class="form-group required">
                        <label for="address1">Alamat Baris 1</label>
                        <input type="text" name="address1" id="address1" 
                               value="<?php echo isset($_POST['address1']) ? htmlspecialchars($_POST['address1']) : ''; ?>" 
                               required>
                    </div>
                    
                    <div class="form-group">
                        <label for="address2">Alamat Baris 2 (Pilihan)</label>
                        <input type="text" name="address2" id="address2" 
                               value="<?php echo isset($_POST['address2']) ? htmlspecialchars($_POST['address2']) : ''; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="address3">Alamat Baris 3 (Pilihan)</label>
                        <input type="text" name="address3" id="address3" 
                               value="<?php echo isset($_POST['address3']) ? htmlspecialchars($_POST['address3']) : ''; ?>">
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group required">
                            <label for="state">Negeri</label>
                            <select name="state" id="state" required>
                                <option value="">-- Pilih Negeri --</option>
                                <?php foreach ($malaysiaStates as $stateOption): ?>
                                    <option value="<?php echo htmlspecialchars($stateOption); ?>"
                                            <?php echo (isset($_POST['state']) && $_POST['state'] === $stateOption) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($stateOption); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group required">
                            <label for="district">Daerah/Bandar</label>
                            <input type="text" name="district" id="district" 
                                   value="<?php echo isset($_POST['district']) ? htmlspecialchars($_POST['district']) : ''; ?>" 
                                   required>
                        </div>
                    </div>
                    
                    <div class="form-group required">
                        <label for="postcode">Poskod</label>
                        <input type="text" name="postcode" id="postcode" 
                               pattern="[0-9]{5}" 
                               maxlength="5"
                               value="<?php echo isset($_POST['postcode']) ? htmlspecialchars($_POST['postcode']) : ''; ?>" 
                               required>
                        <small style="color: #6b7280; font-size: 12px;">5 digit nombor sahaja</small>
                    </div>
                    
                    <!-- Pre-Order Notice -->
                    <div style="background: #fef3c7; border: 2px solid #fbbf24; border-radius: 8px; padding: 20px; margin-top: 20px; margin-bottom: 20px;">
                        <div style="display: flex; align-items: start; gap: 12px;">
                            <div style="font-size: 24px;">📦</div>
                            <div>
                                <h4 style="margin: 0 0 8px 0; color: #92400e; font-size: 16px; font-weight: 600;">Pre-Order Notice</h4>
                                <p style="margin: 0; color: #78350f; font-size: 14px; line-height: 1.6;">
                                    Ini adalah <strong>pre-order</strong>. Kami akan menghantar kemaskini kemajuan pesanan anda melalui email pada setiap peringkat proses. 
                                    Sila pastikan alamat email anda betul dan aktif.
                                </p>
                            </div>
                        </div>
                    </div>
                    
                    <button type="submit" class="submit-btn">Proceed to Payment</button>
                </div>
            </div>
        </form>
    </div>
    
    <script>
        // ============================================
        // Promotion Code Validation (AJAX)
        // ============================================
        document.getElementById('applyPromoBtn').addEventListener('click', function() {
            const promoCode = document.getElementById('promoCodeInput').value.trim().toUpperCase();
            const promoMessage = document.getElementById('promoMessage');
            
            if (!promoCode) {
                promoMessage.innerHTML = '<div class="promo-message error">Sila masukkan kod promosi</div>';
                return;
            }
            
            // Disable button during request
            this.disabled = true;
            this.textContent = 'Validating...';
            
            const formData = new FormData();
            formData.append('action', 'validate_promo');
            formData.append('promo_code', promoCode);
            
            fetch('checkout.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update hidden fields
                    document.getElementById('promoCodeApplied').value = data.code;
                    document.getElementById('promoDiscount').value = data.discount;
                    
                    // Update display
                    updateOrderTotal(data.subtotal, data.discount, data.final);
                    
                    // Show success message
                    promoMessage.innerHTML = '<div class="promo-message success">Kod promosi berjaya digunakan! Diskaun: RM ' + data.discount.toFixed(2) + '</div>';
                } else {
                    // Show error message
                    promoMessage.innerHTML = '<div class="promo-message error">' + data.error + '</div>';
                    
                    // Clear discount
                    document.getElementById('promoCodeApplied').value = '';
                    document.getElementById('promoDiscount').value = '0';
                    updateOrderTotal(data.subtotal || <?php echo $cartSubtotal; ?>, 0, data.subtotal || <?php echo $cartSubtotal; ?>);
                }
                
                // Re-enable button
                this.disabled = false;
                this.textContent = 'Apply';
            })
            .catch(error => {
                console.error('Error:', error);
                promoMessage.innerHTML = '<div class="promo-message error">Ralat semasa mengesahkan kod promosi</div>';
                this.disabled = false;
                this.textContent = 'Apply';
            });
        });
        
        // Update order total display
        function updateOrderTotal(subtotal, discount, final) {
            const subtotalRow = document.querySelector('.order-total-row:first-child span:last-child');
            const finalRow = document.querySelector('.order-total-row.final span:last-child');
            
            if (subtotalRow) {
                subtotalRow.textContent = 'RM ' + subtotal.toFixed(2);
            }
            
            // Update or create discount row
            let discountRow = document.querySelector('.order-total-row.discount');
            if (discount > 0) {
                if (!discountRow) {
                    const orderTotal = document.querySelector('.order-total');
                    const finalRowElement = document.querySelector('.order-total-row.final');
                    discountRow = document.createElement('div');
                    discountRow.className = 'order-total-row discount';
                    orderTotal.insertBefore(discountRow, finalRowElement);
                }
                discountRow.innerHTML = '<span>Discount:</span><span>- RM ' + discount.toFixed(2) + '</span>';
                discountRow.style.display = 'flex';
            } else if (discountRow) {
                discountRow.style.display = 'none';
            }
            
            if (finalRow) {
                finalRow.textContent = 'RM ' + final.toFixed(2);
            }
        }
        
        // Allow Enter key to apply promo code
        document.getElementById('promoCodeInput').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                document.getElementById('applyPromoBtn').click();
            }
        });
        
        // Form validation
        document.getElementById('checkoutForm').addEventListener('submit', function(e) {
            const fullname = document.getElementById('fullname').value.trim();
            const email = document.getElementById('email').value.trim();
            const phone = document.getElementById('phone').value.trim();
            const address1 = document.getElementById('address1').value.trim();
            const state = document.getElementById('state').value;
            const district = document.getElementById('district').value.trim();
            const postcode = document.getElementById('postcode').value.trim();
            
            if (!fullname || !email || !phone || !address1 || !state || !district || !postcode) {
                e.preventDefault();
                alert('Sila isi semua medan yang diperlukan.');
                return false;
            }
            
            // Email validation
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(email)) {
                e.preventDefault();
                alert('Sila masukkan email yang sah.');
                return false;
            }
            
            // Postcode validation
            if (!/^\d{5}$/.test(postcode)) {
                e.preventDefault();
                alert('Poskod mestilah 5 digit nombor.');
                return false;
            }
            
            // Show loading state
            const submitBtn = document.querySelector('.submit-btn');
            submitBtn.disabled = true;
            submitBtn.textContent = 'Memproses...';
        });
    </script>
</body>
</html>
