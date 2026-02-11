<?php
/**
 * E-Commerce Product Page with Shopping Cart
 * 
 * This page handles:
 * - Product variant and size selection
 * - Shopping cart management (add/remove items)
 * - Session-based cart storage
 * - Order form submission
 * - Image lazy loading with preloading
 */

// HTTPS enforcement and security headers
$isLocalhost = in_array($_SERVER['HTTP_HOST'] ?? '', ['localhost', '127.0.0.1', '::1']) || 
               (isset($_SERVER['SERVER_ADDR']) && in_array($_SERVER['SERVER_ADDR'], ['127.0.0.1', '::1']));

if (!$isLocalhost && (!isset($_SERVER['HTTPS']) || $_SERVER['HTTPS'] !== 'on')) {
    header("Location: https://{$_SERVER['HTTP_HOST']}{$_SERVER['REQUEST_URI']}", true, 301);
    exit;
}

if (!headers_sent()) {
    if (!$isLocalhost) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('X-XSS-Protection: 1; mode=block');
    header('Referrer-Policy: strict-origin-when-cross-origin');
}


// Session security configuration
$isLocalhost = in_array($_SERVER['HTTP_HOST'] ?? '', ['localhost', '127.0.0.1', '::1']) || 
               (isset($_SERVER['SERVER_ADDR']) && in_array($_SERVER['SERVER_ADDR'], ['127.0.0.1', '::1']));

ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', $isLocalhost ? 0 : 1);
ini_set('session.cookie_samesite', 'Strict');
ini_set('session.use_strict_mode', 1);
ini_set('session.gc_maxlifetime', 3600);
ini_set('session.gc_probability', 1);
ini_set('session.gc_divisor', 100);

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

// Include CSRF protection
require_once __DIR__ . '/includes/csrf.php';

// Include rate limiting
require_once __DIR__ . '/includes/rate_limit.php';

// Include version configuration for asset versioning
require_once __DIR__ . '/config/version.php';

