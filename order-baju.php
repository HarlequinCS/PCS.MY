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

// Include version configuration for asset versioning
require_once __DIR__ . '/config/version.php';

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
define('PRODUCT_NAME', 'Japanese Drill Half-Zip Workshirt');
define('PRICE_PER_SHIRT', 50.00); // RM50 per baju

// Variant Configuration
$variants = [
    'hitamdepan' => [
        'name' => 'Hitam',
        'image' => 'images/hitamdepan.svg',
        'color' => '#1a1a1a'
    ],
    'hijaufront' => [
        'name' => 'Hijau',
        'image' => 'images/hijaufront.svg',
        'color' => '#2d5016'
    ],
    'merahdepan' => [
        'name' => 'Merah',
        'image' => 'images/merahdepan.svg',
        'color' => '#8b0000'
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
        $message = 'Please select a color';
        $messageType = 'error';
    } elseif (empty($size) || !in_array($size, $sizes)) {
        $message = 'Please select a size';
        $messageType = 'error';
    } elseif ($quantity < 1) {
        $message = 'Quantity must be at least 1';
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
        
        $message = 'Item successfully added to cart!';
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">
    <title>Order - <?php echo htmlspecialchars(PRODUCT_NAME); ?> | Projek Cuti Sem</title>
    <link rel="icon" type="image/x-icon" href="https://projectcutisem.com/favicon.ico">
    <link rel="shortcut icon" type="image/x-icon" href="https://projectcutisem.com/favicon.ico">
    <link rel="apple-touch-icon" href="https://projectcutisem.com/apple-touch-icon.png">
    <link rel="stylesheet" href="<?php echo getVersionedAsset('css/order-baju.css'); ?>">
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
            <a href="/" class="nav-back-link">← Back to Home</a>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="order-main">
        <div class="order-container">
            <!-- Two Column Layout -->
            <div class="order-layout">
                <!-- Left Column: Product Visual -->
                <div class="product-visual-column">
                    <div class="product-preview">
                        <div class="product-image-wrapper" id="productImageWrapper">
                            <img src="" alt="Product Preview" id="mainProductImage" class="main-product-image">
                            <div class="image-placeholder" id="imagePlaceholder">
                                <span>Select a color to see preview</span>
                            </div>
                        </div>
                        <div class="product-zoom-hint" id="zoomHint" style="display: none;">
                            <span>Click to zoom</span>
                        </div>
                    </div>
                </div>

                <!-- Right Column: Order Options -->
                <div class="order-options-column">
                    <div class="order-content">
                        <!-- Product Title & Price -->
                        <div class="product-header">
                            <h1 class="product-title"><?php echo htmlspecialchars(PRODUCT_NAME); ?></h1>
                            <div class="product-price">RM <?php echo number_format(PRICE_PER_SHIRT, 2); ?></div>
                        </div>

                        <!-- Message Display -->
                        <?php if ($message): ?>
                            <div class="order-message order-message--<?php echo $messageType; ?>" id="orderMessage">
                                <?php echo htmlspecialchars($message); ?>
                            </div>
                        <?php endif; ?>

                        <!-- Order Form -->
                        <form method="POST" action="" id="orderForm" class="order-form">
                            <input type="hidden" name="action" value="add_to_cart">
                            
                            <!-- Variant Selection -->
                            <div class="form-section">
                                <label class="form-label">
                                    Select Color
                                    <span class="required-asterisk">*</span>
                                </label>
                                <div class="variant-selector" id="variantSelector">
                                    <?php foreach ($variants as $key => $variant): ?>
                                        <label class="variant-card" data-variant="<?php echo htmlspecialchars($key); ?>">
                                            <input type="radio" name="variant" value="<?php echo htmlspecialchars($key); ?>" 
                                                   data-image="<?php echo htmlspecialchars($variant['image']); ?>"
                                                   required>
                                            <div class="variant-card-content">
                                                <div class="variant-thumbnail">
                                                    <img src="<?php echo htmlspecialchars($variant['image']); ?>" 
                                                         alt="<?php echo htmlspecialchars($variant['name']); ?>"
                                                         loading="lazy">
                                                </div>
                                                <div class="variant-label">
                                                    <?php echo htmlspecialchars($variant['name']); ?>
                                                </div>
                                                <div class="variant-check">
                                                    <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
                                                        <path d="M16.667 5L7.5 14.167 3.333 10" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                                    </svg>
                                                </div>
                                            </div>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <!-- Size Selection -->
                            <div class="form-section">
                                <div class="form-label-row">
                                    <label class="form-label">
                                        Size
                                        <span class="required-asterisk">*</span>
                                    </label>
                                    <button type="button" class="size-chart-link" id="sizeChartBtn">Size Chart</button>
                                </div>
                                <div class="size-selector" id="sizeSelector">
                                    <?php foreach ($sizes as $size): ?>
                                        <label class="size-option">
                                            <input type="radio" name="size" value="<?php echo htmlspecialchars($size); ?>" required>
                                            <span class="size-label"><?php echo htmlspecialchars($size); ?></span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <!-- Quantity Selection -->
                            <div class="form-section">
                                <label class="form-label">
                                    Quantity
                                    <span class="required-asterisk">*</span>
                                </label>
                                <div class="quantity-selector">
                                    <button type="button" class="quantity-btn quantity-btn--decrease" id="quantityDecrease">−</button>
                                    <input type="number" name="quantity" id="quantityInput" value="1" min="1" max="10" required readonly>
                                    <button type="button" class="quantity-btn quantity-btn--increase" id="quantityIncrease">+</button>
                                </div>
                            </div>

                            <!-- Total Price Display -->
                            <div class="price-summary">
                                <div class="price-row">
                                    <span>Price Per Unit:</span>
                                    <span>RM <?php echo number_format(PRICE_PER_SHIRT, 2); ?></span>
                                </div>
                                <div class="price-row price-row--total">
                                    <span>Total:</span>
                                    <span class="total-price">RM <span id="totalPriceDisplay"><?php echo number_format(PRICE_PER_SHIRT, 2); ?></span></span>
                                </div>
                            </div>

                            <!-- Add to Cart Button -->
                            <button type="submit" class="add-to-cart-btn" id="addToCartBtn">
                                <span class="btn-text">Add to Cart</span>
                                <span class="btn-loader" style="display: none;">
                                    <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
                                        <circle cx="10" cy="10" r="8" stroke="currentColor" stroke-width="2" stroke-dasharray="31.416" stroke-dashoffset="31.416">
                                            <animate attributeName="stroke-dasharray" dur="2s" values="0 31.416;15.708 15.708;0 31.416;0 31.416" repeatCount="indefinite"/>
                                            <animate attributeName="stroke-dashoffset" dur="2s" values="0;-15.708;-31.416;-31.416" repeatCount="indefinite"/>
                                        </circle>
                                    </svg>
                                </span>
                            </button>
                        </form>

                        <!-- Product Details -->
                        <div class="product-details">
                            <div class="detail-item">
                                <strong>Fabric:</strong> Heavyweight Japanese Drill
                            </div>
                            <div class="detail-item">
                                <strong>Fit:</strong> Relaxed Workwear Fit
                            </div>
                            <div class="detail-item">
                                <strong>Made in:</strong> Malaysia
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Size Chart Modal -->
    <div class="size-chart-modal" id="sizeChartModal">
        <div class="size-chart-overlay"></div>
        <div class="size-chart-content">
            <div class="size-chart-header">
                <h2>Size Chart</h2>
                <button class="size-chart-close" id="sizeChartClose">&times;</button>
            </div>
            <div class="size-chart-table-wrapper">
                <table class="size-chart-table">
                    <thead>
                        <tr>
                            <th>Size</th>
                            <th>Chest (cm)</th>
                            <th>Length (cm)</th>
                            <th>Shoulder (cm)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><strong>S</strong></td>
                            <td>98-102</td>
                            <td>68</td>
                            <td>45</td>
                        </tr>
                        <tr>
                            <td><strong>M</strong></td>
                            <td>106-110</td>
                            <td>70</td>
                            <td>47</td>
                        </tr>
                        <tr>
                            <td><strong>L</strong></td>
                            <td>114-118</td>
                            <td>72</td>
                            <td>49</td>
                        </tr>
                        <tr>
                            <td><strong>XL</strong></td>
                            <td>122-126</td>
                            <td>74</td>
                            <td>51</td>
                        </tr>
                        <tr>
                            <td><strong>XXL</strong></td>
                            <td>130-134</td>
                            <td>76</td>
                            <td>53</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="size-chart-note">
                <p><strong>Note:</strong> Measurements are in centimeters. For best fit, measure your chest at the fullest point and compare with the chart above.</p>
            </div>
        </div>
    </div>

    <!-- Image Zoom Modal -->
    <div class="image-zoom-modal" id="imageZoomModal">
        <div class="image-zoom-overlay"></div>
        <div class="image-zoom-content">
            <button class="image-zoom-close" id="imageZoomClose">&times;</button>
            <img src="" alt="Zoomed Product Image" id="zoomedImage" class="zoomed-image">
        </div>
    </div>

    <!-- Floating Cart Button -->
    <div class="cart-fab" id="cartFab">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M9 2L7 6M17 2l2 4M3 10h18l-2 8H5l-2-8zM10 18a1 1 0 11-2 0 1 1 0 012 0zM20 18a1 1 0 11-2 0 1 1 0 012 0z"/>
        </svg>
        <?php if ($cartCount > 0): ?>
            <span class="cart-fab-badge" id="cartFabBadge"><?php echo $cartCount; ?></span>
        <?php endif; ?>
    </div>

    <!-- Cart Popup -->
    <div class="cart-popup" id="cartPopup">
        <div class="cart-popup-overlay"></div>
        <div class="cart-popup-content">
            <div class="cart-popup-header">
                <h2>Shopping Cart</h2>
                <button class="cart-popup-close" id="cartPopupClose">&times;</button>
            </div>
            
            <?php if (empty($_SESSION['cart'])): ?>
                <div class="cart-empty">
                    <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" opacity="0.3">
                        <path d="M9 2L7 6M17 2l2 4M3 10h18l-2 8H5l-2-8zM10 18a1 1 0 11-2 0 1 1 0 012 0zM20 18a1 1 0 11-2 0 1 1 0 012 0z"/>
                    </svg>
                    <p>Your cart is empty</p>
                </div>
            <?php else: ?>
                <div class="cart-items-list" id="cartItemsList">
                    <?php foreach ($_SESSION['cart'] as $item): ?>
                        <div class="cart-item-card" data-item-id="<?php echo htmlspecialchars($item['id']); ?>">
                            <div class="cart-item-image-wrapper">
                                <img src="<?php echo htmlspecialchars($item['variant_image']); ?>" 
                                     alt="<?php echo htmlspecialchars($item['variant_name']); ?>"
                                     class="cart-item-image">
                            </div>
                            <div class="cart-item-info">
                                <h4 class="cart-item-title"><?php echo htmlspecialchars($item['variant_name']); ?></h4>
                                <p class="cart-item-meta">Size: <?php echo htmlspecialchars($item['size']); ?> • Qty: <?php echo $item['quantity']; ?></p>
                                <div class="cart-item-price">RM <?php echo number_format($item['subtotal'], 2); ?></div>
                            </div>
                            <button class="cart-item-remove" onclick="removeFromCart('<?php echo htmlspecialchars($item['id']); ?>')" aria-label="Remove item">
                                <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
                                    <path d="M15 5L5 15M5 5l10 10" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                </svg>
                            </button>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <div class="cart-summary">
                    <div class="cart-summary-row">
                        <span>Jumlah Item:</span>
                        <span><?php echo $cartCount; ?> item(s)</span>
                    </div>
                    <div class="cart-summary-row cart-summary-total">
                        <span>Jumlah Keseluruhan:</span>
                        <span class="cart-total-amount">RM <?php echo number_format($cartTotal, 2); ?></span>
                    </div>
                </div>
                
                <a href="checkout.php" class="cart-checkout-btn">Go to Checkout</a>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Constants
        const PRICE_PER_SHIRT = <?php echo PRICE_PER_SHIRT; ?>;
        
        // DOM Elements
        const orderForm = document.getElementById('orderForm');
        const quantityInput = document.getElementById('quantityInput');
        const quantityDecrease = document.getElementById('quantityDecrease');
        const quantityIncrease = document.getElementById('quantityIncrease');
        const totalPriceDisplay = document.getElementById('totalPriceDisplay');
        const variantRadios = document.querySelectorAll('input[name="variant"]');
        const mainProductImage = document.getElementById('mainProductImage');
        const imagePlaceholder = document.getElementById('imagePlaceholder');
        const productImageWrapper = document.getElementById('productImageWrapper');
        const addToCartBtn = document.getElementById('addToCartBtn');
        const btnText = addToCartBtn.querySelector('.btn-text');
        const btnLoader = addToCartBtn.querySelector('.btn-loader');
        
        // Cart Elements
        const cartFab = document.getElementById('cartFab');
        const cartPopup = document.getElementById('cartPopup');
        const cartPopupClose = document.getElementById('cartPopupClose');
        const cartFabBadge = document.getElementById('cartFabBadge');
        
        // Size Chart Modal
        const sizeChartBtn = document.getElementById('sizeChartBtn');
        const sizeChartModal = document.getElementById('sizeChartModal');
        const sizeChartClose = document.getElementById('sizeChartClose');
        
        // Image Zoom Modal
        const imageZoomModal = document.getElementById('imageZoomModal');
        const imageZoomClose = document.getElementById('imageZoomClose');
        const zoomedImage = document.getElementById('zoomedImage');
        const zoomHint = document.getElementById('zoomHint');

        // Update Total Price
        function updateTotalPrice() {
            const quantity = parseInt(quantityInput.value) || 1;
            const total = PRICE_PER_SHIRT * quantity;
            totalPriceDisplay.textContent = total.toFixed(2);
        }

        // Quantity Controls
        quantityDecrease.addEventListener('click', () => {
            const current = parseInt(quantityInput.value) || 1;
            if (current > 1) {
                quantityInput.value = current - 1;
                updateTotalPrice();
            }
        });

        quantityIncrease.addEventListener('click', () => {
            const current = parseInt(quantityInput.value) || 1;
            if (current < 10) {
                quantityInput.value = current + 1;
                updateTotalPrice();
            }
        });

        // Variant Selection
        variantRadios.forEach(radio => {
            radio.addEventListener('change', function() {
                if (this.checked) {
                    const imageSrc = this.getAttribute('data-image');
                    
                    // Update main image
                    mainProductImage.src = imageSrc;
                    imagePlaceholder.style.display = 'none';
                    mainProductImage.style.display = 'block';
                    zoomHint.style.display = 'block';
                    
                    // Update variant card active state
                    document.querySelectorAll('.variant-card').forEach(card => {
                        card.classList.remove('variant-card--active');
                    });
                    this.closest('.variant-card').classList.add('variant-card--active');
                }
            });
        });

        // Image Zoom
        productImageWrapper.addEventListener('click', function() {
            if (mainProductImage.src) {
                zoomedImage.src = mainProductImage.src;
                imageZoomModal.classList.add('image-zoom-modal--active');
                document.body.style.overflow = 'hidden';
            }
        });

        imageZoomClose.addEventListener('click', () => {
            imageZoomModal.classList.remove('image-zoom-modal--active');
            document.body.style.overflow = '';
        });

        imageZoomModal.querySelector('.image-zoom-overlay').addEventListener('click', () => {
            imageZoomModal.classList.remove('image-zoom-modal--active');
            document.body.style.overflow = '';
        });

        // Size Chart Modal
        sizeChartBtn.addEventListener('click', () => {
            sizeChartModal.classList.add('size-chart-modal--active');
            document.body.style.overflow = 'hidden';
        });

        sizeChartClose.addEventListener('click', () => {
            sizeChartModal.classList.remove('size-chart-modal--active');
            document.body.style.overflow = '';
        });

        sizeChartModal.querySelector('.size-chart-overlay').addEventListener('click', () => {
            sizeChartModal.classList.remove('size-chart-modal--active');
            document.body.style.overflow = '';
        });

        // Cart Popup
        cartFab.addEventListener('click', () => {
            cartPopup.classList.add('cart-popup--active');
            document.body.style.overflow = 'hidden';
        });

        cartPopupClose.addEventListener('click', () => {
            cartPopup.classList.remove('cart-popup--active');
            document.body.style.overflow = '';
        });

        cartPopup.querySelector('.cart-popup-overlay').addEventListener('click', () => {
            cartPopup.classList.remove('cart-popup--active');
            document.body.style.overflow = '';
        });

        // Form Submission with Loading State
        orderForm.addEventListener('submit', function(e) {
            // Basic validation
            const variantSelected = document.querySelector('input[name="variant"]:checked');
            const sizeSelected = document.querySelector('input[name="size"]:checked');
            
            if (!variantSelected || !sizeSelected) {
                e.preventDefault();
                return;
            }

            // Show loading state
            addToCartBtn.disabled = true;
            btnText.style.display = 'none';
            btnLoader.style.display = 'inline-block';
        });

        // Remove from Cart (AJAX)
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
                        itemElement.style.animation = 'fadeOut 0.3s ease-out';
                        setTimeout(() => {
                            itemElement.remove();
                            
                            // Update badge
                            if (data.cart_count > 0) {
                                if (cartFabBadge) {
                                    cartFabBadge.textContent = data.cart_count;
                                } else {
                                    const badge = document.createElement('span');
                                    badge.className = 'cart-fab-badge';
                                    badge.id = 'cartFabBadge';
                                    badge.textContent = data.cart_count;
                                    cartFab.appendChild(badge);
                                }
                            } else {
                                if (cartFabBadge) cartFabBadge.remove();
                                // Reload after a moment to show empty cart
                                setTimeout(() => location.reload(), 500);
                            }
                        }, 300);
                    }
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error removing item. Please try again.');
            });
        }

        // Initialize
        updateTotalPrice();
    </script>
</body>
</html>
