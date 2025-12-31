<?php
/**
 * ToyyibPay Staging - Order Page untuk Baju Premium
 * Single product dengan 3 variant warna/design dan size S-XXL
 */

// ============================================
// STEP 1: Configuration & Constants
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

// Base URL untuk staging environment (gantikan dengan domain sebenar)
$baseUrl = 'https://staging.yourdomain.com'; // GANTIKAN dengan domain staging sebenar

// Product Configuration
define('PRODUCT_NAME', 'Baju Premium');
define('PRICE_PER_SHIRT', 50.00); // RM50 per baju (boleh adjust)

// Variant Configuration
$variants = [
    'hitamdepan' => [
        'name' => 'Hitam Depan',
        'image' => 'images/hitamdepan.svg'
    ],
    'hijaufront' => [
        'name' => 'Hijau Front',
        'image' => 'images/hijaufront.svg'
    ],
    'hijaudepan' => [
        'name' => 'Hijau Depan',
        'image' => 'images/hijaufront.svg' // Using hijaufront as fallback if hijaudepan doesn't exist
    ]
];

// Size Options
$sizes = ['S', 'M', 'L', 'XL', 'XXL'];

// ============================================
// STEP 2: Handle Form Submission
// ============================================
$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate required fields
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $variant = $_POST['variant'] ?? '';
    $size = $_POST['size'] ?? '';
    $quantity = intval($_POST['quantity'] ?? 0);
    
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
    
    if (empty($variant) || !isset($variants[$variant])) {
        $errors[] = 'Sila pilih variant';
    }
    
    if (empty($size) || !in_array($size, $sizes)) {
        $errors[] = 'Sila pilih saiz';
    }
    
    if ($quantity < 1) {
        $errors[] = 'Kuantiti mestilah sekurang-kurangnya 1';
    }
    
    // If no errors, process order
    if (empty($errors)) {
        try {
            // ============================================
            // STEP 3: Calculate Total Amount
            // ============================================
            $amount = PRICE_PER_SHIRT * $quantity;
            
            // ============================================
            // STEP 4: Generate Unique Bill Reference
            // ============================================
            $billExternalReferenceNo = 'BILL-' . time() . '-' . uniqid();
            
            // ============================================
            // STEP 5: Save Order to Database
            // ============================================
            $pdo = getDBConnection();
            
            $stmt = $pdo->prepare("
                INSERT INTO orders (
                    name, email, phone, variant, size, quantity, 
                    amount, billExternalReferenceNo, status, created_at
                ) VALUES (
                    :name, :email, :phone, :variant, :size, :quantity,
                    :amount, :billExternalReferenceNo, 'pending', NOW()
                )
            ");
            
            $stmt->execute([
                ':name' => $name,
                ':email' => $email,
                ':phone' => $phone,
                ':variant' => $variant,
                ':size' => $size,
                ':quantity' => $quantity,
                ':amount' => $amount,
                ':billExternalReferenceNo' => $billExternalReferenceNo
            ]);
            
            // ============================================
            // STEP 6: Prepare Bill Data for ToyyibPay
            // ============================================
            // Convert RM to sen (RM50.00 = 5000 sen)
            $billAmount = (int)($amount * 100);
            
            // Build description with order details
            $billDescription = sprintf(
                "Baju Premium - Variant: %s, Size: %s, Quantity: %d",
                $variants[$variant]['name'],
                $size,
                $quantity
            );
            
            $billData = [
                // Required: ToyyibPay credentials
                'userSecretKey' => STAGING_SECRET_KEY,
                'categoryCode' => STAGING_CATEGORY_CODE,
                
                // Bill information
                'billName' => PRODUCT_NAME,
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
            
            // ============================================
            // STEP 7: Call ToyyibPay API to Create Bill
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
            // STEP 8: Handle API Response & Error Handling
            // ============================================
            if ($curlError) {
                throw new Exception('cURL request failed: ' . $curlError);
            }
            
            if ($httpCode !== 200) {
                throw new Exception('HTTP request failed with status code ' . $httpCode);
            }
            
            // Decode JSON response
            $result = json_decode($response, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('Invalid JSON response: ' . json_last_error_msg());
            }
            
            // Check for API error response
            if (isset($result[0]['status']) && $result[0]['status'] === 'error') {
                $errorMsg = isset($result[0]['msg']) ? $result[0]['msg'] : 'Unknown error';
                throw new Exception('ToyyibPay API Error: ' . $errorMsg);
            }
            
            // Check if response contains BillCode
            if (!isset($result[0]['BillCode']) || empty($result[0]['BillCode'])) {
                throw new Exception('BillCode not returned in response');
            }
            
            // ============================================
            // STEP 9: Redirect User to Payment Page
            // ============================================
            $billCode = $result[0]['BillCode'];
            header('Location: https://dev.toyyibpay.com/' . $billCode);
            exit;
            
        } catch (Exception $e) {
            // Log error for debugging
            error_log('Order processing error: ' . $e->getMessage());
            $errors[] = 'Ralat semasa memproses pesanan. Sila cuba lagi atau hubungi kami.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Baju Premium - ToyyibPay Staging</title>
    <link rel="stylesheet" href="css/order-baju.css">
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><?php echo htmlspecialchars(PRODUCT_NAME); ?></h1>
            <div class="price">RM <?php echo number_format(PRICE_PER_SHIRT, 2); ?> sehelai</div>
        </div>
        
        <div class="form-container">
            <?php if (!empty($errors)): ?>
                <div class="errors">
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="" id="orderForm">
                <!-- Variant Selection -->
                <div class="form-group required">
                    <label>Pilih Variant</label>
                    <div class="variant-options">
                        <?php foreach ($variants as $key => $variant): ?>
                            <label class="variant-option">
                                <input type="radio" name="variant" value="<?php echo htmlspecialchars($key); ?>" 
                                       data-image="<?php echo htmlspecialchars($variant['image']); ?>"
                                       <?php echo (isset($_POST['variant']) && $_POST['variant'] === $key) ? 'checked' : ''; ?>>
                                <div class="variant-content">
                                    <div class="variant-image-container">
                                        <img src="<?php echo htmlspecialchars($variant['image']); ?>" 
                                             alt="<?php echo htmlspecialchars($variant['name']); ?>"
                                             class="variant-image">
                                    </div>
                                    <div class="variant-name"><?php echo htmlspecialchars($variant['name']); ?></div>
                                </div>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <!-- Preview Section -->
                <div class="preview-section">
                    <div class="preview-image" id="previewImage">
                        <img src="" alt="Preview" id="previewImg">
                    </div>
                    <div id="previewText">Pilih variant untuk melihat preview</div>
                </div>
                
                <!-- Size Selection -->
                <div class="form-group required">
                    <label for="size">Saiz</label>
                    <select name="size" id="size" required>
                        <option value="">-- Pilih Saiz --</option>
                        <?php foreach ($sizes as $s): ?>
                            <option value="<?php echo htmlspecialchars($s); ?>"
                                    <?php echo (isset($_POST['size']) && $_POST['size'] === $s) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($s); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <!-- Quantity -->
                <div class="form-group required">
                    <label for="quantity">Kuantiti</label>
                    <input type="number" name="quantity" id="quantity" 
                           min="1" value="<?php echo isset($_POST['quantity']) ? htmlspecialchars($_POST['quantity']) : '1'; ?>" 
                           required>
                </div>
                
                <!-- User Information -->
                <div class="form-group required">
                    <label for="name">Nama</label>
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
                
                <!-- Total Amount Display -->
                <div class="form-group">
                    <div style="background: #f0f4ff; padding: 15px; border-radius: 8px; text-align: center;">
                        <strong style="font-size: 18px;">Jumlah: RM <span id="totalAmount"><?php echo number_format(PRICE_PER_SHIRT, 2); ?></span></strong>
                    </div>
                </div>
                
                <!-- Submit Button -->
                <button type="submit" class="submit-btn">Buat Pesanan & Bayar</button>
            </form>
        </div>
    </div>
    
    <script>
        // ============================================
        // JavaScript: Variant Image Preview
        // ============================================
        document.addEventListener('DOMContentLoaded', function() {
            const variantRadios = document.querySelectorAll('input[name="variant"]');
            const previewImage = document.getElementById('previewImage');
            const previewImg = document.getElementById('previewImg');
            const previewText = document.getElementById('previewText');
            const quantityInput = document.getElementById('quantity');
            const totalAmountSpan = document.getElementById('totalAmount');
            const pricePerShirt = <?php echo PRICE_PER_SHIRT; ?>;
            
            // Handle variant selection
            variantRadios.forEach(radio => {
                radio.addEventListener('change', function() {
                    if (this.checked) {
                        const imageSrc = this.getAttribute('data-image');
                        previewImg.src = imageSrc;
                        previewImage.classList.add('active');
                        previewText.textContent = 'Preview: ' + this.closest('.variant-option').querySelector('.variant-name').textContent;
                    }
                });
            });
            
            // Show preview if variant already selected
            const checkedVariant = document.querySelector('input[name="variant"]:checked');
            if (checkedVariant) {
                const imageSrc = checkedVariant.getAttribute('data-image');
                previewImg.src = imageSrc;
                previewImage.classList.add('active');
                previewText.textContent = 'Preview: ' + checkedVariant.closest('.variant-option').querySelector('.variant-name').textContent;
            }
            
            // Calculate total amount
            function updateTotal() {
                const quantity = parseInt(quantityInput.value) || 1;
                const total = pricePerShirt * quantity;
                totalAmountSpan.textContent = total.toFixed(2);
            }
            
            quantityInput.addEventListener('input', updateTotal);
            updateTotal();
        });
    </script>
</body>
</html>

