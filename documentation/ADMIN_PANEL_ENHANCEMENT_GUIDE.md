# Admin Panel Enhancement Guide

This guide provides step-by-step instructions and code snippets to add order management, stock management, price adjustment, and promotion code management functionalities to your existing PHP admin panel.

## Table of Contents

1. [Database Schema Overview](#database-schema-overview)
2. [Order Status Update](#1-order-status-update)
3. [Stock Status Management](#2-stock-status-management)
4. [Price Adjustment](#3-price-adjustment)
5. [Promotion Code Management](#4-promotion-code-management)
6. [Security & Authentication](#5-security--authentication)
7. [UI Components](#6-ui-components)
8. [Complete Integration Example](#7-complete-integration-example)

---

## Database Schema Overview

### Orders Table Structure
```sql
-- Based on your current schema with enhancements
CREATE TABLE IF NOT EXISTS `orders` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(255) NOT NULL,
    `fullname` VARCHAR(255) NULL,
    `email` VARCHAR(255) NOT NULL,
    `phone` VARCHAR(50) NOT NULL,
    `address1` VARCHAR(255) NULL,
    `address2` VARCHAR(255) NULL,
    `address3` VARCHAR(255) NULL,
    `state` VARCHAR(100) NULL,
    `district` VARCHAR(100) NULL,
    `postcode` VARCHAR(10) NULL,
    `variant` VARCHAR(50) NOT NULL,
    `size` VARCHAR(10) NOT NULL,
    `quantity` INT(11) NOT NULL DEFAULT 1,
    `amount` DECIMAL(10,2) NOT NULL,
    `promo_code` VARCHAR(50) NULL,
    `discount_amount` DECIMAL(10,2) NULL DEFAULT 0.00,
    `order_type` ENUM('pickup', 'delivery') NULL DEFAULT 'delivery',
    `delivery_fee` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `final_amount` DECIMAL(10,2) NOT NULL,
    `status` VARCHAR(50) NOT NULL DEFAULT 'pending',
    `stock_status` VARCHAR(50) NULL DEFAULT 'ready',
    `billExternalReferenceNo` VARCHAR(255) NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_status` (`status`),
    INDEX `idx_email` (`email`),
    INDEX `idx_bill_ref` (`billExternalReferenceNo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### Promotions Table Structure
```sql
CREATE TABLE IF NOT EXISTS `promotions` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `code` VARCHAR(50) NOT NULL,
    `type` ENUM('percentage', 'fixed') NOT NULL,
    `value` DECIMAL(10,2) NOT NULL,
    `active` TINYINT(1) NOT NULL DEFAULT 1,
    `min_purchase` DECIMAL(10,2) NULL DEFAULT 0.00,
    `max_discount` DECIMAL(10,2) NULL DEFAULT NULL,
    `usage_limit` INT(11) NULL DEFAULT NULL,
    `used_count` INT(11) NOT NULL DEFAULT 0,
    `valid_from` DATETIME NULL,
    `valid_until` DATETIME NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_code` (`code`),
    INDEX `idx_active` (`active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### Stock Status Table (New - Optional)
If you want to manage stock per variant separately from orders:
```sql
CREATE TABLE IF NOT EXISTS `product_stock` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `variant` VARCHAR(50) NOT NULL,
    `stock_status` ENUM('ready', 'out_of_stock', 'pre_order') NOT NULL DEFAULT 'ready',
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `updated_by` VARCHAR(255) NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_variant` (`variant`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

---

## 1. Order Status Update

### Step 1.1: Create AJAX Handler for Status Update

Create `admin/ajax/update_order_status.php`:

```php
<?php
/**
 * AJAX Handler: Update Order Status
 * POST parameters: order_id, new_status
 */

require_once __DIR__ . '/../config.php'; // Uses existing admin/config.php
requireAdminLogin(); // From config.php

define('DB_CONFIG_LOADED', true);
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/db.php';

// Authentication is handled by requireAdminLogin() above

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$orderId = intval($_POST['order_id'] ?? 0);
$newStatus = trim($_POST['new_status'] ?? '');

// Validate status
$validStatuses = ['pending', 'packing', 'ready', 'shipped', 'paid', 'failed', 'cancelled'];
if (!in_array($newStatus, $validStatuses)) {
    echo json_encode(['success' => false, 'error' => 'Invalid status']);
    exit;
}

if ($orderId <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid order ID']);
    exit;
}

try {
    $pdo = getDBConnection();
    
    // Update order status
    $stmt = $pdo->prepare("
        UPDATE orders 
        SET status = :status, 
            updated_at = NOW()
        WHERE id = :id
    ");
    
    $stmt->execute([
        ':status' => $newStatus,
        ':id' => $orderId
    ]);
    
    if ($stmt->rowCount() > 0) {
        // Log the change (optional)
        error_log("Admin updated order #{$orderId} status to: {$newStatus}");
        
        echo json_encode([
            'success' => true,
            'message' => 'Order status updated successfully',
            'order_id' => $orderId,
            'new_status' => $newStatus
        ]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Order not found']);
    }
    
} catch (PDOException $e) {
    error_log('Order status update error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Database error']);
}
```

### Step 1.2: UI Component - Status Dropdown

Add this to your order listing or detail view:

```html
<!-- Order Status Dropdown -->
<select class="order-status-select" data-order-id="<?php echo $order['id']; ?>" 
        data-current-status="<?php echo htmlspecialchars($order['status']); ?>">
    <option value="pending" <?php echo $order['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
    <option value="packing" <?php echo $order['status'] === 'packing' ? 'selected' : ''; ?>>Packing</option>
    <option value="ready" <?php echo $order['status'] === 'ready' ? 'selected' : ''; ?>>Ready</option>
    <option value="shipped" <?php echo $order['status'] === 'shipped' ? 'selected' : ''; ?>>Shipped</option>
    <option value="paid" <?php echo $order['status'] === 'paid' ? 'selected' : ''; ?>>Paid</option>
    <option value="failed" <?php echo $order['status'] === 'failed' ? 'selected' : ''; ?>>Failed</option>
    <option value="cancelled" <?php echo $order['status'] === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
</select>
```

### Step 1.3: JavaScript for Status Update

```javascript
// Add to your admin panel JavaScript file
document.addEventListener('DOMContentLoaded', function() {
    const statusSelects = document.querySelectorAll('.order-status-select');
    
    statusSelects.forEach(select => {
        select.addEventListener('change', function() {
            const orderId = this.dataset.orderId;
            const newStatus = this.value;
            const currentStatus = this.dataset.currentStatus;
            
            // Confirm change
            if (!confirm(`Change order #${orderId} status from "${currentStatus}" to "${newStatus}"?`)) {
                this.value = currentStatus; // Revert
                return;
            }
            
            // Disable select during update
            this.disabled = true;
            
            // AJAX request
            const formData = new FormData();
            formData.append('order_id', orderId);
            formData.append('new_status', newStatus);
            
            fetch('ajax/update_order_status.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update UI
                    this.dataset.currentStatus = newStatus;
                    showNotification('Order status updated successfully', 'success');
                } else {
                    // Revert on error
                    this.value = currentStatus;
                    showNotification(data.error || 'Failed to update status', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                this.value = currentStatus;
                showNotification('Error updating status', 'error');
            })
            .finally(() => {
                this.disabled = false;
            });
        });
    });
});

// Notification function (adjust to match your UI)
function showNotification(message, type) {
    // Your notification implementation
    alert(message); // Simple fallback
}
```

---

## 2. Stock Status Management

### Step 2.1: Database Update for Stock Status in Orders

If you want to update stock_status in the orders table:

Create `admin/ajax/update_stock_status.php`:

```php
<?php
/**
 * AJAX Handler: Update Stock Status
 * POST parameters: order_id (or variant), new_stock_status
 */

require_once __DIR__ . '/../config.php';
requireAdminLogin();

define('DB_CONFIG_LOADED', true);
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$orderId = intval($_POST['order_id'] ?? 0);
$variant = trim($_POST['variant'] ?? '');
$newStockStatus = trim($_POST['new_stock_status'] ?? '');

// Validate stock status
$validStockStatuses = ['ready', 'out_of_stock', 'pre_order'];
if (!in_array($newStockStatus, $validStockStatuses)) {
    echo json_encode(['success' => false, 'error' => 'Invalid stock status']);
    exit;
}

try {
    $pdo = getDBConnection();
    
    if ($orderId > 0) {
        // Update specific order's stock status
        $stmt = $pdo->prepare("
            UPDATE orders 
            SET stock_status = :stock_status, 
                updated_at = NOW()
            WHERE id = :id
        ");
        
        $stmt->execute([
            ':stock_status' => $newStockStatus,
            ':id' => $orderId
        ]);
        
        if ($stmt->rowCount() > 0) {
            echo json_encode([
                'success' => true,
                'message' => 'Stock status updated successfully',
                'order_id' => $orderId,
                'new_stock_status' => $newStockStatus
            ]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Order not found']);
        }
    } elseif (!empty($variant)) {
        // Update all orders with this variant (bulk update)
        $stmt = $pdo->prepare("
            UPDATE orders 
            SET stock_status = :stock_status, 
                updated_at = NOW()
            WHERE variant = :variant
        ");
        
        $stmt->execute([
            ':stock_status' => $newStockStatus,
            ':variant' => $variant
        ]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Stock status updated for all orders with this variant',
            'variant' => $variant,
            'new_stock_status' => $newStockStatus,
            'affected_rows' => $stmt->rowCount()
        ]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Order ID or variant required']);
    }
    
} catch (PDOException $e) {
    error_log('Stock status update error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Database error']);
}
```

### Step 2.2: Prevent Ordering Out of Stock Items

Update your `order-baju.php` to check stock status:

```php
// Add this before processing add to cart
$variant = $_POST['variant'] ?? '';

// Check stock status
try {
    $pdo = getDBConnection();
    
    // Get latest stock status for this variant (from most recent order or stock table)
    $stmt = $pdo->prepare("
        SELECT stock_status 
        FROM orders 
        WHERE variant = :variant 
        ORDER BY created_at DESC 
        LIMIT 1
    ");
    $stmt->execute([':variant' => $variant]);
    $stockData = $stmt->fetch();
    
    if ($stockData && $stockData['stock_status'] === 'out_of_stock') {
        $message = 'This variant is currently out of stock';
        $messageType = 'error';
        // Don't process the order
    }
} catch (PDOException $e) {
    error_log('Stock check error: ' . $e->getMessage());
    // Continue with order if stock check fails (fail open)
}
```

### Step 2.3: UI Component - Stock Status Dropdown

```html
<!-- Stock Status Dropdown (in order detail or variant management) -->
<select class="stock-status-select" data-order-id="<?php echo $order['id']; ?>" 
        data-current-stock="<?php echo htmlspecialchars($order['stock_status'] ?? 'ready'); ?>">
    <option value="ready" <?php echo ($order['stock_status'] ?? 'ready') === 'ready' ? 'selected' : ''; ?>>Ready</option>
    <option value="out_of_stock" <?php echo ($order['stock_status'] ?? 'ready') === 'out_of_stock' ? 'selected' : ''; ?>>Out of Stock</option>
    <option value="pre_order" <?php echo ($order['stock_status'] ?? 'ready') === 'pre_order' ? 'selected' : ''; ?>>Pre-Order</option>
</select>
```

---

## 3. Price Adjustment

### Step 3.1: Create Price Adjustment Handler

Create `admin/ajax/adjust_order_price.php`:

```php
<?php
/**
 * AJAX Handler: Adjust Order Price
 * POST parameters: order_id, adjustment_amount, adjustment_type (add/subtract/override), reason
 */

require_once __DIR__ . '/../config.php';
requireAdminLogin();

define('DB_CONFIG_LOADED', true);
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$orderId = intval($_POST['order_id'] ?? 0);
$adjustmentAmount = floatval($_POST['adjustment_amount'] ?? 0);
$adjustmentType = trim($_POST['adjustment_type'] ?? 'override'); // add, subtract, override
$reason = trim($_POST['reason'] ?? '');

if ($orderId <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid order ID']);
    exit;
}

if (!in_array($adjustmentType, ['add', 'subtract', 'override'])) {
    echo json_encode(['success' => false, 'error' => 'Invalid adjustment type']);
    exit;
}

try {
    $pdo = getDBConnection();
    
    // Get current order data
    $stmt = $pdo->prepare("
        SELECT final_amount, amount, delivery_fee, discount_amount 
        FROM orders 
        WHERE id = :id
    ");
    $stmt->execute([':id' => $orderId]);
    $order = $stmt->fetch();
    
    if (!$order) {
        echo json_encode(['success' => false, 'error' => 'Order not found']);
        exit;
    }
    
    $currentFinalAmount = floatval($order['final_amount']);
    $currentAmount = floatval($order['amount']);
    
    // Calculate new final amount
    $newFinalAmount = $currentFinalAmount;
    
    if ($adjustmentType === 'add') {
        $newFinalAmount = $currentFinalAmount + $adjustmentAmount;
    } elseif ($adjustmentType === 'subtract') {
        $newFinalAmount = $currentFinalAmount - $adjustmentAmount;
    } else { // override
        $newFinalAmount = $adjustmentAmount;
    }
    
    // Validate: final amount cannot be negative
    if ($newFinalAmount < 0) {
        echo json_encode(['success' => false, 'error' => 'Final amount cannot be negative']);
        exit;
    }
    
    // Calculate proportional adjustment for amount (excluding delivery fee)
    $deliveryFee = floatval($order['delivery_fee']);
    $adjustedAmount = $newFinalAmount - $deliveryFee; // Remove delivery fee from final to get item amount
    
    // Update order
    $updateStmt = $pdo->prepare("
        UPDATE orders 
        SET final_amount = :final_amount,
            amount = :amount,
            updated_at = NOW()
        WHERE id = :id
    ");
    
    $updateStmt->execute([
        ':final_amount' => $newFinalAmount,
        ':amount' => max(0, $adjustedAmount), // Ensure amount is not negative
        ':id' => $orderId
    ]);
    
    // Log price adjustment (optional - you might want a price_adjustments table)
    error_log("Admin adjusted order #{$orderId}: {$adjustmentType} {$adjustmentAmount}, Reason: {$reason}, New total: {$newFinalAmount}");
    
    echo json_encode([
        'success' => true,
        'message' => 'Order price adjusted successfully',
        'order_id' => $orderId,
        'old_final_amount' => $currentFinalAmount,
        'new_final_amount' => $newFinalAmount,
        'adjustment' => $newFinalAmount - $currentFinalAmount
    ]);
    
} catch (PDOException $e) {
    error_log('Price adjustment error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Database error']);
}
```

### Step 3.2: UI Component - Price Adjustment Form

```html
<!-- Price Adjustment Modal/Form -->
<div class="price-adjustment-form" data-order-id="<?php echo $order['id']; ?>">
    <h3>Adjust Order Price</h3>
    <p>Current Total: <strong>RM <?php echo number_format($order['final_amount'], 2); ?></strong></p>
    
    <form id="priceAdjustForm">
        <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
        
        <div class="form-group">
            <label>Adjustment Type:</label>
            <select name="adjustment_type" id="adjustmentType" required>
                <option value="override">Set New Total (Override)</option>
                <option value="add">Add Amount</option>
                <option value="subtract">Subtract Amount</option>
            </select>
        </div>
        
        <div class="form-group">
            <label>Amount (RM):</label>
            <input type="number" name="adjustment_amount" step="0.01" min="0" required>
            <small id="adjustmentHint">Enter the new total amount</small>
        </div>
        
        <div class="form-group">
            <label>Reason (Optional):</label>
            <textarea name="reason" rows="3" placeholder="E.g., Special discount, correction..."></textarea>
        </div>
        
        <div class="form-actions">
            <button type="submit" class="btn-primary">Apply Adjustment</button>
            <button type="button" class="btn-secondary" onclick="closePriceAdjustForm()">Cancel</button>
        </div>
    </form>
    
    <div id="adjustmentPreview" style="margin-top: 1rem; padding: 1rem; background: #f5f5f5; display: none;">
        <strong>Preview:</strong>
        <span id="previewText"></span>
    </div>
</div>

<script>
document.getElementById('adjustmentType').addEventListener('change', function() {
    const hint = document.getElementById('adjustmentHint');
    if (this.value === 'override') {
        hint.textContent = 'Enter the new total amount';
    } else if (this.value === 'add') {
        hint.textContent = 'Enter amount to add';
    } else {
        hint.textContent = 'Enter amount to subtract';
    }
});

document.getElementById('priceAdjustForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    const currentTotal = <?php echo $order['final_amount']; ?>;
    const adjustmentType = formData.get('adjustment_type');
    const adjustmentAmount = parseFloat(formData.get('adjustment_amount'));
    
    // Calculate preview
    let newTotal = currentTotal;
    if (adjustmentType === 'add') {
        newTotal = currentTotal + adjustmentAmount;
    } else if (adjustmentType === 'subtract') {
        newTotal = currentTotal - adjustmentAmount;
    } else {
        newTotal = adjustmentAmount;
    }
    
    if (newTotal < 0) {
        alert('Final amount cannot be negative');
        return;
    }
    
    if (!confirm(`Adjust order price to RM ${newTotal.toFixed(2)}?`)) {
        return;
    }
    
    // Submit
    fetch('ajax/adjust_order_price.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Price adjusted successfully');
            location.reload(); // Reload to show updated price
        } else {
            alert(data.error || 'Failed to adjust price');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error adjusting price');
    });
});
</script>
```

---

## 4. Promotion Code Management

### Step 4.1: Create Promotion Code CRUD Handler

Create `admin/promotions.php`:

```php
<?php
/**
 * Promotion Code Management Page
 */

require_once __DIR__ . '/config.php';
requireAdminLogin();

define('DB_CONFIG_LOADED', true);
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/db.php';

$pdo = getDBConnection();

// Handle actions
$action = $_GET['action'] ?? 'list';
$message = '';
$messageType = '';

// Create/Update Promotion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $actionPost = $_POST['action'];
    
    if ($actionPost === 'create' || $actionPost === 'update') {
        $code = strtoupper(trim($_POST['code'] ?? ''));
        $type = $_POST['type'] ?? 'percentage';
        $value = floatval($_POST['value'] ?? 0);
        $active = isset($_POST['active']) ? 1 : 0;
        $minPurchase = floatval($_POST['min_purchase'] ?? 0);
        $maxDiscount = !empty($_POST['max_discount']) ? floatval($_POST['max_discount']) : null;
        $usageLimit = !empty($_POST['usage_limit']) ? intval($_POST['usage_limit']) : null;
        $validFrom = !empty($_POST['valid_from']) ? $_POST['valid_from'] : null;
        $validUntil = !empty($_POST['valid_until']) ? $_POST['valid_until'] : null;
        
        // Validation
        if (empty($code)) {
            $message = 'Promo code is required';
            $messageType = 'error';
        } elseif (!in_array($type, ['percentage', 'fixed'])) {
            $message = 'Invalid discount type';
            $messageType = 'error';
        } elseif ($value <= 0) {
            $message = 'Discount value must be greater than 0';
            $messageType = 'error';
        } else {
            try {
                if ($actionPost === 'create') {
                    // Check if code already exists
                    $checkStmt = $pdo->prepare("SELECT id FROM promotions WHERE code = :code");
                    $checkStmt->execute([':code' => $code]);
                    if ($checkStmt->fetch()) {
                        $message = 'Promo code already exists';
                        $messageType = 'error';
                    } else {
                        $stmt = $pdo->prepare("
                            INSERT INTO promotions (
                                code, type, value, active, min_purchase, max_discount,
                                usage_limit, valid_from, valid_until
                            ) VALUES (
                                :code, :type, :value, :active, :min_purchase, :max_discount,
                                :usage_limit, :valid_from, :valid_until
                            )
                        ");
                        $stmt->execute([
                            ':code' => $code,
                            ':type' => $type,
                            ':value' => $value,
                            ':active' => $active,
                            ':min_purchase' => $minPurchase,
                            ':max_discount' => $maxDiscount,
                            ':usage_limit' => $usageLimit,
                            ':valid_from' => $validFrom ?: null,
                            ':valid_until' => $validUntil ?: null
                        ]);
                        $message = 'Promotion code created successfully';
                        $messageType = 'success';
                    }
                } else { // update
                    $id = intval($_POST['id']);
                    $stmt = $pdo->prepare("
                        UPDATE promotions SET
                            code = :code,
                            type = :type,
                            value = :value,
                            active = :active,
                            min_purchase = :min_purchase,
                            max_discount = :max_discount,
                            usage_limit = :usage_limit,
                            valid_from = :valid_from,
                            valid_until = :valid_until,
                            updated_at = NOW()
                        WHERE id = :id
                    ");
                    $stmt->execute([
                        ':code' => $code,
                        ':type' => $type,
                        ':value' => $value,
                        ':active' => $active,
                        ':min_purchase' => $minPurchase,
                        ':max_discount' => $maxDiscount,
                        ':usage_limit' => $usageLimit,
                        ':valid_from' => $validFrom ?: null,
                        ':valid_until' => $validUntil ?: null,
                        ':id' => $id
                    ]);
                    $message = 'Promotion code updated successfully';
                    $messageType = 'success';
                }
            } catch (PDOException $e) {
                error_log('Promotion save error: ' . $e->getMessage());
                $message = 'Error saving promotion code';
                $messageType = 'error';
            }
        }
    } elseif ($actionPost === 'delete') {
        $id = intval($_POST['id'] ?? 0);
        if ($id > 0) {
            try {
                $stmt = $pdo->prepare("DELETE FROM promotions WHERE id = :id");
                $stmt->execute([':id' => $id]);
                $message = 'Promotion code deleted successfully';
                $messageType = 'success';
            } catch (PDOException $e) {
                error_log('Promotion delete error: ' . $e->getMessage());
                $message = 'Error deleting promotion code';
                $messageType = 'error';
            }
        }
    }
}

// Get promotion for editing
$promotion = null;
if ($action === 'edit' && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $stmt = $pdo->prepare("SELECT * FROM promotions WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $promotion = $stmt->fetch();
    if (!$promotion) {
        $action = 'list';
    }
}

// Get all promotions
$promotions = [];
$stmt = $pdo->query("SELECT * FROM promotions ORDER BY created_at DESC");
$promotions = $stmt->fetchAll();

// Include your admin header/HTML structure here
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Promotion Code Management - Admin Panel</title>
    <style>
        /* Add your admin panel styles */
        .promo-table { width: 100%; border-collapse: collapse; }
        .promo-table th, .promo-table td { padding: 0.75rem; border: 1px solid #ddd; text-align: left; }
        .promo-table th { background: #f5f5f5; }
        .btn { padding: 0.5rem 1rem; border: none; cursor: pointer; border-radius: 4px; }
        .btn-primary { background: #007bff; color: white; }
        .btn-danger { background: #dc3545; color: white; }
        .btn-success { background: #28a745; color: white; }
        .status-active { color: #28a745; font-weight: bold; }
        .status-inactive { color: #dc3545; }
    </style>
</head>
<body>
    <h1>Promotion Code Management</h1>
    
    <?php if ($message): ?>
        <div class="message message-<?php echo $messageType; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>
    
    <?php if ($action === 'list' || $action === 'edit'): ?>
        <div style="margin-bottom: 1rem;">
            <a href="?action=create" class="btn btn-primary">Create New Promotion</a>
        </div>
        
        <table class="promo-table">
            <thead>
                <tr>
                    <th>Code</th>
                    <th>Type</th>
                    <th>Value</th>
                    <th>Min Purchase</th>
                    <th>Max Discount</th>
                    <th>Usage Limit</th>
                    <th>Used</th>
                    <th>Valid From</th>
                    <th>Valid Until</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($promotions as $promo): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($promo['code']); ?></strong></td>
                        <td><?php echo ucfirst($promo['type']); ?></td>
                        <td>
                            <?php 
                            if ($promo['type'] === 'percentage') {
                                echo number_format($promo['value'], 0) . '%';
                            } else {
                                echo 'RM ' . number_format($promo['value'], 2);
                            }
                            ?>
                        </td>
                        <td>RM <?php echo number_format($promo['min_purchase'] ?? 0, 2); ?></td>
                        <td><?php echo $promo['max_discount'] ? 'RM ' . number_format($promo['max_discount'], 2) : '-'; ?></td>
                        <td><?php echo $promo['usage_limit'] ?? 'Unlimited'; ?></td>
                        <td><?php echo $promo['used_count']; ?></td>
                        <td><?php echo $promo['valid_from'] ? date('Y-m-d H:i', strtotime($promo['valid_from'])) : '-'; ?></td>
                        <td><?php echo $promo['valid_until'] ? date('Y-m-d H:i', strtotime($promo['valid_until'])) : '-'; ?></td>
                        <td>
                            <span class="<?php echo $promo['active'] ? 'status-active' : 'status-inactive'; ?>">
                                <?php echo $promo['active'] ? 'Active' : 'Inactive'; ?>
                            </span>
                        </td>
                        <td>
                            <a href="?action=edit&id=<?php echo $promo['id']; ?>" class="btn btn-primary">Edit</a>
                            <form method="POST" style="display: inline;" onsubmit="return confirm('Delete this promotion code?');">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?php echo $promo['id']; ?>">
                                <button type="submit" class="btn btn-danger">Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
    
    <?php if ($action === 'create' || $action === 'edit'): ?>
        <h2><?php echo $action === 'create' ? 'Create' : 'Edit'; ?> Promotion Code</h2>
        <form method="POST" style="max-width: 600px;">
            <input type="hidden" name="action" value="<?php echo $action; ?>">
            <?php if ($action === 'edit'): ?>
                <input type="hidden" name="id" value="<?php echo $promotion['id']; ?>">
            <?php endif; ?>
            
            <div style="margin-bottom: 1rem;">
                <label>Promo Code:</label>
                <input type="text" name="code" value="<?php echo htmlspecialchars($promotion['code'] ?? ''); ?>" 
                       required style="width: 100%; padding: 0.5rem;">
            </div>
            
            <div style="margin-bottom: 1rem;">
                <label>Discount Type:</label>
                <select name="type" required style="width: 100%; padding: 0.5rem;">
                    <option value="percentage" <?php echo ($promotion['type'] ?? '') === 'percentage' ? 'selected' : ''; ?>>Percentage</option>
                    <option value="fixed" <?php echo ($promotion['type'] ?? '') === 'fixed' ? 'selected' : ''; ?>>Fixed Amount</option>
                </select>
            </div>
            
            <div style="margin-bottom: 1rem;">
                <label>Discount Value:</label>
                <input type="number" name="value" step="0.01" min="0.01" 
                       value="<?php echo $promotion['value'] ?? ''; ?>" required 
                       style="width: 100%; padding: 0.5rem;">
                <small>For percentage: enter number (e.g., 10 for 10%). For fixed: enter amount in RM.</small>
            </div>
            
            <div style="margin-bottom: 1rem;">
                <label>Minimum Purchase (RM):</label>
                <input type="number" name="min_purchase" step="0.01" min="0" 
                       value="<?php echo $promotion['min_purchase'] ?? 0; ?>" 
                       style="width: 100%; padding: 0.5rem;">
            </div>
            
            <div style="margin-bottom: 1rem;">
                <label>Max Discount (RM) - Optional:</label>
                <input type="number" name="max_discount" step="0.01" min="0" 
                       value="<?php echo $promotion['max_discount'] ?? ''; ?>" 
                       style="width: 100%; padding: 0.5rem;">
                <small>Only applies to percentage discounts</small>
            </div>
            
            <div style="margin-bottom: 1rem;">
                <label>Usage Limit - Optional:</label>
                <input type="number" name="usage_limit" min="1" 
                       value="<?php echo $promotion['usage_limit'] ?? ''; ?>" 
                       style="width: 100%; padding: 0.5rem;">
                <small>Leave empty for unlimited</small>
            </div>
            
            <div style="margin-bottom: 1rem;">
                <label>Valid From (Optional):</label>
                <input type="datetime-local" name="valid_from" 
                       value="<?php echo $promotion['valid_from'] ? date('Y-m-d\TH:i', strtotime($promotion['valid_from'])) : ''; ?>" 
                       style="width: 100%; padding: 0.5rem;">
            </div>
            
            <div style="margin-bottom: 1rem;">
                <label>Valid Until (Optional):</label>
                <input type="datetime-local" name="valid_until" 
                       value="<?php echo $promotion['valid_until'] ? date('Y-m-d\TH:i', strtotime($promotion['valid_until'])) : ''; ?>" 
                       style="width: 100%; padding: 0.5rem;">
            </div>
            
            <div style="margin-bottom: 1rem;">
                <label>
                    <input type="checkbox" name="active" value="1" 
                           <?php echo ($promotion['active'] ?? 1) ? 'checked' : ''; ?>>
                    Active
                </label>
            </div>
            
            <div>
                <button type="submit" class="btn btn-success">Save</button>
                <a href="?" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    <?php endif; ?>
</body>
</html>
```

---

## 5. Security & Authentication

### Step 5.1: Admin Authentication

Your existing admin panel already has authentication in `admin/config.php`:

- `isAdminLoggedIn()` - Checks if admin is logged in
- `requireAdminLogin()` - Redirects if not logged in
- `adminLogin($username, $password)` - Login function
- `adminLogout()` - Logout function

**All new admin files should use:**
```php
require_once __DIR__ . '/../config.php';
requireAdminLogin();
```

**Note**: If you want to add action logging, you can add this function to `admin/config.php`:
```php
/**
 * Log admin action (optional enhancement)
 */
function logAdminAction($action, $details = '') {
    error_log(sprintf(
        "Admin Action: User %s, Action: %s, Details: %s, IP: %s, Time: %s",
        $_SESSION['admin_username'] ?? 'unknown',
        $action,
        $details,
        $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        date('Y-m-d H:i:s')
    ));
}
```

### Step 5.2: Add CSRF Protection (Optional but Recommended)

Add to your forms:

```php
// Generate CSRF token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// In forms
<input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

// In handlers
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Invalid security token']);
    exit;
}
```

---

## 6. UI Components

### Step 6.1: Add Status Badge Styling

Add to your admin CSS:

```css
/* Status Badges */
.status-badge {
    display: inline-block;
    padding: 0.25rem 0.75rem;
    border-radius: 12px;
    font-size: 0.875rem;
    font-weight: 600;
    text-transform: uppercase;
}

.status-pending { background: #ffc107; color: #000; }
.status-packing { background: #17a2b8; color: #fff; }
.status-ready { background: #28a745; color: #fff; }
.status-shipped { background: #007bff; color: #fff; }
.status-paid { background: #28a745; color: #fff; }
.status-failed { background: #dc3545; color: #fff; }
.status-cancelled { background: #6c757d; color: #fff; }

/* Stock Status Badges */
.stock-ready { background: #28a745; color: #fff; }
.stock-out_of_stock { background: #dc3545; color: #fff; }
.stock-pre_order { background: #ffc107; color: #000; }

/* Order Status Select */
.order-status-select {
    padding: 0.5rem;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 0.875rem;
}

/* Responsive Table */
@media (max-width: 768px) {
    .promo-table {
        display: block;
        overflow-x: auto;
    }
}
```

---

## 7. Complete Integration Example

### Step 7.1: Sample Admin Order List Page

Create `admin/orders.php` (example - integrates with existing admin panel):

```php
<?php
require_once __DIR__ . '/config.php';
requireAdminLogin();

define('DB_CONFIG_LOADED', true);
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/db.php';

$pdo = getDBConnection();

// Get orders with filters
$statusFilter = $_GET['status'] ?? '';
$searchQuery = $_GET['search'] ?? '';

$sql = "SELECT * FROM orders WHERE 1=1";
$params = [];

if ($statusFilter) {
    $sql .= " AND status = :status";
    $params[':status'] = $statusFilter;
}

if ($searchQuery) {
    $sql .= " AND (email LIKE :search OR fullname LIKE :search OR billExternalReferenceNo LIKE :search)";
    $params[':search'] = '%' . $searchQuery . '%';
}

$sql .= " ORDER BY created_at DESC LIMIT 100";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$orders = $stmt->fetchAll();

// Group orders by billExternalReferenceNo (same order)
$orderGroups = [];
foreach ($orders as $order) {
    $refNo = $order['billExternalReferenceNo'];
    if (!isset($orderGroups[$refNo])) {
        $orderGroups[$refNo] = [];
    }
    $orderGroups[$refNo][] = $order;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Orders - Admin Panel</title>
    <link rel="stylesheet" href="admin-styles.css"> <!-- Your admin CSS -->
</head>
<body>
    <h1>Orders Management</h1>
    
    <!-- Filters -->
    <div class="filters">
        <form method="GET">
            <select name="status">
                <option value="">All Statuses</option>
                <option value="pending" <?php echo $statusFilter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                <option value="packing" <?php echo $statusFilter === 'packing' ? 'selected' : ''; ?>>Packing</option>
                <option value="ready" <?php echo $statusFilter === 'ready' ? 'selected' : ''; ?>>Ready</option>
                <option value="shipped" <?php echo $statusFilter === 'shipped' ? 'selected' : ''; ?>>Shipped</option>
                <option value="paid" <?php echo $statusFilter === 'paid' ? 'selected' : ''; ?>>Paid</option>
            </select>
            <input type="text" name="search" placeholder="Search by email/name/ref..." value="<?php echo htmlspecialchars($searchQuery); ?>">
            <button type="submit">Filter</button>
        </form>
    </div>
    
    <!-- Orders List -->
    <table class="orders-table">
        <thead>
            <tr>
                <th>Order Ref</th>
                <th>Customer</th>
                <th>Items</th>
                <th>Total</th>
                <th>Status</th>
                <th>Stock Status</th>
                <th>Order Type</th>
                <th>Date</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($orderGroups as $refNo => $items): 
                $firstItem = $items[0];
                $totalAmount = array_sum(array_column($items, 'final_amount'));
            ?>
                <tr>
                    <td><?php echo htmlspecialchars($refNo); ?></td>
                    <td>
                        <?php echo htmlspecialchars($firstItem['fullname'] ?: $firstItem['name']); ?><br>
                        <small><?php echo htmlspecialchars($firstItem['email']); ?></small>
                    </td>
                    <td>
                        <?php foreach ($items as $item): ?>
                            <?php echo htmlspecialchars($item['variant']); ?> - 
                            <?php echo htmlspecialchars($item['size']); ?> x<?php echo $item['quantity']; ?><br>
                        <?php endforeach; ?>
                    </td>
                    <td>RM <?php echo number_format($totalAmount, 2); ?></td>
                    <td>
                        <select class="order-status-select" data-order-id="<?php echo $firstItem['id']; ?>" 
                                data-current-status="<?php echo htmlspecialchars($firstItem['status']); ?>">
                            <option value="pending" <?php echo $firstItem['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="packing" <?php echo $firstItem['status'] === 'packing' ? 'selected' : ''; ?>>Packing</option>
                            <option value="ready" <?php echo $firstItem['status'] === 'ready' ? 'selected' : ''; ?>>Ready</option>
                            <option value="shipped" <?php echo $firstItem['status'] === 'shipped' ? 'selected' : ''; ?>>Shipped</option>
                            <option value="paid" <?php echo $firstItem['status'] === 'paid' ? 'selected' : ''; ?>>Paid</option>
                            <option value="failed" <?php echo $firstItem['status'] === 'failed' ? 'selected' : ''; ?>>Failed</option>
                            <option value="cancelled" <?php echo $firstItem['status'] === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                        </select>
                    </td>
                    <td>
                        <select class="stock-status-select" data-order-id="<?php echo $firstItem['id']; ?>" 
                                data-current-stock="<?php echo htmlspecialchars($firstItem['stock_status'] ?? 'ready'); ?>">
                            <option value="ready" <?php echo ($firstItem['stock_status'] ?? 'ready') === 'ready' ? 'selected' : ''; ?>>Ready</option>
                            <option value="out_of_stock" <?php echo ($firstItem['stock_status'] ?? 'ready') === 'out_of_stock' ? 'selected' : ''; ?>>Out of Stock</option>
                            <option value="pre_order" <?php echo ($firstItem['stock_status'] ?? 'ready') === 'pre_order' ? 'selected' : ''; ?>>Pre-Order</option>
                        </select>
                    </td>
                    <td><?php echo ucfirst($firstItem['order_type'] ?? 'delivery'); ?></td>
                    <td><?php echo date('Y-m-d H:i', strtotime($firstItem['created_at'])); ?></td>
                    <td>
                        <a href="order_detail.php?id=<?php echo $firstItem['id']; ?>">View</a> |
                        <a href="#" onclick="openPriceAdjust(<?php echo $firstItem['id']; ?>, <?php echo $totalAmount; ?>); return false;">Adjust Price</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    
    <script src="admin-orders.js"></script> <!-- Your JavaScript -->
</body>
</html>
```

---

## Implementation Checklist

- [x] Authentication system already exists (`admin/config.php`) ✅
- [ ] Create `admin/ajax/update_order_status.php`
- [ ] Create `admin/ajax/update_stock_status.php`
- [ ] Create `admin/ajax/adjust_order_price.php`
- [ ] Create `admin/promotions.php`
- [ ] Add status dropdowns to order listing
- [ ] Add stock status dropdowns
- [ ] Add price adjustment form/modal
- [ ] Add JavaScript handlers for all AJAX calls
- [ ] Update `order-baju.php` to check stock status
- [ ] Test all functionalities
- [ ] Add error handling and validation
- [ ] Style UI components
- [ ] Test security (authentication, validation)

---

## Notes

1. **Database Compatibility**: The code assumes your database structure matches the schema described. Adjust column names if needed.

2. **Authentication**: Adapt `isAdminLoggedIn()` to match your existing authentication system.

3. **Error Handling**: Add comprehensive error handling and user feedback.

4. **UI Framework**: If you use a CSS framework (Bootstrap, Tailwind, etc.), adjust the HTML/CSS accordingly.

5. **Testing**: Test each functionality individually before integrating.

6. **Security**: Always validate and sanitize all inputs server-side.

---

**Last Updated**: January 2025
**Version**: 1.0.0

