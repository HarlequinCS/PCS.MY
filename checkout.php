<?php
/**
 * Checkout Page with ToyyibPay Integration
 * Displays cart items, collects user information, and processes payment
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

// Variant names for display
$variantNames = [
    'hitamdepan' => 'Hitam Depan',
    'hijaufront' => 'Hijau Front',
    'hijaudepan' => 'Merah Depan'
];

// ============================================
// STEP 3: Calculate Cart Totals
// ============================================
$cart = $_SESSION['cart'];
$cartCount = count($cart);
$cartTotal = 0;
foreach ($cart as $item) {
    $cartTotal += $item['subtotal'];
}

// ============================================
// STEP 4: Handle Form Submission
// ============================================
$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate required fields
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    
    // Validation
    if (empty($name)) {
        $errors[] = 'Nama diperlukan';
    }
    
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Email yang sah diperlukan';
    }
    
    if (empty($phone)) {
        $errors[] = 'Nombor telefon diperlukan';
    }
    
    if (empty($address)) {
        $errors[] = 'Alamat diperlukan';
    }
    
    // If no errors, process checkout
    if (empty($errors)) {
        try {
            // ============================================
            // STEP 5: Generate Unique Bill Reference
            // ============================================
            $billExternalReferenceNo = 'BILL-' . time() . '-' . uniqid();
            
            // ============================================
            // STEP 6: Save All Cart Items to Database
            // ============================================
            $pdo = getDBConnection();
            
            // Prepare insert statement
            $stmt = $pdo->prepare("
                INSERT INTO orders (
                    name, email, phone, variant, size, quantity, 
                    amount, billExternalReferenceNo, status, created_at
                ) VALUES (
                    :name, :email, :phone, :variant, :size, :quantity,
                    :amount, :billExternalReferenceNo, 'pending', NOW()
                )
            ");
            
            // Insert each cart item as a separate order record
            foreach ($cart as $item) {
                $stmt->execute([
                    ':name' => $name,
                    ':email' => $email,
                    ':phone' => $phone,
                    ':variant' => $item['variant'],
                    ':size' => $item['size'],
                    ':quantity' => $item['quantity'],
                    ':amount' => $item['subtotal'],
                    ':billExternalReferenceNo' => $billExternalReferenceNo
                ]);
            }
            
            // ============================================
            // STEP 7: Prepare Bill Data for ToyyibPay
            // ============================================
            // Convert RM to sen (RM50.00 = 5000 sen)
            $billAmount = (int)($cartTotal * 100);
            
            // Build description with order details
            $itemDescriptions = [];
            foreach ($cart as $item) {
                $itemDescriptions[] = sprintf(
                    "%s (Size: %s, Qty: %d)",
                    $item['variant_name'],
                    $item['size'],
                    $item['quantity']
                );
            }
            $billDescription = "Baju Premium - " . implode(", ", $itemDescriptions);
            
            $billData = [
                // Required: ToyyibPay credentials
                'userSecretKey' => STAGING_SECRET_KEY,
                'categoryCode' => STAGING_CATEGORY_CODE,
                
                // Bill information
                'billName' => 'Baju Premium Order',
                'billDescription' => $billDescription,
                
                // Price setting: 1 = Fixed amount
                'billPriceSetting' => 1,
                
                // Collect payer info: 1 = Yes (name, email, phone)
                'billPayorInfo' => 1,
                
                // Bill amount in sen
                'billAmount' => $billAmount,
                
                // Unique reference number untuk tracking
                'billExternalReferenceNo' => $billExternalReferenceNo,
                
                // Payer information
                'billTo' => $name,
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
            // STEP 8: Call ToyyibPay API to Create Bill
            // ============================================
            $ch = curl_init('https://dev.toyyibpay.com/index.php/api/createBill');
            
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
            // STEP 9: Handle API Response & Error Handling
            // ============================================
            if ($curlError) {
                error_log('cURL Error: ' . $curlError);
                throw new Exception('cURL request failed: ' . $curlError);
            }
            
            if ($httpCode !== 200) {
                error_log('HTTP Error: Status ' . $httpCode . ', Response: ' . $response);
                throw new Exception('HTTP request failed with status code ' . $httpCode);
            }
            
            // Decode JSON response
            $result = json_decode($response, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                error_log('JSON Decode Error: ' . json_last_error_msg() . ', Response: ' . $response);
                throw new Exception('Invalid JSON response: ' . json_last_error_msg());
            }
            
            // Check for API error response
            if (isset($result[0]['status']) && $result[0]['status'] === 'error') {
                $errorMsg = isset($result[0]['msg']) ? $result[0]['msg'] : 'Unknown error';
                error_log('ToyyibPay API Error: ' . $errorMsg . ', Full Response: ' . json_encode($result));
                throw new Exception('ToyyibPay API Error: ' . $errorMsg);
            }
            
            // Check if response contains BillCode
            if (!isset($result[0]['BillCode']) || empty($result[0]['BillCode'])) {
                error_log('BillCode Missing: Full Response: ' . json_encode($result));
                throw new Exception('BillCode not returned in response');
            }
            
            // ============================================
            // STEP 10: Redirect User to Payment Page
            // ============================================
            $billCode = $result[0]['BillCode'];
            
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
            
            // Show error message
            $errors[] = 'Ralat semasa memproses checkout: ' . htmlspecialchars($errorMessage);
        }
    }
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
            max-width: 1000px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .checkout-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
        }
        
        @media (max-width: 768px) {
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
        }
        
        .order-total-row:last-child {
            font-size: 24px;
            font-weight: 700;
            color: #1a202c;
            margin-top: 10px;
            padding-top: 10px;
            border-top: 2px solid #e5e7eb;
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
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .form-group textarea {
            resize: vertical;
            min-height: 100px;
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
        
        <h1 style="text-align: center; margin-bottom: 30px; color: #1a202c;">Checkout</h1>
        
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
            <div class="checkout-grid">
                <!-- Order Summary -->
                <div class="checkout-section">
                    <h2>Order Summary</h2>
                    
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
                    
                    <div class="order-total">
                        <div class="order-total-row">
                            <span>Jumlah Item:</span>
                            <span><?php echo $cartCount; ?> item(s)</span>
                        </div>
                        <div class="order-total-row">
                            <span>Jumlah Keseluruhan:</span>
                            <span>RM <?php echo number_format($cartTotal, 2); ?></span>
                        </div>
                    </div>
                </div>
                
                <!-- Customer Information -->
                <div class="checkout-section">
                    <h2>Maklumat Pelanggan</h2>
                    
                    <div class="form-group required">
                        <label for="name">Nama Penuh</label>
                        <input type="text" name="name" id="name" 
                               value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>" 
                               required>
                    </div>
                    
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
                    
                    <div class="form-group required">
                        <label for="address">Alamat</label>
                        <textarea name="address" id="address" 
                                  required><?php echo isset($_POST['address']) ? htmlspecialchars($_POST['address']) : ''; ?></textarea>
                    </div>
                    
                    <button type="submit" class="submit-btn">Proceed to Payment</button>
                </div>
            </div>
        </form>
    </div>
    
    <script>
        // Form validation
        document.getElementById('checkoutForm').addEventListener('submit', function(e) {
            const name = document.getElementById('name').value.trim();
            const email = document.getElementById('email').value.trim();
            const phone = document.getElementById('phone').value.trim();
            const address = document.getElementById('address').value.trim();
            
            if (!name || !email || !phone || !address) {
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
            
            // Show loading state
            const submitBtn = document.querySelector('.submit-btn');
            submitBtn.disabled = true;
            submitBtn.textContent = 'Memproses...';
        });
    </script>
</body>
</html>

