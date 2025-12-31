<?php
/**
 * E-Commerce Product Page with Shopping Cart
 * User can select product variant, size, quantity and add to cart
 * Session-based cart with popup display
 */

// ============================================
// STEP 1: Start Session
// ============================================
session_start();

// Initialize cart if not exists
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
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

// Auto-create orders table if not exists
function createOrdersTableIfNotExists() {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->query("SHOW TABLES LIKE 'orders'");
        if ($stmt->rowCount() > 0) {
            return true;
        }
        
        $sql = "CREATE TABLE IF NOT EXISTS `orders` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `name` VARCHAR(255) NOT NULL COMMENT 'Nama user (billTo) - backward compatibility',
            `fullname` VARCHAR(255) NULL COMMENT 'Full name of customer',
            `email` VARCHAR(255) NOT NULL COMMENT 'Email user (billEmail)',
            `phone` VARCHAR(50) NOT NULL COMMENT 'Phone user (billPhone)',
            `address1` VARCHAR(255) NULL COMMENT 'Address Line 1',
            `address2` VARCHAR(255) NULL COMMENT 'Address Line 2 (optional)',
            `address3` VARCHAR(255) NULL COMMENT 'Address Line 3 (optional)',
            `state` VARCHAR(100) NULL COMMENT 'State in Malaysia',
            `district` VARCHAR(100) NULL COMMENT 'District/City',
            `postcode` VARCHAR(10) NULL COMMENT 'Postcode (5 digits)',
            `variant` VARCHAR(50) NOT NULL COMMENT 'Variant: hitamdepan, hijaufront, merahdepan',
            `size` VARCHAR(10) NOT NULL COMMENT 'Size: S, M, L, XL, XXL',
            `quantity` INT(11) NOT NULL DEFAULT 1 COMMENT 'Quantity ordered',
            `amount` DECIMAL(10,2) NOT NULL COMMENT 'Total amount in RM',
            `promo_code` VARCHAR(50) NULL COMMENT 'Promotion code applied',
            `discount_amount` DECIMAL(10,2) NULL DEFAULT 0.00 COMMENT 'Discount amount applied',
            `billExternalReferenceNo` VARCHAR(255) NOT NULL COMMENT 'Unique reference number untuk ToyyibPay',
            `status` VARCHAR(50) NOT NULL DEFAULT 'pending' COMMENT 'Order status: pending, paid, failed',
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Order creation timestamp',
            PRIMARY KEY (`id`),
            INDEX `idx_billExternalReferenceNo` (`billExternalReferenceNo`),
            INDEX `idx_status` (`status`),
            INDEX `idx_created_at` (`created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Orders table for ToyyibPay staging payment'";
        
        $pdo->exec($sql);
        error_log('Orders table created successfully');
        return true;
    } catch (PDOException $e) {
        error_log('Error creating orders table: ' . $e->getMessage());
        return false;
    }
}

createOrdersTableIfNotExists();

// Product Configuration
define('PRODUCT_NAME', 'Baju Premium');
define('PRICE_PER_SHIRT', 50.00); // RM50 per baju

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
        'name' => 'Merah Depan',
        'image' => 'images/merahdepan.svg'
    ]
];

// Size Options
$sizes = ['S', 'M', 'L', 'XL', 'XXL'];

// ============================================
// STEP 3: Handle Add to Cart
// ============================================
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_to_cart') {
    // Validate input
    $variant = $_POST['variant'] ?? '';
    $size = $_POST['size'] ?? '';
    $quantity = intval($_POST['quantity'] ?? 0);
    
    // Validation
    if (empty($variant) || !isset($variants[$variant])) {
        $message = 'Sila pilih variant';
        $messageType = 'error';
    } elseif (empty($size) || !in_array($size, $sizes)) {
        $message = 'Sila pilih saiz';
        $messageType = 'error';
    } elseif ($quantity < 1) {
        $message = 'Kuantiti mestilah sekurang-kurangnya 1';
        $messageType = 'error';
    } else {
        // Calculate item total
        $itemTotal = PRICE_PER_SHIRT * $quantity;
        
        // Create cart item
        $cartItem = [
            'id' => uniqid('item_'),
            'variant' => $variant,
            'variant_name' => $variants[$variant]['name'],
            'variant_image' => $variants[$variant]['image'],
            'size' => $size,
            'quantity' => $quantity,
            'price' => PRICE_PER_SHIRT,
            'subtotal' => $itemTotal
        ];
        
        // Add to cart
        $_SESSION['cart'][] = $cartItem;
        
        $message = 'Item berjaya ditambah ke cart!';
        $messageType = 'success';
    }
}

// ============================================
// STEP 4: Handle Remove from Cart (AJAX)
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'remove_from_cart') {
    $itemId = $_POST['item_id'] ?? '';
    
    if (!empty($itemId) && isset($_SESSION['cart'])) {
        $_SESSION['cart'] = array_filter($_SESSION['cart'], function($item) use ($itemId) {
            return $item['id'] !== $itemId;
        });
        $_SESSION['cart'] = array_values($_SESSION['cart']); // Re-index array
        
        // Return JSON response for AJAX
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'cart_count' => count($_SESSION['cart'])]);
        exit;
    }
}

// ============================================
// STEP 5: Calculate Cart Totals
// ============================================
$cartCount = count($_SESSION['cart']);
$cartTotal = 0;
foreach ($_SESSION['cart'] as $item) {
    $cartTotal += $item['subtotal'];
}
?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Baju Premium</title>
    <link rel="stylesheet" href="css/order-baju.css">
    <style>
        /* Cart Button Styles */
        .cart-button {
            position: fixed;
            bottom: 30px;
            right: 30px;
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 20px;
            font-weight: bold;
            cursor: pointer;
            box-shadow: 0 4px 20px rgba(102, 126, 234, 0.4);
            z-index: 1000;
            transition: transform 0.3s, box-shadow 0.3s;
        }
        
        .cart-button:hover {
            transform: scale(1.1);
            box-shadow: 0 6px 25px rgba(102, 126, 234, 0.6);
        }
        
        .cart-button .badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background: #ef4444;
            color: white;
            border-radius: 50%;
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: bold;
        }
        
        /* Cart Popup Styles */
        .cart-popup {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 2000;
            justify-content: center;
            align-items: center;
        }
        
        .cart-popup.active {
            display: flex;
        }
        
        .cart-popup-content {
            background: white;
            border-radius: 12px;
            width: 90%;
            max-width: 600px;
            max-height: 80vh;
            overflow-y: auto;
            padding: 30px;
            position: relative;
            animation: slideUp 0.3s ease-out;
        }
        
        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .cart-popup-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #e5e7eb;
        }
        
        .cart-popup-header h2 {
            margin: 0;
            color: #1a202c;
        }
        
        .close-cart {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #6b7280;
            padding: 0;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .close-cart:hover {
            color: #1a202c;
        }
        
        .cart-items {
            margin-bottom: 20px;
        }
        
        .cart-item {
            display: flex;
            align-items: center;
            padding: 15px;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            margin-bottom: 10px;
        }
        
        .cart-item-image {
            width: 60px;
            height: 60px;
            object-fit: contain;
            margin-right: 15px;
            border-radius: 4px;
        }
        
        .cart-item-details {
            flex: 1;
        }
        
        .cart-item-details h4 {
            margin: 0 0 5px 0;
            font-size: 16px;
            color: #1a202c;
        }
        
        .cart-item-details p {
            margin: 0;
            font-size: 14px;
            color: #6b7280;
        }
        
        .cart-item-price {
            font-weight: 600;
            color: #1a202c;
            margin-right: 15px;
        }
        
        .remove-item {
            background: #ef4444;
            color: white;
            border: none;
            border-radius: 4px;
            padding: 5px 10px;
            cursor: pointer;
            font-size: 12px;
        }
        
        .remove-item:hover {
            background: #dc2626;
        }
        
        .cart-total {
            padding: 20px;
            background: #f9fafb;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .cart-total-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
        }
        
        .cart-total-row:last-child {
            margin-bottom: 0;
            font-size: 20px;
            font-weight: 700;
            color: #1a202c;
            padding-top: 10px;
            border-top: 2px solid #e5e7eb;
        }
        
        .checkout-btn {
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
        }
        
        .checkout-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
        }
        
        .checkout-btn:disabled {
            background: #9ca3af;
            cursor: not-allowed;
            transform: none;
        }
        
        .empty-cart {
            text-align: center;
            padding: 40px 20px;
            color: #6b7280;
        }
        
        .message {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .message.success {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #10b981;
        }
        
        .message.error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #ef4444;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><?php echo htmlspecialchars(PRODUCT_NAME); ?></h1>
            <div class="price">RM <?php echo number_format(PRICE_PER_SHIRT, 2); ?> sehelai</div>
        </div>
        
        <div class="form-container">
            <?php if ($message): ?>
                <div class="message <?php echo $messageType; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="" id="orderForm">
                <input type="hidden" name="action" value="add_to_cart">
                
                <!-- Variant Selection -->
                <div class="form-group required">
                    <label>Pilih Variant</label>
                    <div class="variant-options">
                        <?php foreach ($variants as $key => $variant): ?>
                            <label class="variant-option">
                                <input type="radio" name="variant" value="<?php echo htmlspecialchars($key); ?>" 
                                       data-image="<?php echo htmlspecialchars($variant['image']); ?>"
                                       required>
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
                            <option value="<?php echo htmlspecialchars($s); ?>">
                                <?php echo htmlspecialchars($s); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <!-- Quantity -->
                <div class="form-group required">
                    <label for="quantity">Kuantiti</label>
                    <input type="number" name="quantity" id="quantity" 
                           min="1" value="1" required>
                </div>
                
                <!-- Total Amount Display -->
                <div class="form-group">
                    <div style="background: #f0f4ff; padding: 15px; border-radius: 8px; text-align: center;">
                        <strong style="font-size: 18px;">Harga: RM <span id="totalAmount"><?php echo number_format(PRICE_PER_SHIRT, 2); ?></span></strong>
                    </div>
                </div>
                
                <!-- Add to Cart Button -->
                <button type="submit" class="submit-btn">Tambah ke Cart</button>
            </form>
        </div>
    </div>
    
    <!-- Floating Cart Button -->
    <div class="cart-button" id="cartButton">
        🛒
        <?php if ($cartCount > 0): ?>
            <span class="badge" id="cartBadge"><?php echo $cartCount; ?></span>
        <?php endif; ?>
    </div>
    
    <!-- Cart Popup -->
    <div class="cart-popup" id="cartPopup">
        <div class="cart-popup-content">
            <div class="cart-popup-header">
                <h2>Shopping Cart</h2>
                <button class="close-cart" id="closeCart">&times;</button>
            </div>
            
            <?php if (empty($_SESSION['cart'])): ?>
                <div class="empty-cart">
                    <p>Cart anda kosong</p>
                </div>
            <?php else: ?>
                <div class="cart-items" id="cartItems">
                    <?php foreach ($_SESSION['cart'] as $item): ?>
                        <div class="cart-item" data-item-id="<?php echo htmlspecialchars($item['id']); ?>">
                            <img src="<?php echo htmlspecialchars($item['variant_image']); ?>" 
                                 alt="<?php echo htmlspecialchars($item['variant_name']); ?>"
                                 class="cart-item-image">
                            <div class="cart-item-details">
                                <h4><?php echo htmlspecialchars($item['variant_name']); ?></h4>
                                <p>Saiz: <?php echo htmlspecialchars($item['size']); ?> | Kuantiti: <?php echo $item['quantity']; ?></p>
                            </div>
                            <div class="cart-item-price">
                                RM <?php echo number_format($item['subtotal'], 2); ?>
                            </div>
                            <button class="remove-item" onclick="removeFromCart('<?php echo htmlspecialchars($item['id']); ?>')">Remove</button>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <div class="cart-total">
                    <div class="cart-total-row">
                        <span>Jumlah Item:</span>
                        <span><?php echo $cartCount; ?> item(s)</span>
                    </div>
                    <div class="cart-total-row">
                        <span>Jumlah Keseluruhan:</span>
                        <span>RM <?php echo number_format($cartTotal, 2); ?></span>
                    </div>
                </div>
                
                <a href="checkout.php" class="checkout-btn">Go to Checkout</a>
            <?php endif; ?>
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
            
            // Calculate total amount
            function updateTotal() {
                const quantity = parseInt(quantityInput.value) || 1;
                const total = pricePerShirt * quantity;
                totalAmountSpan.textContent = total.toFixed(2);
            }
            
            quantityInput.addEventListener('input', updateTotal);
            updateTotal();
            
            // ============================================
            // Cart Popup Functionality
            // ============================================
            const cartButton = document.getElementById('cartButton');
            const cartPopup = document.getElementById('cartPopup');
            const closeCart = document.getElementById('closeCart');
            
            cartButton.addEventListener('click', function() {
                cartPopup.classList.add('active');
            });
            
            closeCart.addEventListener('click', function() {
                cartPopup.classList.remove('active');
            });
            
            cartPopup.addEventListener('click', function(e) {
                if (e.target === cartPopup) {
                    cartPopup.classList.remove('active');
                }
            });
        });
        
        // ============================================
        // Remove Item from Cart (AJAX)
        // ============================================
        function removeFromCart(itemId) {
            if (!confirm('Adakah anda pasti mahu membuang item ini?')) {
                return;
            }
            
            const formData = new FormData();
            formData.append('action', 'remove_from_cart');
            formData.append('item_id', itemId);
            
            fetch('order-baju.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Remove item from DOM
                    const itemElement = document.querySelector(`[data-item-id="${itemId}"]`);
                    if (itemElement) {
                        itemElement.remove();
                    }
                    
                    // Update cart badge
                    const badge = document.getElementById('cartBadge');
                    if (data.cart_count > 0) {
                        if (badge) {
                            badge.textContent = data.cart_count;
                        } else {
                            const cartButton = document.getElementById('cartButton');
                            const newBadge = document.createElement('span');
                            newBadge.className = 'badge';
                            newBadge.id = 'cartBadge';
                            newBadge.textContent = data.cart_count;
                            cartButton.appendChild(newBadge);
                        }
                    } else {
                        if (badge) badge.remove();
                        // Reload page to show empty cart message
                        location.reload();
                    }
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Ralat semasa membuang item. Sila cuba lagi.');
            });
        }
    </script>
</body>
</html>
