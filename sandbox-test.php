<?php
/**
 * ToyyibPay SANDBOX / Staging Integration
 * Creates a bill and redirects user to ToyyibPay payment page
 */

// ============================================
// STEP 1: Configuration
// ============================================
// Gantikan dengan staging credentials sebenar
define('STAGING_SECRET_KEY', 'dh1kldj2-j8db-ofm0-qauv-e037egz4oiui');
define('STAGING_CATEGORY_CODE', 'g3ii2ms2');

// Base URL untuk staging environment (gantikan dengan domain sebenar)
$baseUrl = 'https://staging.yourdomain.com';

// ============================================
// STEP 2: Generate Unique Bill Reference
// ============================================
// Generate unique bill reference number untuk tracking
$billExternalReferenceNo = 'BILL-' . time() . '-' . uniqid();

// ============================================
// STEP 3: Prepare Bill Data
// ============================================
$billData = [
    // Required: ToyyibPay credentials
    'userSecretKey' => STAGING_SECRET_KEY,
    'categoryCode' => STAGING_CATEGORY_CODE,
    
    // Bill information
    'billName' => 'Test Bill Payment',
    'billDescription' => 'Staging Test Payment',
    
    // Price setting: 1 = Fixed amount, 2 = Multiple options
    'billPriceSetting' => 1,
    
    // Collect payer info: 1 = Yes (name, email, phone), 0 = No
    'billPayorInfo' => 1,
    
    // Bill amount in sen (RM1.00 = 100 sen)
    'billAmount' => 100,
    
    // Unique reference number untuk tracking
    'billExternalReferenceNo' => $billExternalReferenceNo,
    
    // Payer information (required even if billPayorInfo = 1)
    // User boleh override nilai ini di payment page
    'billTo' => 'Test User',
    'billEmail' => 'test@example.com',
    'billPhone' => '60123456789',
    
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

// ============================================
// STEP 4: Call ToyyibPay API to Create Bill
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
// STEP 5: Handle API Response
// ============================================
// Check for cURL errors
if ($curlError) {
    die('Error: cURL request failed - ' . $curlError);
}

// Check HTTP status code
if ($httpCode !== 200) {
    die('Error: HTTP request failed with status code ' . $httpCode . '<br>Response: ' . htmlspecialchars($response));
}

// Decode JSON response safely
$result = json_decode($response, true);

// Check if JSON decode was successful
if (json_last_error() !== JSON_ERROR_NONE) {
    die('Error: Invalid JSON response - ' . json_last_error_msg() . '<br>Response: ' . htmlspecialchars($response));
}

// Check for API error response
if (isset($result[0]['status']) && $result[0]['status'] === 'error') {
    $errorMsg = isset($result[0]['msg']) ? $result[0]['msg'] : 'Unknown error';
    die('Error: ' . htmlspecialchars($errorMsg) . '<br>Response: ' . htmlspecialchars($response));
}

// Check if response contains BillCode
if (!isset($result[0]['BillCode']) || empty($result[0]['BillCode'])) {
    die('Error: BillCode not returned in response.<br>Response: ' . htmlspecialchars($response));
}

// ============================================
// STEP 6: Redirect User to Payment Page
// ============================================
// Get BillCode from response
$billCode = $result[0]['BillCode'];

// Redirect user to ToyyibPay payment page
header('Location: https://dev.toyyibpay.com/' . $billCode);
exit;