// Check if orders are closed
try {
    $pdo = getDBConnection();
    $ordersClosedStmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'orders_closed' LIMIT 1");
    $ordersClosedStmt->execute();
    $ordersClosedResult = $ordersClosedStmt->fetch(PDO::FETCH_ASSOC);
    $ordersClosed = ($ordersClosedResult && isset($ordersClosedResult['setting_value']) && ($ordersClosedResult['setting_value'] === '1' || $ordersClosedResult['setting_value'] === 'true'));
    
    // If orders are closed, show closed page and exit
    if ($ordersClosed) {
        // Display closed page
        ?>
<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>Exclusive Access Closed | Projek Cuti Sem (PCS)</title>
    <meta name="description" content="The exclusive early access for PCS X WORKSHIRT COLLECTION is now closed. Stay tuned for future releases.">
    <meta name="robots" content="noindex, follow">
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <link rel="canonical" href="https://projectcutisem.com/order-baju.php">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: linear-gradient(135deg, #111111 0%, #1a1a1a 50%, #111111 100%);
            color: #ffffff;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            line-height: 1.6;
            position: relative;
            overflow-x: hidden;
        }
        
        /* Animated background pattern */
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-image: 
                radial-gradient(circle at 20% 50%, rgba(255, 255, 255, 0.03) 0%, transparent 50%),
                radial-gradient(circle at 80% 80%, rgba(255, 255, 255, 0.03) 0%, transparent 50%);
            animation: backgroundMove 20s ease-in-out infinite;
            z-index: 0;
        }
        
        @keyframes backgroundMove {
            0%, 100% { transform: translate(0, 0) scale(1); }
            50% { transform: translate(-20px, -20px) scale(1.1); }
        }
        
        .container {
            max-width: 700px;
            width: 100%;
            text-align: center;
            position: relative;
            z-index: 1;
            animation: fadeInUp 0.8s ease-out;
        }
        
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .logo {
            margin-bottom: 40px;
            animation: fadeIn 1s ease-out 0.2s both;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        .logo img {
            width: 80px;
            height: 80px;
            filter: drop-shadow(0 4px 8px rgba(0, 0, 0, 0.3));
        }
        
        .badge {
            display: inline-block;
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            padding: 8px 20px;
            border-radius: 30px;
            font-size: 12px;
            font-weight: 600;
            letter-spacing: 1px;
            text-transform: uppercase;
            margin-bottom: 30px;
            animation: fadeIn 1s ease-out 0.4s both;
            backdrop-filter: blur(10px);
        }
        
        h1 {
            font-size: clamp(32px, 5vw, 48px);
            font-weight: 700;
            margin-bottom: 20px;
            letter-spacing: -1px;
            background: linear-gradient(135deg, #ffffff 0%, #cccccc 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            animation: fadeIn 1s ease-out 0.6s both;
        }
        
        .message {
            font-size: clamp(16px, 2.5vw, 20px);
            color: rgba(255, 255, 255, 0.85);
            margin-bottom: 40px;
            line-height: 1.8;
            animation: fadeIn 1s ease-out 0.8s both;
        }
        
        .quote {
            font-size: clamp(18px, 3vw, 24px);
            font-style: italic;
            color: rgba(255, 255, 255, 0.7);
            margin: 50px 0;
            padding: 30px;
            border-left: 3px solid rgba(255, 255, 255, 0.3);
            text-align: left;
            background: rgba(255, 255, 255, 0.03);
            border-radius: 8px;
            animation: fadeIn 1s ease-out 1s both;
            position: relative;
        }
        
        .quote::before {
            content: '"';
            font-size: 60px;
            position: absolute;
            top: -10px;
            left: 15px;
            color: rgba(255, 255, 255, 0.1);
            font-family: Georgia, serif;
        }
        
        .info {
            font-size: clamp(14px, 2vw, 16px);
            color: rgba(255, 255, 255, 0.6);
            margin-bottom: 50px;
            line-height: 1.8;
            animation: fadeIn 1s ease-out 1.2s both;
        }
        
        .signature {
            margin-top: 60px;
            padding-top: 30px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            animation: fadeIn 1s ease-out 1.4s both;
        }
        
        .signature p {
            font-size: 14px;
            color: rgba(255, 255, 255, 0.5);
            margin-top: 10px;
        }
        
        .back-link {
            display: inline-block;
            margin-top: 40px;
            padding: 14px 32px;
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: #ffffff;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 600;
            font-size: 16px;
            transition: all 0.3s ease;
            backdrop-filter: blur(10px);
            animation: fadeIn 1s ease-out 1.6s both;
        }
        
        .back-link:hover {
            background: rgba(255, 255, 255, 0.2);
            border-color: rgba(255, 255, 255, 0.3);
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.3);
        }
        
        .back-link:active {
            transform: translateY(0);
        }
        
        /* Decorative elements */
        .decorative-line {
            width: 60px;
            height: 2px;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.3), transparent);
            margin: 30px auto;
            animation: fadeIn 1s ease-out 1s both;
        }
        
        @media (max-width: 768px) {
            .container {
                padding: 20px;
            }
            
            h1 {
                font-size: 32px;
            }
            
            .message {
                font-size: 16px;
            }
            
            .quote {
                font-size: 18px;
                padding: 20px;
            }
        }
        
        /* Loading animation */
        @keyframes pulse {
            0%, 100% {
                opacity: 1;
            }
            50% {
                opacity: 0.5;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo">
            <img src="/images/PCS logo.svg" alt="Projek Cuti Sem Logo" width="80" height="80">
        </div>
        
        <div class="badge">Exclusive Access</div>
        
        <h1>EXCLUSIVE ACCESS CLOSED</h1>
        
        <div class="message">
            Thank you for your interest in the <strong>PCS X WORKSHIRT COLLECTION</strong>.
            <br><br>
            This exclusive early access is now closed.
        </div>
        
        <div class="decorative-line"></div>
        
        <div class="quote">
            "You don't follow trends. You set them."
        </div>
        
        <div class="info">
            If you missed this round, don't worry – we'll be back with more limited releases in the future.
            <br><br>
            Stay tuned and make sure you're on our circle to get first access next time.
        </div>
        
        <div class="signature">
            <p>Sincerely,<br>projectcutisem.com</p>
        </div>
        
        <a href="/" class="back-link">← Back to Home</a>
    </div>
</body>
</html>
        <?php
        exit;
    }
} catch (Exception $e) {
    error_log('Error checking orders_closed setting: ' . $e->getMessage());
}

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

