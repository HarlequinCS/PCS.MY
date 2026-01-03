# Product Management Feature Implementation Guide

This document provides comprehensive step-by-step instructions to add a Product Management feature to your existing admin panel.

## Table of Contents

1. [Overview](#overview)
2. [Database Schema](#database-schema)
3. [Step 1: Database Migration](#step-1-database-migration)
4. [Step 2: Admin Products Page](#step-2-admin-products-page)
5. [Step 3: AJAX Handlers](#step-3-ajax-handlers)
6. [Step 4: Update Order Flow](#step-4-update-order-flow)
7. [Step 5: Update Payment Callback](#step-5-update-payment-callback)
8. [Step 6: Navigation Integration](#step-6-navigation-integration)
9. [Security & Validation](#security--validation)
10. [Testing Checklist](#testing-checklist)

## Overview

The Product Management module allows administrators to:
- **Manage product prices** (affects new orders only, existing orders retain original price)
- **Set product stock status** (in_stock, out_of_stock, pre_order)
- **Manage inventory quantities** per variant and size combination
- **View comprehensive product dashboard** with all variants and sizes
- **Automatic inventory tracking** - quantities decrease on successful payment

## Database Schema

### Tables Structure

1. **products** - Main product information
   - `id` (INT, PRIMARY KEY)
   - `name` (VARCHAR 255)
   - `price` (DECIMAL 10,2)
   - `stock_status` (ENUM: 'in_stock', 'out_of_stock', 'pre_order')
   - `created_at`, `updated_at` (TIMESTAMP)

2. **product_variants** - Variant information (colors, images)
   - `id` (INT, PRIMARY KEY)
   - `product_id` (INT, FOREIGN KEY → products.id)
   - `variant_key` (VARCHAR 100) - e.g., 'hitamdepan', 'hijaufront'
   - `variant_name` (VARCHAR 255) - Display name
   - `variant_image` (VARCHAR 500) - Image path
   - `created_at`, `updated_at` (TIMESTAMP)

3. **product_inventory** - Inventory per variant + size
   - `id` (INT, PRIMARY KEY)
   - `variant_id` (INT, FOREIGN KEY → product_variants.id)
   - `size` (VARCHAR 10) - 'S', 'M', 'L', 'XL', 'XXL'
   - `quantity` (INT) - Available quantity
   - `created_at`, `updated_at` (TIMESTAMP)
   - UNIQUE KEY on (variant_id, size)

## Step 1: Database Migration

**File:** `update_database_product_management.php`

Run this script once to create the database tables and initialize the default product:

```bash
php update_database_product_management.php
```

The script will:
- Create `products`, `product_variants`, and `product_inventory` tables
- Initialize default product with variants matching your current setup
- Set initial inventory to 0 (admin can update via interface)

**Important:** Run this script before proceeding to Step 2.

## Step 2: Admin Products Page

**File:** `admin/products.php`

This page provides the main product management interface with:
- Product overview (name, price, stock status)
- Price editing
- Stock status dropdown
- Variant and size inventory management table
- Real-time updates via AJAX

### Key Features:
- Display all products
- Edit product price inline
- Change stock status with dropdown
- Manage inventory quantities per variant+size
- Show current inventory levels
- Only show inventory management when stock_status = 'in_stock'

## Step 3: AJAX Handlers

Create the following AJAX handlers in `admin/ajax/`:

### 3.1 Update Product Price
**File:** `admin/ajax/update_product_price.php`

- Updates product price in `products` table
- Validates price is positive number
- Returns success/error JSON response

### 3.2 Update Product Status
**File:** `admin/ajax/update_product_status.php`

- Updates `stock_status` in `products` table
- Validates status is one of: 'in_stock', 'out_of_stock', 'pre_order'
- Returns success/error JSON response

### 3.3 Update Inventory
**File:** `admin/ajax/update_inventory.php`

- Updates quantity in `product_inventory` table
- Creates inventory record if it doesn't exist (INSERT ON DUPLICATE KEY UPDATE)
- Validates quantity is non-negative
- Only works when product stock_status = 'in_stock'
- Returns success/error JSON response

## Step 4: Update Order Flow

**File:** `order-baju.php`

### 4.1 Load Product Data from Database

Replace hardcoded constants with database queries:

```php
// Get product from database
$pdo = getDBConnection();
$productStmt = $pdo->query("SELECT * FROM products LIMIT 1");
$product = $productStmt->fetch();

if (!$product) {
    // Fallback to defaults if no product found
    define('PRODUCT_NAME', 'Japanese Drill Half-Zip Workshirt');
    define('PRICE_PER_SHIRT', 50.00);
} else {
    define('PRODUCT_NAME', $product['name']);
    define('PRICE_PER_SHIRT', floatval($product['price']));
}

// Get variants from database
$variantsStmt = $pdo->query("
    SELECT variant_key, variant_name, variant_image 
    FROM product_variants 
    WHERE product_id = {$product['id']}
");
$dbVariants = $variantsStmt->fetchAll(PDO::FETCH_ASSOC);

// Build variants array
$variants = [];
foreach ($dbVariants as $v) {
    $variants[$v['variant_key']] = [
        'name' => $v['variant_name'],
        'image' => $v['variant_image'],
        'color' => '#1a1a1a' // Keep color for display
    ];
}
```

### 4.2 Check Stock Status Before Adding to Cart

In the "Add to Cart" handler:

```php
// Check product stock status
if ($product['stock_status'] === 'out_of_stock') {
    $message = 'This product is currently out of stock.';
    $messageType = 'error';
} elseif ($product['stock_status'] === 'in_stock') {
    // Check inventory quantity
    $inventoryStmt = $pdo->prepare("
        SELECT quantity 
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
        $message = "Only {$availableQty} units available for this variant and size.";
        $messageType = 'error';
    } else {
        // Proceed with adding to cart
    }
} else {
    // pre_order - allow ordering
    // Proceed with adding to cart
}
```

### 4.3 Display Availability Status

In the product display area, show availability:

```php
// Get inventory status for display
$inventoryStatus = [];
if ($product['stock_status'] === 'in_stock') {
    foreach ($variants as $variantKey => $variantData) {
        $inventoryStmt = $pdo->prepare("
            SELECT size, quantity 
            FROM product_inventory pi
            JOIN product_variants pv ON pi.variant_id = pv.id
            WHERE pv.variant_key = :variant_key
        ");
        $inventoryStmt->execute([':variant_key' => $variantKey]);
        $inventoryStatus[$variantKey] = $inventoryStmt->fetchAll(PDO::FETCH_KEY_PAIR);
    }
}
```

## Step 5: Update Payment Callback

**File:** `callback.php` (or your payment callback handler)

After successful payment (status_id == 1), decrease inventory:

```php
if ($statusId == 1) {
    // Payment successful
    try {
        $pdo = getDBConnection();
        
        // Update order status
        $updateStmt = $pdo->prepare("
            UPDATE orders 
            SET status = 'paid' 
            WHERE billExternalReferenceNo = :ref_no
        ");
        $updateStmt->execute([':ref_no' => $orderId]);
        
        // Get all orders for this reference (may have multiple items)
        $ordersStmt = $pdo->prepare("
            SELECT variant, size, quantity 
            FROM orders 
            WHERE billExternalReferenceNo = :ref_no
        ");
        $ordersStmt->execute([':ref_no' => $orderId]);
        $orderItems = $ordersStmt->fetchAll();
        
        // Decrease inventory for each item
        foreach ($orderItems as $item) {
            // Only decrease if product is in_stock
            $productStmt = $pdo->query("SELECT stock_status FROM products LIMIT 1");
            $product = $productStmt->fetch();
            
            if ($product && $product['stock_status'] === 'in_stock') {
                $decreaseStmt = $pdo->prepare("
                    UPDATE product_inventory pi
                    JOIN product_variants pv ON pi.variant_id = pv.id
                    SET pi.quantity = GREATEST(0, pi.quantity - :qty)
                    WHERE pv.variant_key = :variant_key AND pi.size = :size
                ");
                $decreaseStmt->execute([
                    ':qty' => intval($item['quantity']),
                    ':variant_key' => $item['variant'],
                    ':size' => $item['size']
                ]);
            }
        }
        
    } catch (PDOException $e) {
        error_log("Inventory decrease error: " . $e->getMessage());
        // Don't fail callback if inventory update fails
    }
}
```

## Step 6: Navigation Integration

Update all admin pages to include "Products" menu item:

**Files:** `admin/dashboard.php`, `admin/orders.php`, `admin/promotions.php`, `admin/emails.php`

Add to sidebar navigation:

```php
<a href="/admin/products.php" class="nav-item">
    <span class="nav-icon">🛍️</span>
    <span>Products</span>
</a>
```

## Step 7: JavaScript for Product Management

**File:** `admin/js/products.js`

Create JavaScript file to handle:
- Price update on change
- Status update on change
- Inventory quantity updates
- Real-time feedback and error handling

## Security & Validation

### Server-Side Validation

All AJAX handlers must:
1. Check admin authentication
2. Validate all inputs (type, range, format)
3. Prevent SQL injection (use prepared statements)
4. Prevent negative inventory values
5. Validate price ranges (e.g., > 0, < 10000)
6. Log all changes for audit trail

### Input Validation Rules

- **Price:** Must be positive number, 2 decimal places, max 99999.99
- **Stock Status:** Must be one of: 'in_stock', 'out_of_stock', 'pre_order'
- **Quantity:** Must be non-negative integer (0 or greater)
- **Variant Key:** Must match existing variant_key in database
- **Size:** Must be one of: 'S', 'M', 'L', 'XL', 'XXL'

## Testing Checklist

- [ ] Run database migration script
- [ ] Verify tables created correctly
- [ ] Access `/admin/products.php`
- [ ] Update product price and verify change
- [ ] Change stock status and verify
- [ ] Set stock status to 'in_stock'
- [ ] Update inventory quantities for variants/sizes
- [ ] Verify inventory management only shows when status = 'in_stock'
- [ ] Test order flow with in_stock product
- [ ] Test order flow with out_of_stock product (should be blocked)
- [ ] Test order flow with pre_order product
- [ ] Test inventory quantity validation (can't order more than available)
- [ ] Complete test payment and verify inventory decreases
- [ ] Verify existing orders retain original price
- [ ] Verify new orders use updated price

## Integration Notes

### Price Changes
- Price is stored in `orders` table at checkout time
- Changing product price only affects new orders
- Existing orders keep their original price

### Stock Status
- `out_of_stock`: Customers cannot add to cart
- `pre_order`: Customers can order (no inventory tracking)
- `in_stock`: Customers can order, inventory tracked and decreased

### Inventory Management
- Only available when `stock_status = 'in_stock'`
- Tracked per variant + size combination
- Automatically decreases on successful payment
- Prevents ordering when quantity = 0

## Next Steps

1. Run `update_database_product_management.php`
2. Implement `admin/products.php` page
3. Create AJAX handlers
4. Update `order-baju.php` to use database
5. Update payment callback to decrease inventory
6. Add navigation menu item
7. Test thoroughly
8. Deploy to production

---

**Note:** This implementation maintains backward compatibility with existing orders. All existing functionality continues to work while new features are added on top.
