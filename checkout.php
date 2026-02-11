<?php
/**
 * Enhanced Checkout Page with Full Address Form and Promotion Code
 * Displays cart items, collects complete user information, applies discounts, and processes payment
 */

// Load timezone configuration (sets default timezone and provides helper functions)
require_once __DIR__ . '/config/timezone.php';

// CSP Header Override - form-action removed to prevent blocking
// Alternative security measures implemented below (Origin check, Referer validation)
if (!headers_sent()) {
    header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.jsdelivr.net https://fonts.googleapis.com https://static.cloudflareinsights.com https://cdn.cloudflare.com; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; img-src 'self' data: https:; connect-src 'self' https://projectcutisem.com https://static.cloudflareinsights.com; frame-ancestors 'self'; base-uri 'self';", true);
}

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

// Include promotion code system
require_once __DIR__ . '/includes/promotion.php';

// Include CSRF protection
require_once __DIR__ . '/includes/csrf.php';

// Include version configuration for asset versioning
require_once __DIR__ . '/config/version.php';

// ToyyibPay Staging Credentials (kept for backward compatibility)
define('STAGING_SECRET_KEY', 'dh1kldj2-j8db-ofm0-qauv-e037egz4oiui');
define('STAGING_CATEGORY_CODE', 'g3ii2ms2');

// Load ToyyibPay config helper (supports staging/production toggle)
// Note: DB_CONFIG_LOADED and database config are already loaded above
require_once __DIR__ . '/includes/toyyibpay_config.php';

// Base URL untuk staging environment
$baseUrl = 'https://projectcutisem.com';

// Product Configuration
define('PRODUCT_NAME', 'PCS Half-Zip Workshirt');
define('PRICE_PER_SHIRT', 50.00); // RM50 per baju

// Delivery Configuration
define('DELIVERY_FEE', 8.00); // RM8 delivery fee for delivery option

// Debug mode: Set to false in production
define('DEBUG_MODE', false); // Production mode enabled