// Product Configuration - Load from Database
try {
    $pdo = getDBConnection();
    
    // Get product from database
    $productStmt = $pdo->query("SELECT * FROM products LIMIT 1");
    $product = $productStmt->fetch();
    
    if ($product) {
        // Override product name to always display "PCS Half-Zip Workshirt"
        define('PRODUCT_NAME', 'PCS Half-Zip Workshirt');
        define('PRICE_PER_SHIRT', floatval($product['price']));
        define('ORIGINAL_PRICE', 120.00); // Marketing original price for strikethrough display
        $productStockStatus = $product['stock_status'];
        
        $variantsStmt = $pdo->prepare("
            SELECT variant_key, variant_name, variant_image 
            FROM product_variants 
            WHERE product_id = :product_id
            ORDER BY variant_key
        ");
        $variantsStmt->execute([':product_id' => $product['id']]);
        $dbVariants = $variantsStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Build variants array with colors (for display)
        $variants = [];
        $variantColors = [
            'hitamdepan' => '#1a1a1a',
            'hijaufront' => '#2d5016',
            'merahdepan' => '#8b0000'
        ];
        
        foreach ($dbVariants as $v) {
            $variants[$v['variant_key']] = [
                'name' => $v['variant_name'],
                'image' => $v['variant_image'],
                'color' => $variantColors[$v['variant_key']] ?? '#1a1a1a'
            ];
        }
        
        $inventoryData = [];
        if ($productStockStatus === 'in_stock') {
            $inventoryStmt = $pdo->prepare("
                SELECT pv.variant_key, pi.size, pi.quantity
                FROM product_inventory pi
                JOIN product_variants pv ON pi.variant_id = pv.id
                WHERE pv.product_id = :product_id
            ");
            $inventoryStmt->execute([':product_id' => $product['id']]);
            $inventoryRows = $inventoryStmt->fetchAll();
            
            foreach ($inventoryRows as $row) {
                if (!isset($inventoryData[$row['variant_key']])) {
                    $inventoryData[$row['variant_key']] = [];
                }
                $inventoryData[$row['variant_key']][$row['size']] = intval($row['quantity']);
            }
        }
    } else {
        // Fallback to defaults if no product found
        define('PRODUCT_NAME', 'PCS Half-Zip Workshirt');
        define('PRICE_PER_SHIRT', 50.00);
        define('ORIGINAL_PRICE', 120.00); // Marketing original price for strikethrough display
        $productStockStatus = 'pre_order';
        $inventoryData = [];
        
        $variants = [
            'hitamdepan' => [
                'name' => 'Classic Black',
                'image' => 'images/hitamdepan.svg',
                'color' => '#1a1a1a'
            ],
            'hijaufront' => [
                'name' => 'Forest Green',
                'image' => 'images/hijaufront.svg',
                'color' => '#2d5016'
            ],
            'merahdepan' => [
                'name' => 'Royal Red',
                'image' => 'images/merahdepan.svg',
                'color' => '#8b0000'
            ]
        ];
    }
} catch (PDOException $e) {
    // Fallback to defaults on database error
    error_log('Error loading product from database: ' . $e->getMessage());
    define('PRODUCT_NAME', 'PCS Half-Zip Workshirt');
    define('PRICE_PER_SHIRT', 50.00);
    define('ORIGINAL_PRICE', 120.00); // Marketing original price for strikethrough display
    $productStockStatus = 'pre_order';
    $inventoryData = [];
    
    $variants = [
        'hitamdepan' => [
            'name' => 'Classic Black',
            'image' => 'images/hitamdepan.svg',
            'color' => '#1a1a1a'
        ],
        'hijaufront' => [
            'name' => 'Forest Green',
            'image' => 'images/hijaufront.svg',
            'color' => '#2d5016'
        ],
        'merahdepan' => [
            'name' => 'Royal Red',
            'image' => 'images/merahdepan.svg',
            'color' => '#8b0000'
        ]
    ];
}

// Size Options
$sizes = ['S', 'M', 'L', 'XL', '2XL', '3XL', '4XL', '5XL', '6XL'];

// Size Addon Prices (for sizes 3XL and above)
function getSizeAddonPrice($size) {
    $addonPrices = [
        '3XL' => 3.00,
        '4XL' => 5.00,
        '5XL' => 5.00,
        '6XL' => 5.00
    ];
    return isset($addonPrices[$size]) ? $addonPrices[$size] : 0.00;
}

// Size Chart Data (width and length in cm)
function getSizeChartData() {
    return [
        'S' => ['width' => 50, 'length' => 66, 'addon' => 0.00],
        'M' => ['width' => 52, 'length' => 68, 'addon' => 0.00],
        'L' => ['width' => 54, 'length' => 71, 'addon' => 0.00],
        'XL' => ['width' => 56, 'length' => 74, 'addon' => 0.00],
        '2XL' => ['width' => 58, 'length' => 77, 'addon' => 0.00],
        '3XL' => ['width' => 60, 'length' => 80, 'addon' => 3.00],
        '4XL' => ['width' => 62, 'length' => 83, 'addon' => 5.00],
        '5XL' => ['width' => 64, 'length' => 85, 'addon' => 5.00],
        '6XL' => ['width' => 66, 'length' => 87, 'addon' => 5.00]
    ];
}

// Handle Add to Cart
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_to_cart') {
    if (!checkRateLimit('cart_add', 20, 60)) {
        $message = 'Too many requests. Please wait a moment before adding more items.';
        $messageType = 'error';
    } elseif (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        http_response_code(403);
        $message = 'Invalid security token. Please refresh the page and try again.';
        $messageType = 'error';
    } else {
        $variant = $_POST['variant'] ?? '';
        $size = $_POST['size'] ?? '';
        $quantity = intval($_POST['quantity'] ?? 0);
        
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
        // Check product stock status and inventory
        if (isset($productStockStatus) && $productStockStatus === 'out_of_stock') {
            $message = 'This product is currently out of stock. Please check back later.';
            $messageType = 'error';
        } elseif (isset($productStockStatus) && $productStockStatus === 'in_stock') {
            try {
                $pdo = getDBConnection();
                $inventoryStmt = $pdo->prepare("
                    SELECT pi.quantity 
                    FROM product_inventory pi
                    JOIN product_variants pv ON pi.variant_id = pv.id
                    WHERE pv.variant_key = :variant_key AND pi.size = :size
                ");
                $inventoryStmt->execute([
                    ':variant_key' => $variant,
                    ':size' => $size
                ]);
                $inventory = $inventoryStmt->fetch();
                
                $availableQty = $inventory ? intval($inventory['quantity']) : 0;
                
                if ($availableQty < $quantity) {
                    if ($availableQty === 0) {
                        $message = 'This variant and size combination is currently out of stock.';
                    } else {
                        $message = "Only {$availableQty} unit(s) available for this variant and size. Please adjust your quantity.";
                    }
                    $messageType = 'error';
                } else {
                    $sizeAddon = getSizeAddonPrice($size);
                    $itemPrice = PRICE_PER_SHIRT + $sizeAddon;
                    $itemTotal = $itemPrice * $quantity;
                    
                    $cartItem = [
                        'id' => uniqid('item_'),
                        'variant' => $variant,
                        'variant_name' => $variants[$variant]['name'],
                        'variant_image' => $variants[$variant]['image'],
                        'size' => $size,
                        'quantity' => $quantity,
                        'price' => $itemPrice,
                        'size_addon' => $sizeAddon,
                        'subtotal' => $itemTotal
                    ];
                    
                    $_SESSION['cart'][] = $cartItem;
                    $message = 'Item successfully added to cart!';
                    $messageType = 'success';
                }
            } catch (PDOException $e) {
                error_log('Inventory check error: ' . $e->getMessage());
                $sizeAddon = getSizeAddonPrice($size);
                $itemPrice = PRICE_PER_SHIRT + $sizeAddon;
                $itemTotal = $itemPrice * $quantity;
                
                $cartItem = [
                    'id' => uniqid('item_'),
                    'variant' => $variant,
                    'variant_name' => $variants[$variant]['name'],
                    'variant_image' => $variants[$variant]['image'],
                    'size' => $size,
                    'quantity' => $quantity,
                    'price' => $itemPrice,
                    'size_addon' => $sizeAddon,
                    'subtotal' => $itemTotal
                ];
                
                $_SESSION['cart'][] = $cartItem;
                $message = 'Item successfully added to cart!';
                $messageType = 'success';
            }
        } else {
            $sizeAddon = getSizeAddonPrice($size);
            $itemPrice = PRICE_PER_SHIRT + $sizeAddon;
            $itemTotal = $itemPrice * $quantity;
            
            $cartItem = [
                'id' => uniqid('item_'),
                'variant' => $variant,
                'variant_name' => $variants[$variant]['name'],
                'variant_image' => $variants[$variant]['image'],
                'size' => $size,
                'quantity' => $quantity,
                'price' => $itemPrice,
                'size_addon' => $sizeAddon,
                'subtotal' => $itemTotal
            ];
            
            $_SESSION['cart'][] = $cartItem;
            $message = 'Item successfully added to cart!';
            $messageType = 'success';
        }
    }
}

// Handle Remove from Cart (AJAX)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'remove_from_cart') {
    if (!checkRateLimit('cart_remove', 20, 60)) {
        header('Content-Type: application/json');
        http_response_code(429);
        echo json_encode([
            'success' => false,
            'error' => 'Too many requests. Please wait a moment.'
        ]);
        exit;
    }
    // SECURITY: CSRF protection - validate token
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        header('Content-Type: application/json');
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'error' => 'Invalid security token. Please refresh the page.'
        ]);
        exit;
    }
    
    // SECURITY: Safe input retrieval with null coalescing
    // Item IDs are generated server-side (uniqid()), so they're safe
    // but we still validate they exist in the session
    $itemId = $_POST['item_id'] ?? '';
    
    // SECURITY: Multi-condition validation
    // - Ensures item_id is not empty (prevents empty string attacks)
    // - Verifies cart exists in session (prevents null reference errors)
    if (!empty($itemId) && isset($_SESSION['cart'])) {
        // Find the item to get its subtotal before removing
        $removedItemSubtotal = 0;
        foreach ($_SESSION['cart'] as $item) {
            if ($item['id'] === $itemId) {
                $removedItemSubtotal = $item['subtotal'];
                break;
            }
        }
        
        $_SESSION['cart'] = array_filter($_SESSION['cart'], function($item) use ($itemId) {
            return $item['id'] !== $itemId;
        });
        $_SESSION['cart'] = array_values($_SESSION['cart']);
        
        if (empty($_SESSION['cart'])) {
            $_SESSION['cart'] = [];
        }
        
        $newCartTotal = 0;
        foreach ($_SESSION['cart'] as $item) {
            $newCartTotal += $item['subtotal'];
        }
        
        regenerateCSRFToken();
        
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true, 
            'cart_count' => count($_SESSION['cart']),
            'cart_total' => round($newCartTotal, 2),
            'removed_subtotal' => $removedItemSubtotal
        ]);
        exit;
    }
}

