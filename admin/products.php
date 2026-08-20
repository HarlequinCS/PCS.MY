<?php
/**
 * Product Management Page
 */

require_once __DIR__ . '/config.php';
requireAdminLogin();

define('DB_CONFIG_LOADED', true);
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/cloudinary.php';
require_once __DIR__ . '/sidebar_helper.php';

$pdo = getDBConnection();
$currentPage = basename(__FILE__);

// Get product data
$productStmt = $pdo->query("SELECT * FROM products LIMIT 1");
$product = $productStmt->fetch();

if (!$product) {
    $message = 'No product found. Please run the database migration script first.';
    $messageType = 'error';
    $product = null;
    $variants = [];
    $inventory = [];
} else {
    // Get variants
    $variantsStmt = $pdo->prepare("
        SELECT * FROM product_variants 
        WHERE product_id = :product_id 
        ORDER BY variant_key
    ");
    $variantsStmt->execute([':product_id' => $product['id']]);
    $variants = $variantsStmt->fetchAll();

    // Get inventory for all variants
    $inventoryStmt = $pdo->prepare("
        SELECT pi.variant_id, pi.size, pi.quantity, pv.variant_key
        FROM product_inventory pi
        JOIN product_variants pv ON pi.variant_id = pv.id
        WHERE pv.product_id = :product_id
        ORDER BY pv.variant_key, pi.size
    ");
    $inventoryStmt->execute([':product_id' => $product['id']]);
    $inventoryData = $inventoryStmt->fetchAll();

    // Organize inventory by variant_id and size
    $inventory = [];
    foreach ($inventoryData as $inv) {
        $inventory[$inv['variant_id']][$inv['size']] = intval($inv['quantity']);
    }
}

// Handle logout
if (isset($_GET['logout'])) {
    adminLogout();
    header('Location: /admin/');
    exit;
}

$sizes = ['S', 'M', 'L', 'XL', 'XXL'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Products Management - Admin Panel</title>
    <link rel="stylesheet" href="<?php echo getAdminAsset('css/admin.css'); ?>">
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <style>
        .product-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 2rem;
            flex-wrap: wrap;
            gap: 1.5rem;
        }

        .product-info {
            flex: 1;
            min-width: 300px;
        }

        .product-price-edit {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-top: 1rem;
        }

        .price-input {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .price-input input {
            width: 120px;
            padding: 0.5rem;
            border: 2px solid var(--border-color);
            border-radius: 6px;
            font-size: 1rem;
        }

        .status-select-wrapper {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .inventory-section {
            margin-top: 2rem;
        }

        .inventory-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }

        .inventory-table th,
        .inventory-table td {
            padding: 0.75rem;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
        }

        .inventory-table th {
            background: var(--secondary-color);
            color: white;
            font-weight: 600;
            font-size: 0.875rem;
            text-transform: uppercase;
        }

        .inventory-table tr:hover {
            background: var(--hover-bg);
        }

        .inventory-qty-input {
            width: 80px;
            padding: 0.5rem;
            border: 2px solid var(--border-color);
            border-radius: 6px;
            text-align: center;
        }

        .inventory-qty-input:focus {
            outline: none;
            border-color: var(--primary-color);
        }

        .variant-group {
            margin-bottom: 3rem;
        }

        .variant-card {
            display: flex;
            gap: 2rem;
            margin-bottom: 1.5rem;
            padding: 1.5rem;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            align-items: flex-start;
        }

        .variant-image-container {
            flex-shrink: 0;
            width: 200px;
            height: 200px;
            border-radius: 8px;
            overflow: hidden;
            border: 2px solid var(--border-color);
            background: rgba(255, 255, 255, 0.02);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .variant-image-large {
            width: 100%;
            height: 100%;
            object-fit: contain;
            display: block;
        }

        .variant-image-placeholder {
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(255, 255, 255, 0.05);
            color: var(--text-secondary);
            font-size: 0.875rem;
        }

        .variant-details {
            flex: 1;
        }

        .variant-name {
            margin: 0 0 1rem 0;
            font-size: 1.5rem;
            color: var(--text-primary);
            font-weight: 600;
        }

        .variant-meta {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }

        .variant-meta-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.5rem 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        }

        .variant-meta-item:last-child {
            border-bottom: none;
        }

        .meta-label {
            font-weight: 600;
            color: var(--text-secondary);
            min-width: 120px;
            font-size: 0.875rem;
        }

        .meta-value {
            color: var(--text-primary);
            font-family: 'Courier New', monospace;
            font-size: 0.875rem;
            background: rgba(255, 255, 255, 0.05);
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
        }

        @media (max-width: 768px) {
            .variant-card {
                flex-direction: column;
                gap: 1.5rem;
            }

            .variant-image-container {
                width: 100%;
                height: 250px;
            }
        }

        .inventory-note {
            padding: 1rem;
            background: rgba(251, 191, 36, 0.1);
            border: 1px solid rgba(251, 191, 36, 0.3);
            border-radius: 8px;
            color: rgba(251, 191, 36, 0.95);
            margin-top: 1rem;
            font-size: 0.875rem;
        }
    </style>
</head>
<body class="admin-dashboard">
    <!-- Mobile Menu Toggle -->
    <button class="mobile-menu-toggle" id="mobileMenuToggle" aria-label="Toggle menu">☰</button>
    
    <!-- Sidebar Overlay -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    
    <!-- Dynamic Sidebar - Automatically generated from admin directory -->
    <?php echo generateAdminSidebar($currentPage); ?>
    
    <!-- Main Content -->
    <main class="main-content">
        <header class="content-header">
            <h1>Product Management</h1>
        </header>
        
        <div class="content-body">
            <?php if ($message ?? false): ?>
                <div class="alert alert-<?php echo $messageType; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <?php if ($product): ?>
                <!-- Product Overview -->
                <div class="card">
                    <div class="card-header">
                        <h2>Product Overview</h2>
                    </div>
                    <div class="card-body">
                        <div class="product-header">
                            <div class="product-info">
                                <h3 style="margin-bottom: 0.5rem;"><?php echo htmlspecialchars($product['name']); ?></h3>
                                <p style="color: var(--text-secondary); margin-bottom: 1rem;">Product ID: #<?php echo $product['id']; ?></p>
                                
                                <!-- Price Editing -->
                                <div class="product-price-edit">
                                    <label style="font-weight: 600;">Price (RM):</label>
                                    <div class="price-input">
                                        <span>RM</span>
                                        <input type="number" 
                                               id="productPrice" 
                                               step="0.01" 
                                               min="0.01" 
                                               max="99999.99"
                                               value="<?php echo number_format($product['price'], 2, '.', ''); ?>"
                                               data-product-id="<?php echo $product['id']; ?>">
                                        <button type="button" class="btn btn-sm btn-primary" id="updatePriceBtn">Update Price</button>
                                    </div>
                                    <span id="priceMessage" style="font-size: 0.875rem;"></span>
                                </div>
                            </div>
                            
                            <!-- Status Selection -->
                            <div class="status-select-wrapper">
                                <label style="font-weight: 600;">Stock Status:</label>
                                <select id="productStatus" 
                                        class="order-status-select" 
                                        data-product-id="<?php echo $product['id']; ?>"
                                        data-current-status="<?php echo htmlspecialchars($product['stock_status']); ?>">
                                    <option value="in_stock" <?php echo $product['stock_status'] === 'in_stock' ? 'selected' : ''; ?>>In Stock</option>
                                    <option value="out_of_stock" <?php echo $product['stock_status'] === 'out_of_stock' ? 'selected' : ''; ?>>Out of Stock</option>
                                    <option value="pre_order" <?php echo $product['stock_status'] === 'pre_order' ? 'selected' : ''; ?>>Pre-Order</option>
                                </select>
                                <span id="statusMessage" style="font-size: 0.875rem;"></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Inventory Management -->
                <?php if ($product['stock_status'] === 'in_stock'): ?>
                    <div class="card mt-4 inventory-section">
                        <div class="card-header">
                            <h2>Inventory Management</h2>
                        </div>
                        <div class="card-body">
                            <p style="color: var(--text-secondary); margin-bottom: 1.5rem;">
                                Manage inventory quantities for each variant and size combination. 
                                Inventory automatically decreases when customers complete payment.
                            </p>

                            <?php foreach ($variants as $variant): ?>
                                <div class="variant-group">
                                    <div class="variant-card">
                                        <div class="variant-image-container">
                                            <img src="<?php echo e_media_url($variant['variant_image']); ?>" 
                                                 alt="<?php echo htmlspecialchars($variant['variant_name']); ?>"
                                                 class="variant-image-large"
                                                 onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
                                            <div class="variant-image-placeholder" style="display: none;">
                                                <span>No Image</span>
                                            </div>
                                        </div>
                                        <div class="variant-details">
                                            <h4 class="variant-name"><?php echo htmlspecialchars($variant['variant_name']); ?></h4>
                                            <div class="variant-meta">
                                                <div class="variant-meta-item">
                                                    <span class="meta-label">Variant Key:</span>
                                                    <span class="meta-value"><?php echo htmlspecialchars($variant['variant_key']); ?></span>
                                                </div>
                                                <div class="variant-meta-item">
                                                    <span class="meta-label">Variant ID:</span>
                                                    <span class="meta-value">#<?php echo $variant['id']; ?></span>
                                                </div>
                                                <div class="variant-meta-item">
                                                    <span class="meta-label">Image Path:</span>
                                                    <span class="meta-value"><?php echo htmlspecialchars($variant['variant_image']); ?></span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <table class="inventory-table">
                                        <thead>
                                            <tr>
                                                <th>Size</th>
                                                <th>Quantity</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($sizes as $size): 
                                                $currentQty = $inventory[$variant['id']][$size] ?? 0;
                                            ?>
                                                <tr data-variant-id="<?php echo $variant['id']; ?>" data-size="<?php echo $size; ?>">
                                                    <td><strong><?php echo $size; ?></strong></td>
                                                    <td>
                                                        <input type="number" 
                                                               class="inventory-qty-input" 
                                                               data-variant-id="<?php echo $variant['id']; ?>"
                                                               data-size="<?php echo $size; ?>"
                                                               min="0"
                                                               value="<?php echo $currentQty; ?>"
                                                               id="qty_<?php echo $variant['id']; ?>_<?php echo $size; ?>">
                                                    </td>
                                                    <td>
                                                        <button type="button" 
                                                                class="btn btn-sm btn-primary update-inventory-btn"
                                                                data-variant-id="<?php echo $variant['id']; ?>"
                                                                data-size="<?php echo $size; ?>"
                                                                data-variant-name="<?php echo htmlspecialchars($variant['variant_name']); ?>">
                                                            Update
                                                        </button>
                                                        <span class="inventory-message" 
                                                              id="msg_<?php echo $variant['id']; ?>_<?php echo $size; ?>"
                                                              style="margin-left: 0.5rem; font-size: 0.875rem;"></span>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="inventory-note">
                        <strong>Note:</strong> Inventory management is only available when product status is set to "In Stock". 
                        Change the stock status to "In Stock" to manage inventory quantities.
                    </div>
                <?php endif; ?>

            <?php else: ?>
                <div class="card">
                    <div class="card-body">
                        <p>No product found. Please run the database migration script:</p>
                        <code>php update_database_product_management.php</code>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <script src="<?php echo getAdminAsset('js/admin.js'); ?>"></script>
    <script src="<?php echo getAdminAsset('js/products.js'); ?>"></script>
</body>
</html>