// Variant names for display
$variantNames = [
    'hitamdepan' => 'Classic Black',
    'hijaufront' => 'Forest Green',
    'merahdepan' => 'Royal Red'
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
// Size Addon Prices (for sizes 3XL and above)
function getSizeAddonPrice($size) {
    $addonPrices = [
        '3XL' => 3.00,
        '4XL' => 3.00,
        '5XL' => 5.00,
        '6XL' => 5.00
    ];
    return isset($addonPrices[$size]) ? $addonPrices[$size] : 0.00;
}

$cart = $_SESSION['cart'];
$cartCount = count($cart);
$cartSubtotal = 0;
$totalBasePrice = 0;
$totalSizeAddons = 0;

foreach ($cart as $item) {
    $cartSubtotal += $item['subtotal'];
    
    // Calculate base price and size addons for detailed breakdown
    $itemSize = $item['size'] ?? '';
    $itemQuantity = $item['quantity'] ?? 1;
    $itemSizeAddon = isset($item['size_addon']) ? $item['size_addon'] : getSizeAddonPrice($itemSize);
    $itemBasePrice = PRICE_PER_SHIRT * $itemQuantity;
    $itemSizeAddonTotal = $itemSizeAddon * $itemQuantity;
    
    $totalBasePrice += $itemBasePrice;
    $totalSizeAddons += $itemSizeAddonTotal;
}

// Get order type from session or default to 'delivery'
$orderType = $_SESSION['order_type'] ?? 'delivery';

// Calculate delivery fee (server-side, don't trust frontend)
$deliveryFee = 0.00;
if ($orderType === 'delivery') {
    $deliveryFee = DELIVERY_FEE;
}

// Initialize discount variables
$promoCode = '';
$discountAmount = 0.00;
$finalAmount = $cartSubtotal + $deliveryFee; // Include delivery fee in base calculation
$promoError = '';
$promoSuccess = '';

// Note: $discountAmount and $promoCode will be updated when form is submitted
// or when promo code is validated via AJAX

// ============================================
// STEP 4: Handle Promotion Code Validation (AJAX)
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'validate_promo') {
    header('Content-Type: application/json');
    
    $promoCodeInput = $_POST['promo_code'] ?? '';
    
    if (empty(trim($promoCodeInput))) {
        echo json_encode(['success' => false, 'error' => 'Promotion code is required']);
        exit;
    }
    
    try {
        $pdo = getDBConnection();
        
        // Validate promotion code using centralized function (follows exact validation order)
        $validation = validatePromotionCode($promoCodeInput, $cartSubtotal, $pdo);
        
        if (!$validation['success']) {
            echo json_encode([
                'success' => false,
                'error' => $validation['error']
            ]);
            exit;
        }
        
        $promotion = $validation['promotion'];
        
        // Calculate discount using centralized function
        $discount = calculatePromotionDiscount($promotion, $cartSubtotal);
        
        // Store validated promotion in session
        storePromotionInSession($promotion['code'], $discount);
        
        // Return success response
        echo json_encode([
            'success' => true,
            'code' => $promotion['code'],
            'type' => $promotion['type'],
            'value' => floatval($promotion['value']),
            'discount' => $discount,
            'subtotal' => round($cartSubtotal, 2),
            'final' => round($cartSubtotal - $discount, 2)
        ]);
        exit;
        
    } catch (PDOException $e) {
        error_log('Promotion validation error: ' . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Error validating promotion code']);
        exit;
    }
}

// ============================================
// STEP 5: Handle Form Submission
// ============================================
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'checkout') {
    // ============================================
    // ALTERNATIVE SECURITY MEASURES (Replaces form-action CSP)
    // ============================================
    
    // 1. Origin Check - Verify request comes from same origin
    $allowedOrigins = ['https://projectcutisem.com', 'http://projectcutisem.com', 'https://www.projectcutisem.com', 'http://www.projectcutisem.com'];
    $origin = $_SERVER['HTTP_ORIGIN'] ?? $_SERVER['HTTP_REFERER'] ?? '';
    $originHost = parse_url($origin, PHP_URL_HOST) ?? '';
    $currentHost = $_SERVER['HTTP_HOST'] ?? 'projectcutisem.com';
    
    // Normalize hosts (remove www if present for comparison)
    $originHostNormalized = preg_replace('/^www\./', '', $originHost);
    $currentHostNormalized = preg_replace('/^www\./', '', $currentHost);
    
    if (!empty($origin) && $originHostNormalized !== $currentHostNormalized) {
        // Check if origin is in allowed list
        $originAllowed = false;
        foreach ($allowedOrigins as $allowed) {
            $allowedHost = parse_url($allowed, PHP_URL_HOST) ?? '';
            $allowedHostNormalized = preg_replace('/^www\./', '', $allowedHost);
            if ($originHostNormalized === $allowedHostNormalized) {
                $originAllowed = true;
                break;
            }
        }
        if (!$originAllowed) {
            error_log('Security: Form submission from unauthorized origin: ' . $origin);
            $errors[] = 'Invalid request origin. Please refresh the page and try again.';
            $_POST = []; // Clear POST data
        }
    }
    
    // 2. Referer Check - Verify referer is from same domain
    $referer = $_SERVER['HTTP_REFERER'] ?? '';
    if (!empty($referer)) {
        $refererHost = parse_url($referer, PHP_URL_HOST) ?? '';
        $refererHostNormalized = preg_replace('/^www\./', '', $refererHost);
        if ($refererHostNormalized !== $currentHostNormalized && !in_array($refererHost, ['projectcutisem.com', 'www.projectcutisem.com'])) {
            error_log('Security: Form submission with invalid referer: ' . $referer);
            // Don't block, just log (some browsers/proxies don't send referer)
        }
    }
    
    // 3. CSRF Protection - validate token (existing)
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid security token. Please refresh the page and try again.';
        // Don't process form if CSRF token is invalid
        $_POST = []; // Clear POST data
    }
    
    // 4. Request Method Validation
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        $errors[] = 'Invalid request method.';
        $_POST = []; // Clear POST data
    }
    
    // 5. Content-Type Check (basic - log only, don't block)
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    if (!empty($contentType) && strpos($contentType, 'application/x-www-form-urlencoded') === false && strpos($contentType, 'multipart/form-data') === false) {
        error_log('Security: Unexpected content type: ' . $contentType);
        // Don't block, just log (some legitimate requests might have different content types)
    }
    
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
    
    // Get order type (validate it's either pickup or delivery)
    $orderType = isset($_POST['order_type']) && in_array($_POST['order_type'], ['pickup', 'delivery']) 
        ? $_POST['order_type'] 
        : 'delivery';
    
    // Calculate delivery fee (server-side validation)
    $deliveryFee = 0.00;
    if ($orderType === 'delivery') {
        $deliveryFee = DELIVERY_FEE;
        // Validate address fields are required for delivery
        if (empty($address1)) {
            $errors[] = 'Address is required for delivery';
        }
        if (empty($state) || !in_array($state, $malaysiaStates)) {
            $errors[] = 'Valid state is required for delivery';
        }
        if (empty($district)) {
            $errors[] = 'District/City is required for delivery';
        }
        if (empty($postcode) || !preg_match('/^\d{5}$/', $postcode)) {
            $errors[] = 'Valid postcode (5 digits) is required for delivery';
        }
    }
    
    // Get promo code from hidden field (set by AJAX validation) or from input field
    $promoCodeInput = strtoupper(trim($_POST['promo_code_applied'] ?? $_POST['promo_code'] ?? ''));
    $promoDiscountFromForm = floatval($_POST['promo_discount'] ?? 0);
    
    // Validation
    if (empty($fullname)) {
        $errors[] = 'Full name is required';
    }
    
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Valid email is required';
    }
    
    if (empty($phone)) {
        $errors[] = 'Phone number is required';
    }
    
    // Address validation only required for delivery (already checked above)
    // For pickup, address fields are optional
    
    // Validate promotion code if provided
    if (!empty($promoCodeInput)) {
        try {
            $pdo = getDBConnection();
            
            // Validate promotion code using centralized function (follows exact validation order)
            $validation = validatePromotionCode($promoCodeInput, $cartSubtotal, $pdo);
            
            if (!$validation['success']) {
                $errors[] = $validation['error'];
                $discountAmount = 0.00;
                $promoCode = '';
            } else {
                $promotion = $validation['promotion'];
                $discountAmount = calculatePromotionDiscount($promotion, $cartSubtotal);
                $promoCode = strtoupper(trim($promoCodeInput));
            }
        } catch (PDOException $e) {
            error_log('Promotion validation error: ' . $e->getMessage());
            $errors[] = 'Error validating promotion code';
            $discountAmount = 0.00;
            $promoCode = '';
        }
    }
    
    // Store order_type in session
    $_SESSION['order_type'] = $orderType;
    
    // Calculate final amount: subtotal + delivery_fee - discount (all rounded to 2 decimal places)
    $finalAmount = round($cartSubtotal + $deliveryFee - $discountAmount, 2);
    if ($finalAmount < 0) {
        $finalAmount = 0.00;
    }
    
    // Log discount calculation for debugging
    error_log('Checkout - Cart Subtotal: ' . $cartSubtotal);
    error_log('Checkout - Discount Amount: ' . $discountAmount);
    error_log('Checkout - Final Amount: ' . $finalAmount);
    error_log('Checkout - Promo Code: ' . ($promoCode ?? 'none'));
    
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
            
            // Prepare insert statement with all new fields including order_type, delivery_fee, final_amount
            // NOTE: gateway_fee, pushed_amount, net_received will be updated after order creation
            // This is part of TEMPORARY WORKAROUND for gateway fee absorption
            $stmt = $pdo->prepare("
                INSERT INTO orders (
                    name, fullname, email, phone, 
                    address1, address2, address3, state, district, postcode,
                    variant, size, quantity, 
                    amount, promo_code, discount_amount,
                    order_type, delivery_fee, final_amount,
                    billExternalReferenceNo, status, created_at
                ) VALUES (
                    :name, :fullname, :email, :phone,
                    :address1, :address2, :address3, :state, :district, :postcode,
                    :variant, :size, :quantity,
                    :amount, :promo_code, :discount_amount,
                    :order_type, :delivery_fee, :final_amount,
                    :billExternalReferenceNo, 'pending', :created_at
                )
            ");
            
            // Insert each cart item as a separate order record
            foreach ($cart as $item) {
                // Calculate item amount (proportional discount if applicable)
                $itemAmount = $item['subtotal'];
                $itemDiscount = 0;
                if ($discountAmount > 0) {
                    // Apply proportional discount to each item
                    $itemDiscount = ($item['subtotal'] / $cartSubtotal) * $discountAmount;
                    $itemAmount = $item['subtotal'] - $itemDiscount;
                }
                
                // Calculate item final amount (proportional delivery fee + item amount - item discount)
                $itemDeliveryFee = ($item['subtotal'] / $cartSubtotal) * $deliveryFee;
                $itemFinalAmount = $itemAmount + $itemDeliveryFee;
                
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
                    ':discount_amount' => $itemDiscount,
                    ':order_type' => $orderType,
                    ':delivery_fee' => $itemDeliveryFee,
                    ':final_amount' => $itemFinalAmount,
                    ':billExternalReferenceNo' => $billExternalReferenceNo,
                    ':created_at' => getCurrentDateTimeUTC() // Store in UTC
                ]);
            }
            
            // Increment promotion usage count if code was used (after order creation)
            if (!empty($promoCode)) {
                incrementPromotionUsage($promoCode, $pdo);
            }
            
            // Clear promotion from session after order creation
            clearPromotionFromSession();
            
            // ============================================
            // STEP 8: Prepare Bill Data for ToyyibPay
            // ============================================
            // TEMPORARY WORKAROUND: Gateway Fee Absorption
            // Company absorbs RM1.00 ToyyibPay gateway fee
            // Customer sees and pays original price, but we deduct RM1 when pushing to gateway
            // TODO: Remove this workaround when implementing proper fee handling system
            // ============================================
            
            // Validate final amount
            if ($finalAmount <= 0) {
                throw new Exception('Invalid order amount. Final amount must be greater than 0.');
            }
            
            // Gateway fee constant (RM1.00)
            $gatewayFee = 1.00;
            
            // Calculate pushed amount (amount sent to ToyyibPay)
            // This is the amount customer will actually pay (original - gateway fee)
            $pushedAmount = round($finalAmount - $gatewayFee, 2);
            
            // Validation: Prevent zero or negative payment amounts
            if ($pushedAmount <= 0) {
                throw new Exception('Invalid payment amount after gateway fee deduction. Final amount: RM ' . number_format($finalAmount, 2) . ', Pushed amount: RM ' . number_format($pushedAmount, 2) . '. Order total must be greater than RM1.00.');
            }
            
            // Net received is same as pushed amount (company absorbs the fee)
            $netReceived = $pushedAmount;
            
            // Update all orders with gateway fee information
            // Store gateway_fee, pushed_amount, net_received for internal reference
            $updateGatewayFeeStmt = $pdo->prepare("
                UPDATE orders 
                SET gateway_fee = :gateway_fee,
                    pushed_amount = :pushed_amount,
                    net_received = :net_received
                WHERE billExternalReferenceNo = :ref_no
            ");
            $updateGatewayFeeStmt->execute([
                ':gateway_fee' => $gatewayFee,
                ':pushed_amount' => $pushedAmount,
                ':net_received' => $netReceived,
                ':ref_no' => $billExternalReferenceNo
            ]);
            
            // Convert RM to sen (RM50.00 = 5000 sen)
            // IMPORTANT: Use pushedAmount (not finalAmount) when sending to ToyyibPay
            // This is the TEMPORARY WORKAROUND - we deduct RM1 here
            $billAmount = (int)round($pushedAmount * 100);
            
            // Log payment calculation for debugging
            error_log('=== CHECKOUT PAYMENT CALCULATION (WITH GATEWAY FEE ABSORPTION) ===');
            error_log('Cart Subtotal: RM ' . number_format($cartSubtotal, 2));
            error_log('Discount Amount: RM ' . number_format($discountAmount, 2));
            error_log('Final Amount (original, shown to customer): RM ' . number_format($finalAmount, 2));
            error_log('Gateway Fee (absorbed by company): RM ' . number_format($gatewayFee, 2));
            error_log('Pushed Amount (sent to ToyyibPay): RM ' . number_format($pushedAmount, 2));
            error_log('Net Received (actual amount): RM ' . number_format($netReceived, 2));
            error_log('Bill Amount (sen, sent to gateway): ' . $billAmount);
            error_log('Promo Code: ' . ($promoCode ?: 'none'));
            error_log('⚠️  TEMPORARY WORKAROUND: Company absorbing RM1 gateway fee');
            error_log('====================================');
            
            // Validate bill amount
            if ($billAmount <= 0) {
                throw new Exception('Invalid bill amount. Amount must be greater than 0. Pushed amount: ' . $pushedAmount);
            }
            
            // Double-check: Ensure billAmount matches pushedAmount
            $expectedBillAmount = (int)round($pushedAmount * 100);
            if ($billAmount !== $expectedBillAmount) {
                error_log('WARNING: Bill amount mismatch! Calculated: ' . $billAmount . ', Expected: ' . $expectedBillAmount);
                $billAmount = $expectedBillAmount; // Use correct value
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
                // Single item: "PCS Half-Zip Workshirt - Classic Black, Size M, Qty 2"
                $item = $cart[0];
                $billDescription = sprintf(
                    "PCS Half-Zip Workshirt - %s, Size %s, Qty %d",
                    $item['variant_name'],
                    $item['size'],
                    $item['quantity']
                );
            } else {
                // Multiple items: "PCS Half-Zip Workshirt - 3 items, Total Qty 5"
                $billDescription = sprintf(
                    "PCS Half-Zip Workshirt - %d items, Total Qty %d",
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
            
            // Get ToyyibPay configuration (staging or production based on settings)
            $toyyibpayConfig = getToyyibPayConfig();
            
            // Prepare bill data (matching sandbox-test.php structure exactly)
            $billData = [
                // Required: ToyyibPay credentials (from config helper)
                'userSecretKey' => $toyyibpayConfig['secret_key'],
                'categoryCode' => $toyyibpayConfig['category_code'],
                
                // Bill information
                'billName' => 'PCS Half-Zip Workshirt Order',
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
            
            // Log configuration and bill data for debugging
            error_log('ToyyibPay Config Used - Endpoint: ' . $toyyibpayConfig['endpoint']);
            error_log('ToyyibPay Config Used - Category Code: ' . $toyyibpayConfig['category_code']);
            error_log('ToyyibPay Bill Data: ' . json_encode($billData, JSON_PRETTY_PRINT));
            
            // ============================================
            // STEP 9: Call ToyyibPay API to Create Bill
            // ============================================
            // Initialize cURL untuk API call (use endpoint from config)
            $ch = curl_init($toyyibpayConfig['endpoint']);
            
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
            
            // Get dynamic base URL based on current mode (staging/production)
            $toyyibpayBaseUrl = getToyyibPayBaseUrl();
            
            // Redirect user to ToyyibPay payment page (dynamically switches between staging and production)
            header('Location: ' . $toyyibpayBaseUrl . '/' . $billCode);
            exit;
            
        } catch (Exception $e) {
            // Log detailed error for debugging
            $errorMessage = $e->getMessage();
            error_log('Checkout processing error: ' . $errorMessage);
            error_log('Stack trace: ' . $e->getTraceAsString());
            
            // Show error message based on debug mode
            if (DEBUG_MODE) {
                // Show detailed error in development
                $errors[] = 'Error processing checkout: ' . htmlspecialchars($errorMessage);
            } else {
                // Show generic error in production
                $errors[] = 'Error processing checkout. Please try again or contact us.';
            }
        }
    }
}

// Calculate display totals (for initial page load or after promo validation)
if (isset($_POST['promo_discount'])) {
    $discountAmount = floatval($_POST['promo_discount']);
    $promoCode = $_POST['promo_code_applied'] ?? '';
}
// Recalculate with delivery fee
$currentOrderType = $_SESSION['order_type'] ?? 'delivery';
$currentDeliveryFee = ($currentOrderType === 'delivery') ? DELIVERY_FEE : 0;
$finalAmount = $cartSubtotal + $currentDeliveryFee - $discountAmount;
if ($finalAmount < 0) {
    $finalAmount = 0;
}
?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">
    <title>Checkout - <?php echo htmlspecialchars(PRODUCT_NAME); ?> | Projek Cuti Sem</title>
    <link rel="icon" type="image/x-icon" href="https://projectcutisem.com/favicon.ico">
    <link rel="shortcut icon" type="image/x-icon" href="https://projectcutisem.com/favicon.ico">
    <link rel="apple-touch-icon" href="https://projectcutisem.com/apple-touch-icon.png">
    <link rel="stylesheet" href="<?php echo getVersionedAsset('css/order-baju.css'); ?>">
    <link rel="stylesheet" href="<?php echo getVersionedAsset('css/checkout.css'); ?>">
</head>
<body>
    <!-- Navigation Bar -->
    <nav class="order-nav">
        <div class="nav-container">
            <a href="/" class="nav-logo-link">
                <figure class="nav-logo">
                    <img src="images/PCS logo.svg" alt="Projek Cuti Sem" width="40" height="40" fetchpriority="high">
                </figure>
                <span class="nav-brand-text">PROJEK CUTI SEM</span>
            </a>
            <a href="order-baju.php" class="nav-back-link">← Back to Order</a>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="checkout-main">
        <div class="checkout-container">
            <h1 class="checkout-page-title">Checkout</h1>
            
            <!-- Pre-Order Notice Banner -->
            <div class="pre-order-banner">
                <div style="display: inline-flex; align-items: center; gap: 0.75rem; margin-bottom: 0.75rem;">
                    <div style="font-size: 2rem;">📦</div>
                    <strong>PRE-ORDER</strong>
                </div>
                <p>
                    <strong>This is a pre-order.</strong> We will send order progress updates via email at each stage of the process. 
                    Please ensure your email address is correct and active to receive notifications.
                </p>
            </div>
            
            <?php if (!empty($errors)): ?>
                <div class="checkout-errors">
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
        
        <form method="POST" action="" id="checkoutForm">
            <input type="hidden" name="action" value="checkout">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            <input type="hidden" name="promo_code_applied" id="promoCodeApplied" value="<?php echo htmlspecialchars($promoCode); ?>">
            <input type="hidden" name="promo_discount" id="promoDiscount" value="<?php echo $discountAmount; ?>">
            
            <div class="checkout-grid">
                <!-- Order Summary -->
                <div class="checkout-section">
                    <h2>Order Summary</h2>
                    
                    <!-- Pre-Order Notice -->
                    <div class="pre-order-banner-inline">
                        <div style="display: flex; align-items: start; gap: 0.75rem;">
                            <div style="font-size: 1.25rem;">📦</div>
                            <div>
                                <strong>Pre-Order</strong>
                                <p>
                                    We will send order progress updates via email at each stage of the process.
                                </p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="cart-items-summary">
                        <?php foreach ($cart as $item): 
                            $itemSize = $item['size'] ?? '';
                            $itemQuantity = $item['quantity'] ?? 1;
                            $itemSizeAddon = isset($item['size_addon']) ? $item['size_addon'] : getSizeAddonPrice($itemSize);
                            $itemBasePrice = PRICE_PER_SHIRT * $itemQuantity;
                            $itemSizeAddonTotal = $itemSizeAddon * $itemQuantity;
                        ?>
                            <div class="cart-summary-item">
                                <img src="<?php echo htmlspecialchars($item['variant_image']); ?>" 
                                     alt="<?php echo htmlspecialchars($item['variant_name']); ?>"
                                     class="cart-summary-item-image">
                                <div class="cart-summary-item-details">
                                    <h4><?php echo htmlspecialchars($item['variant_name']); ?></h4>
                                    <p>Size: <?php echo htmlspecialchars($item['size']); ?> | Quantity: <?php echo $item['quantity']; ?></p>
                                    <?php if ($itemSizeAddon > 0): ?>
                                        <p style="font-size: 0.85rem; color: #666; margin-top: 0.25rem;">
                                            Base: RM <?php echo number_format(PRICE_PER_SHIRT, 2); ?> × <?php echo $itemQuantity; ?> 
                                            + Size Addon: RM <?php echo number_format($itemSizeAddon, 2); ?> × <?php echo $itemQuantity; ?>
                                        </p>
                                    <?php endif; ?>
                                </div>
                                <div class="cart-summary-item-price">
                                    RM <?php echo number_format($item['subtotal'], 2); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <!-- Promotion Code Section -->
                    <div class="promo-section">
                        <label>Promotion Code</label>
                        <div class="promo-input-group">
                            <input type="text" 
                                   id="promoCodeInput" 
                                   placeholder="Enter code" 
                                   value="<?php echo htmlspecialchars($promoCode); ?>">
                            <button type="button" id="applyPromoBtn"><span>Apply</span></button>
                        </div>
                        <div id="promoMessage"></div>
                    </div>
                    
                    <div class="order-total">
                        <div style="margin-bottom: 1rem; padding-bottom: 1rem; border-bottom: 1px solid #e0e0e0;">
                            <h3 style="font-size: 1rem; font-weight: 600; margin-bottom: 0.75rem; color: #333;">Order Breakdown</h3>
                            
                            <div class="order-total-row" style="font-size: 0.9rem; color: #666;">
                                <span>Base Price (<?php echo $cartCount; ?> item(s)):</span>
                                <span>RM <?php echo number_format($totalBasePrice, 2); ?></span>
                            </div>
                            
                            <?php if ($totalSizeAddons > 0): ?>
                            <div class="order-total-row" style="font-size: 0.9rem; color: #666;">
                                <span>Size Addons (3XL+):</span>
                                <span>+ RM <?php echo number_format($totalSizeAddons, 2); ?></span>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="order-total-row">
                            <span>Subtotal:</span>
                            <span>RM <?php echo number_format($cartSubtotal, 2); ?></span>
                        </div>
                        <div class="order-total-row" id="deliveryFeeRow">
                            <span>Delivery Fee:</span>
                            <span id="deliveryFeeDisplay">RM <?php echo number_format($currentDeliveryFee, 2); ?></span>
                        </div>
                        <?php if ($discountAmount > 0): ?>
                            <div class="order-total-row discount">
                                <span>Discount (<?php echo htmlspecialchars($promoCode); ?>):</span>
                                <span>- RM <?php echo number_format($discountAmount, 2); ?></span>
                            </div>
                        <?php endif; ?>
                        <div class="order-total-row final">
                            <span>Total Amount to Pay:</span>
                            <span id="finalTotalDisplay">RM <?php echo number_format($finalAmount, 2); ?></span>
                        </div>
                        
                        <div style="margin-top: 1rem; padding-top: 1rem; border-top: 1px solid #e0e0e0; font-size: 0.85rem; color: #999;">
                            <p style="margin: 0.25rem 0;">
                                <strong>Note:</strong> Payment gateway fee (RM 1.00) is absorbed by the company.
                            </p>
                            <p style="margin: 0.25rem 0;">
                                Amount sent to payment gateway: RM <?php echo number_format(max(0, $finalAmount - 1.00), 2); ?>
                            </p>
                        </div>
                    </div>
                </div>
                
                <!-- Customer Information -->
                <div class="checkout-section">
                    <h2>Customer Information</h2>
                    
                    <!-- Order Type Selection -->
                    <div class="form-group">
                        <label for="order_type">Order Type <span class="required">*</span></label>
                        <select name="order_type" id="order_type" class="order-type-select" required>
                            <option value="pickup" <?php echo ($currentOrderType ?? 'delivery') === 'pickup' ? 'selected' : ''; ?>>
                                Pickup - Free (Collect from our store)
                            </option>
                            <option value="delivery" <?php echo ($currentOrderType ?? 'delivery') === 'delivery' ? 'selected' : ''; ?>>
                                Delivery - RM <?php echo number_format(DELIVERY_FEE, 2); ?> delivery fee
                            </option>
                        </select>
                    </div>
                    
                    <div class="form-group required">
                        <label for="fullname">Full Name</label>
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
                            <label for="phone">Phone Number</label>
                            <input type="tel" name="phone" id="phone" 
                                   value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>" 
                                   required>
                        </div>
                    </div>
                    
                    <!-- Delivery Address Section (shown only for delivery) -->
                    <div id="deliveryAddressSection" style="display: <?php echo ($currentOrderType ?? 'delivery') === 'delivery' ? 'block' : 'none'; ?>;">
                        <h3>Delivery Address</h3>
                        
                        <div class="form-group required" data-required-for-delivery>
                            <label for="address1">Address</label>
                            <textarea name="address1" id="address1" 
                                      rows="3"
                                      placeholder="Enter your full delivery address"
                                      data-required-for-delivery><?php echo isset($_POST['address1']) ? htmlspecialchars($_POST['address1']) : ''; ?></textarea>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group required" data-required-for-delivery>
                                <label for="state">State</label>
                                <select name="state" id="state" data-required-for-delivery>
                                    <option value="">-- Select State --</option>
                                    <?php foreach ($malaysiaStates as $stateOption): ?>
                                        <option value="<?php echo htmlspecialchars($stateOption); ?>"
                                                <?php echo (isset($_POST['state']) && $_POST['state'] === $stateOption) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($stateOption); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="form-group required" data-required-for-delivery>
                                <label for="district">District/City</label>
                                <input type="text" name="district" id="district" 
                                       value="<?php echo isset($_POST['district']) ? htmlspecialchars($_POST['district']) : ''; ?>" 
                                       data-required-for-delivery>
                            </div>
                        </div>
                        
                        <div class="form-group required" data-required-for-delivery>
                            <label for="postcode">Postcode</label>
                            <input type="text" name="postcode" id="postcode" 
                                   pattern="[0-9]{5}" 
                                   maxlength="5"
                                   value="<?php echo isset($_POST['postcode']) ? htmlspecialchars($_POST['postcode']) : ''; ?>" 
                                   data-required-for-delivery>
                            <small>5 digits only</small>
                        </div>
                    </div>
                    
                    <!-- Hidden fields for backward compatibility (keep address2 and address3 as empty for delivery) -->
                    <input type="hidden" name="address2" value="">
                    <input type="hidden" name="address3" value="">
                    
                    <!-- Pre-Order Notice -->
                    <div class="pre-order-banner-inline" style="margin-top: 1.5rem;">
                        <div style="display: flex; align-items: start; gap: 0.75rem;">
                            <div style="font-size: 1.5rem;">📦</div>
                            <div>
                                <strong>Pre-Order Notice</strong>
                                <p>
                                    This is a <strong>pre-order</strong>. We will send order progress updates via email at each stage of the process. 
                                    Please ensure your email address is correct and active.
                                </p>
                            </div>
                        </div>
                    </div>
                    
                    <button type="submit" class="submit-btn"><span>Proceed to Payment</span></button>
                </div>
            </div>
        </form>
        </div>
    </main>
    
    <script>
        // ============================================
        // Promotion Code Validation (AJAX)
        // ============================================
        document.getElementById('applyPromoBtn').addEventListener('click', function() {
            const promoCode = document.getElementById('promoCodeInput').value.trim().toUpperCase();
            const promoMessage = document.getElementById('promoMessage');
            
            if (!promoCode) {
                promoMessage.innerHTML = '<div class="promo-message error">Please enter a promotion code</div>';
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
                    
                    // Update display (data.final already includes delivery fee from server)
                    updateOrderTotal(data.subtotal, data.discount, data.final);
                    
                    // Show success message
                    promoMessage.innerHTML = '<div class="promo-message success">Promotion code applied successfully! Discount: RM ' + data.discount.toFixed(2) + '</div>';
                } else {
                    // Show error message
                    promoMessage.innerHTML = '<div class="promo-message error">' + data.error + '</div>';
                    
                    // Clear discount
                    document.getElementById('promoCodeApplied').value = '';
                    document.getElementById('promoDiscount').value = '0';
                    // Promo code validation failed
                    // Recalculate with current order type
                    const orderTypeSelect = document.getElementById('order_type');
                    const orderType = orderTypeSelect ? orderTypeSelect.value : 'delivery';
                    const deliveryFee = (orderType === 'delivery') ? DELIVERY_FEE : 0;
                    const subtotal = data.subtotal || CART_SUBTOTAL;
                    updateOrderTotal(subtotal, 0, subtotal + deliveryFee);
                }
                
                // Re-enable button
                this.disabled = false;
                this.textContent = 'Apply';
            })
            .catch(error => {
                // Error handling (logging removed for production)
                promoMessage.innerHTML = '<div class="promo-message error">Error validating promotion code</div>';
                this.disabled = false;
                this.textContent = 'Apply';
            });
        });
        
        // Constants
        const DELIVERY_FEE = <?php echo DELIVERY_FEE; ?>;
        const CART_SUBTOTAL = <?php echo $cartSubtotal; ?>;
        
        // Get current discount amount
        function getCurrentDiscount() {
            return parseFloat(document.getElementById('promoDiscount').value) || 0;
        }
        
        // Calculate and update totals based on order type
        function updateTotalsForOrderType(orderType) {
            const deliveryFee = (orderType === 'delivery') ? DELIVERY_FEE : 0;
            const discount = getCurrentDiscount();
            const final = CART_SUBTOTAL + deliveryFee - discount;
            
            // Update delivery fee display
            const deliveryFeeDisplay = document.getElementById('deliveryFeeDisplay');
            if (deliveryFeeDisplay) {
                deliveryFeeDisplay.textContent = 'RM ' + deliveryFee.toFixed(2);
            }
            
            // Update final total
            const finalTotalDisplay = document.getElementById('finalTotalDisplay');
            if (finalTotalDisplay) {
                finalTotalDisplay.textContent = 'RM ' + Math.max(0, final).toFixed(2);
            }
            
            return { deliveryFee, discount, final: Math.max(0, final) };
        }
        
        // Update order total display
        function updateOrderTotal(subtotal, discount, final) {
            const subtotalRow = document.querySelector('.order-total-row:first-child span:last-child');
            const finalRow = document.getElementById('finalTotalDisplay');
            
            if (subtotalRow) {
                subtotalRow.textContent = 'RM ' + subtotal.toFixed(2);
            }
            
            // Get current order type to calculate delivery fee
            const orderTypeSelect = document.getElementById('order_type');
            const orderType = orderTypeSelect ? orderTypeSelect.value : 'delivery';
            const deliveryFee = (orderType === 'delivery') ? DELIVERY_FEE : 0;
            const calculatedFinal = subtotal + deliveryFee - discount;
            
            // Update delivery fee display
            const deliveryFeeDisplay = document.getElementById('deliveryFeeDisplay');
            if (deliveryFeeDisplay) {
                deliveryFeeDisplay.textContent = 'RM ' + deliveryFee.toFixed(2);
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
                const promoCode = document.getElementById('promoCodeApplied').value;
                discountRow.innerHTML = '<span>Discount' + (promoCode ? ' (' + promoCode + ')' : '') + ':</span><span>- RM ' + discount.toFixed(2) + '</span>';
                discountRow.style.display = 'flex';
            } else if (discountRow) {
                discountRow.style.display = 'none';
            }
            
            if (finalRow) {
                finalRow.textContent = 'RM ' + Math.max(0, calculatedFinal).toFixed(2);
            }
        }
        
        // Handle order type change (dropdown)
        const orderTypeSelect = document.getElementById('order_type');
        const deliveryAddressSection = document.getElementById('deliveryAddressSection');
        
        if (orderTypeSelect) {
            orderTypeSelect.addEventListener('change', function() {
                const orderType = this.value;
                updateTotalsForOrderType(orderType);
                
                // Show/hide delivery address section
                if (orderType === 'delivery') {
                    deliveryAddressSection.style.display = 'block';
                    // Add required attributes
                    const addressFields = deliveryAddressSection.querySelectorAll('[data-required-for-delivery]');
                    addressFields.forEach(field => {
                        field.setAttribute('required', 'required');
                    });
                } else {
                    deliveryAddressSection.style.display = 'none';
                    // Remove required attributes
                    const addressFields = deliveryAddressSection.querySelectorAll('[data-required-for-delivery]');
                    addressFields.forEach(field => {
                        field.removeAttribute('required');
                        field.value = ''; // Clear values when hidden
                    });
                }
            });
            
            // Trigger on page load to set initial state
            orderTypeSelect.dispatchEvent(new Event('change'));
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
            // Log promo code values before submit
            const promoCodeApplied = document.getElementById('promoCodeApplied').value;
            const promoDiscount = document.getElementById('promoDiscount').value;
            // Form submission (logging removed for production)
            
            const fullname = document.getElementById('fullname').value.trim();
            const email = document.getElementById('email').value.trim();
            const phone = document.getElementById('phone').value.trim();
            const orderTypeSelect = document.getElementById('order_type');
            const orderType = orderTypeSelect ? orderTypeSelect.value : 'delivery';
            
            // Basic validation
            if (!fullname || !email || !phone) {
                e.preventDefault();
                alert('Please fill in all required fields.');
                return false;
            }
            
            // Email validation
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(email)) {
                e.preventDefault();
                alert('Please enter a valid email address.');
                return false;
            }
            
            // Address validation only for delivery
            if (orderType === 'delivery') {
                const address1 = document.getElementById('address1').value.trim();
                const state = document.getElementById('state').value;
                const district = document.getElementById('district').value.trim();
                const postcode = document.getElementById('postcode').value.trim();
                
                if (!address1 || !state || !district || !postcode) {
                    e.preventDefault();
                    alert('Please fill in all delivery address fields.');
                    return false;
                }
                
                // Postcode validation
                if (!/^\d{5}$/.test(postcode)) {
                    e.preventDefault();
                    alert('Postcode must be 5 digits.');
                    return false;
                }
            }
            
            // Show loading state
            const submitBtn = document.querySelector('.submit-btn');
            submitBtn.disabled = true;
            submitBtn.textContent = 'Processing...';
        });
    </script>
</body>
</html>