// Calculate Cart Totals
$cartCount = count($_SESSION['cart']);
$cartTotal = 0;
foreach ($_SESSION['cart'] as $item) {
    $cartTotal += $item['subtotal'];
}
?>
<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    
    <!-- Primary Meta Tags -->
    <title>Order - <?php echo htmlspecialchars(PRODUCT_NAME); ?> | Projek Cuti Sem</title>
    <meta name="title" content="Order <?php echo htmlspecialchars(PRODUCT_NAME); ?> | Projek Cuti Sem">
    <meta name="description" content="Order your premium workshirt from Projek Cuti Sem. Heavyweight Japanese drill fabric, YKK zipper, available in multiple colors and sizes. Fast shipping across Malaysia.">
    <meta name="keywords" content="order workshirt, Projek Cuti Sem, PCS workshirt, buy workshirt Malaysia, premium clothing Malaysia">
    <meta name="robots" content="index, follow">
    
    <!-- Open Graph / Facebook -->
    <meta property="og:type" content="website">
    <meta property="og:url" content="https://projectcutisem.com/order-baju.php">
    <meta property="og:title" content="Order <?php echo htmlspecialchars(PRODUCT_NAME); ?> | Projek Cuti Sem">
    <meta property="og:description" content="Order your premium workshirt from Projek Cuti Sem. Heavyweight Japanese drill fabric, YKK zipper, available in multiple colors and sizes.">
    <meta property="og:image" content="https://projectcutisem.com/images/android-chrome-512x512.png">
    
    <!-- Twitter -->
    <meta property="twitter:card" content="summary_large_image">
    <meta property="twitter:url" content="https://projectcutisem.com/order-baju.php">
    <meta property="twitter:title" content="Order <?php echo htmlspecialchars(PRODUCT_NAME); ?> | Projek Cuti Sem">
    <meta property="twitter:description" content="Order your premium workshirt from Projek Cuti Sem. Heavyweight Japanese drill fabric, YKK zipper, available in multiple colors and sizes.">
    <meta property="twitter:image" content="https://projectcutisem.com/images/android-chrome-512x512.png">
    
    <!-- Favicons -->
    <link rel="icon" type="image/x-icon" href="https://projectcutisem.com/favicon.ico">
    <link rel="shortcut icon" type="image/x-icon" href="https://projectcutisem.com/favicon.ico">
    <link rel="apple-touch-icon" href="https://projectcutisem.com/apple-touch-icon.png">
    
    <!-- Stylesheet -->
    <link rel="stylesheet" href="<?php echo getVersionedAsset('css/order-baju.css'); ?>">
    <link rel="stylesheet" href="<?php echo getVersionedAsset('css/image-loader.css'); ?>">
    
    <!-- Preconnect for Performance -->
    <link rel="preconnect" href="https://projectcutisem.com">
    <link rel="dns-prefetch" href="https://projectcutisem.com">
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
                            <img data-src="" alt="Product Preview" id="mainProductImage" class="lazy-image main-product-image">
                            <div class="placeholder"></div>
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
                        <!-- Product Title -->
                        <div class="product-header">
                            <div class="product-badge">
                                <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                                    <path d="M8 0L10.163 5.527L16 6.11L12 9.945L12.944 16L8 13.527L3.056 16L4 9.945L0 6.11L5.837 5.527L8 0Z" fill="currentColor"/>
                                </svg>
                                <span>Premium Quality</span>
                            </div>
                            <h1 class="product-title"><?php echo htmlspecialchars(PRODUCT_NAME); ?></h1>
                            <p class="product-subtitle">Your new everyday outfit for class and beyond.</p>
                            <div class="product-price-display">
                                <span class="price-label">Starting from</span>
                                <span class="price-value">
                                    <?php if (PRICE_PER_SHIRT < ORIGINAL_PRICE): ?>
                                        <span class="original-price">RM <?php echo number_format(ORIGINAL_PRICE, 2); ?></span>
                                        <span class="current-price">RM <?php echo number_format(PRICE_PER_SHIRT, 2); ?></span>
                                    <?php else: ?>
                                        <span class="current-price">RM <?php echo number_format(PRICE_PER_SHIRT, 2); ?></span>
                                    <?php endif; ?>
                                </span>
                            </div>
                            <p class="product-brand">PCS™ - Represent Project Cuti Sem</p>
                        </div>

                        <!-- Message Display -->
                        <?php if ($message): ?>
                            <div class="order-message order-message--<?php echo htmlspecialchars($messageType); ?>" id="orderMessage">
                                <?php echo htmlspecialchars($message); ?>
                            </div>
                        <?php endif; ?>

                        <!-- Order Form -->
                        <form method="POST" action="" id="orderForm" class="order-form">
                            <input type="hidden" name="action" value="add_to_cart">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCSRFToken(), ENT_QUOTES, 'UTF-8'); ?>">
                            
                            <!-- Variant Selection -->
                            <div class="form-section">
                                <label class="form-label" for="variant-selector">
                                    Select Color
                                    <span class="required-asterisk" aria-label="required">*</span>
                                </label>
                                <div class="variant-selector" id="variantSelector" role="radiogroup" aria-labelledby="variant-label">
                                    <?php foreach ($variants as $key => $variant): ?>
                                        <label class="variant-card" data-variant="<?php echo htmlspecialchars($key, ENT_QUOTES, 'UTF-8'); ?>" aria-label="Select <?php echo htmlspecialchars($variant['name'], ENT_QUOTES, 'UTF-8'); ?> color">
                                            <input type="radio" name="variant" value="<?php echo htmlspecialchars($key, ENT_QUOTES, 'UTF-8'); ?>" 
                                                   data-image="<?php echo htmlspecialchars($variant['image'], ENT_QUOTES, 'UTF-8'); ?>"
                                                   id="variant-<?php echo htmlspecialchars($key, ENT_QUOTES, 'UTF-8'); ?>"
                                                   aria-describedby="variant-<?php echo htmlspecialchars($key, ENT_QUOTES, 'UTF-8'); ?>-name"
                                                   required>
                                            <div class="variant-card-content">
                                                <div class="variant-thumbnail">
                                                    <!-- Variant thumbnail with lazy loading -->
                                                    <img class="lazy-image" data-src="<?php echo htmlspecialchars($variant['image']); ?>" 
                                                         alt="<?php echo htmlspecialchars($variant['name']); ?> workshirt"
                                                         width="120"
                                                         height="120">
                                                    <div class="placeholder"></div>
                                                </div>
                                                <div class="variant-label" id="variant-<?php echo htmlspecialchars($key); ?>-name">
                                                    <?php echo htmlspecialchars($variant['name']); ?>
                                                </div>
                                                <div class="variant-check" aria-hidden="true">
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
                                    <label class="form-label" for="size-selector">
                                        Size
                                        <span class="required-asterisk" aria-label="required">*</span>
                                    </label>
                                    <button type="button" class="size-chart-link" id="sizeChartBtn" aria-label="View size chart">Size Chart</button>
                                </div>
                                <div class="size-selector" id="sizeSelector" role="radiogroup" aria-labelledby="size-label">
                                    <?php foreach ($sizes as $size): ?>
                                        <label class="size-option" aria-label="Select size <?php echo htmlspecialchars($size, ENT_QUOTES, 'UTF-8'); ?>">
                                            <input type="radio" name="size" value="<?php echo htmlspecialchars($size, ENT_QUOTES, 'UTF-8'); ?>" 
                                                   id="size-<?php echo htmlspecialchars($size, ENT_QUOTES, 'UTF-8'); ?>"
                                                   required>
                                            <span class="size-label"><?php echo htmlspecialchars($size, ENT_QUOTES, 'UTF-8'); ?></span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <!-- Quantity Selection -->
                            <div class="form-section">
                                <label class="form-label" for="quantityInput">
                                    Quantity
                                    <span class="required-asterisk" aria-label="required">*</span>
                                </label>
                                <div class="quantity-selector" role="group" aria-labelledby="quantity-label">
                                    <button type="button" class="quantity-btn quantity-btn--decrease" id="quantityDecrease" aria-label="Decrease quantity">−</button>
                                    <input type="number" name="quantity" id="quantityInput" value="1" min="1" max="10" required readonly aria-label="Quantity">
                                    <button type="button" class="quantity-btn quantity-btn--increase" id="quantityIncrease" aria-label="Increase quantity">+</button>
                                </div>
                            </div>

                            <!-- Total Price Display -->
                            <div class="price-summary">
                                <div class="price-row">
                                    <span>Price Per Unit:</span>
                                    <span class="price-per-unit-display">
                                        <?php if (PRICE_PER_SHIRT < ORIGINAL_PRICE): ?>
                                            <span class="original-price">RM <?php echo number_format(ORIGINAL_PRICE, 2); ?></span>
                                            <span class="current-price">RM <?php echo number_format(PRICE_PER_SHIRT, 2); ?></span>
                                        <?php else: ?>
                                            <span class="current-price">RM <?php echo number_format(PRICE_PER_SHIRT, 2); ?></span>
                                        <?php endif; ?>
                                    </span>
                                </div>
                                <div class="price-row price-row--total">
                                    <span>Total:</span>
                                    <span class="total-price">
                                        <?php if (PRICE_PER_SHIRT < ORIGINAL_PRICE): ?>
                                            <span class="original-price-total">RM <span id="originalTotalPriceDisplay"><?php echo number_format(ORIGINAL_PRICE, 2); ?></span></span>
                                            <span class="current-price-total">RM <span id="totalPriceDisplay"><?php echo number_format(PRICE_PER_SHIRT, 2); ?></span></span>
                                        <?php else: ?>
                                            <span class="current-price-total">RM <span id="totalPriceDisplay"><?php echo number_format(PRICE_PER_SHIRT, 2); ?></span></span>
                                        <?php endif; ?>
                                    </span>
                                </div>
                            </div>

                            <!-- Add to Cart Button -->
                            <button type="submit" class="add-to-cart-btn" id="addToCartBtn" aria-label="Add selected items to shopping cart">
                                <span class="btn-text">Add to Cart</span>
                                <span class="btn-loader" style="display: none;" aria-hidden="true">
                                    <svg width="20" height="20" viewBox="0 0 20 20" fill="none" aria-label="Loading">
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
                            <h3 class="details-title">Product Details</h3>
                            <div class="details-grid">
                                <div class="detail-item">
                                    <div class="detail-icon">
                                        <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
                                            <path d="M10 2L3 7V18H17V7L10 2Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                            <path d="M10 10V18M6 10H14" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                                        </svg>
                                    </div>
                                    <div class="detail-content">
                                        <strong>Fabric:</strong> Heavyweight Japanese Drill
                                    </div>
                                </div>
                                <div class="detail-item">
                                    <div class="detail-icon">
                                        <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
                                            <path d="M10 2C6.5 2 3.5 5 3.5 8.5C3.5 12 6.5 15 10 15C13.5 15 16.5 12 16.5 8.5C16.5 5 13.5 2 10 2Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                            <path d="M10 5V8.5L12.5 10" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                        </svg>
                                    </div>
                                    <div class="detail-content">
                                        <strong>Fit:</strong> Relaxed Workwear Fit
                                    </div>
                                </div>
                                <div class="detail-item">
                                    <div class="detail-icon">
                                        <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
                                            <path d="M10 18C14.4183 18 18 14.4183 18 10C18 5.58172 14.4183 2 10 2C5.58172 2 2 5.58172 2 10C2 14.4183 5.58172 18 10 18Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                            <path d="M10 6V10L13 11" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                        </svg>
                                    </div>
                                    <div class="detail-content">
                                        <strong>Zipper:</strong> YKK Metal Half-Zip
                                    </div>
                                </div>
                                <div class="detail-item">
                                    <div class="detail-icon">
                                        <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
                                            <path d="M10 2L12.163 7.527L18 8.11L14 11.945L14.944 18L10 15.527L5.056 18L6 11.945L2 8.11L7.837 7.527L10 2Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                        </svg>
                                    </div>
                                    <div class="detail-content">
                                        <strong>Made with:</strong> love & craftsmanship
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Trust Indicators -->
                            <div class="trust-indicators">
                                <div class="trust-item">
                                    <svg width="18" height="18" viewBox="0 0 18 18" fill="none">
                                        <path d="M9 1L11.5 6.5L17.5 7.5L13 11.5L14 17.5L9 14.5L4 17.5L5 11.5L0.5 7.5L6.5 6.5L9 1Z" fill="currentColor"/>
                                    </svg>
                                    <span>Premium Quality</span>
                                </div>
                                <div class="trust-item">
                                    <svg width="18" height="18" viewBox="0 0 18 18" fill="none">
                                        <path d="M9 1L11.5 6.5L17.5 7.5L13 11.5L14 17.5L9 14.5L4 17.5L5 11.5L0.5 7.5L6.5 6.5L9 1Z" fill="currentColor"/>
                                    </svg>
                                    <span>Fast Shipping</span>
                                </div>
                                <div class="trust-item">
                                    <svg width="18" height="18" viewBox="0 0 18 18" fill="none">
                                        <path d="M9 1L11.5 6.5L17.5 7.5L13 11.5L14 17.5L9 14.5L4 17.5L5 11.5L0.5 7.5L6.5 6.5L9 1Z" fill="currentColor"/>
                                    </svg>
                                    <span>Secure Payment</span>
                                </div>
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
            <div class="size-chart-illustration">
                <!-- Size chart image with lazy loading -->
                <img class="lazy-image size-chart-image" data-src="images/MockUp size.svg" alt="Size chart illustration showing pit (width) and length measurements">
                <div class="placeholder"></div>
            </div>
            <div class="size-chart-table-wrapper">
                <table class="size-chart-table">
                    <thead>
                        <tr>
                            <th>Size</th>
                            <th>Pit(Width) (cm)</th>
                            <th>Length (cm)</th>
                            <th>Addon Price</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $sizeChartData = getSizeChartData();
                        foreach ($sizes as $size): 
                            $data = $sizeChartData[$size] ?? ['width' => 0, 'length' => 0, 'addon' => 0.00];
                        ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($size); ?></strong></td>
                            <td><?php echo $data['width']; ?></td>
                            <td><?php echo $data['length']; ?></td>
                            <td>
                                <?php if ($data['addon'] > 0): ?>
                                    <strong>+RM <?php echo number_format($data['addon'], 2); ?></strong>
                                <?php else: ?>
                                    <span style="color: #999;">-</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
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
                        <div class="cart-item-card" data-item-id="<?php echo htmlspecialchars($item['id'], ENT_QUOTES, 'UTF-8'); ?>">
                            <div class="cart-item-image-wrapper">
                                <img class="lazy-image cart-item-image" data-src="<?php echo htmlspecialchars($item['variant_image'], ENT_QUOTES, 'UTF-8'); ?>" 
                                     alt="<?php echo htmlspecialchars($item['variant_name'], ENT_QUOTES, 'UTF-8'); ?>">
                                <div class="placeholder"></div>
                            </div>
                            <div class="cart-item-info">
                                <h4 class="cart-item-title"><?php echo htmlspecialchars($item['variant_name'], ENT_QUOTES, 'UTF-8'); ?></h4>
                                <p class="cart-item-meta">Size: <?php echo htmlspecialchars($item['size'], ENT_QUOTES, 'UTF-8'); ?> • Qty: <?php echo intval($item['quantity']); ?></p>
                                <div class="cart-item-price">RM <?php echo number_format(floatval($item['subtotal']), 2); ?></div>
                            </div>
                            <button class="cart-item-remove" onclick="removeFromCart('<?php echo htmlspecialchars($item['id'], ENT_QUOTES, 'UTF-8'); ?>')" aria-label="Remove item">
                                <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
                                    <path d="M15 5L5 15M5 5l10 10" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                </svg>
                            </button>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <div class="cart-summary">
                    <div class="cart-summary-row">
                        <span>Total Items:</span>
                        <span><?php echo $cartCount; ?> item(s)</span>
                    </div>
                    <div class="cart-summary-row cart-summary-total">
                        <span>Total Amount:</span>
                        <span class="cart-total-amount">RM <?php echo number_format($cartTotal, 2); ?></span>
                    </div>
                </div>
                
                <a href="checkout.php" class="cart-checkout-btn">Go to Checkout</a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Image Lazy Loader - Enhanced with WebP support and preloading -->
    <script src="<?php echo getVersionedAsset('js/image-lazy-loader.js'); ?>"></script>

    <!-- Order Page Configuration -->
    <script id="orderPageConfig" type="application/json">
    {
        "pricePerShirt": <?php echo PRICE_PER_SHIRT; ?>,
        "originalPrice": <?php echo ORIGINAL_PRICE; ?>,
        "showOriginalPrice": <?php echo (PRICE_PER_SHIRT < ORIGINAL_PRICE) ? 'true' : 'false'; ?>,
        "csrfToken": "<?php echo htmlspecialchars(generateCSRFToken(), ENT_QUOTES, 'UTF-8'); ?>",
        "sizeAddonPrices": {
            "S": 0.00,
            "M": 0.00,
            "L": 0.00,
            "XL": 0.00,
            "2XL": 0.00,
            "3XL": 3.00,
            "4XL": 5.00,
            "5XL": 5.00,
            "6XL": 5.00
        }
    }
    </script>

    <!-- Order Page JavaScript Module -->
    <script src="<?php echo getVersionedAsset('js/order-baju.js'); ?>"></script>
</body>
</html>
