# Order System Update Guide

**Updating Existing PHP E-Commerce System: Pickup/Delivery + English Translation**

This guide provides step-by-step instructions to update the existing ordering system with pickup/delivery options and English translation, while maintaining backward compatibility.

---

## Table of Contents

1. [Overview](#overview)
2. [Step 1: Database Updates](#step-1-database-updates)
3. [Step 2: Update Order Creation Logic](#step-2-update-order-creation-logic)
4. [Step 3: Update Checkout Form (English Translation)](#step-3-update-checkout-form-english-translation)
5. [Step 4: Add Pickup/Delivery Selection](#step-4-add-pickupdelivery-selection)
6. [Step 5: Update Session Management](#step-5-update-session-management)
7. [Step 6: Update Order Calculation Logic](#step-6-update-order-calculation-logic)
8. [Step 7: Update Order Summary Display](#step-7-update-order-summary-display)
9. [Step 8: Update Order-Baju Page (English)](#step-8-update-order-baju-page-english)
10. [Step 9: Testing Checklist](#step-9-testing-checklist)
11. [Step 10: Admin Panel Preparation Notes](#step-10-admin-panel-preparation-notes)

---

## Overview

### Changes Summary

1. **Pickup/Delivery Option**: Add selection on checkout with RM8 delivery fee
2. **Database Schema**: Add `order_type`, `delivery_fee`, `final_amount` columns
3. **English Translation**: Convert all Malay text to English
4. **Session Updates**: Store order_type in session
5. **Calculation Logic**: Update to include delivery fee in final total
6. **Backward Compatibility**: Ensure existing orders continue to work

### Files to Modify

- `checkout.php` - Main checkout form and processing
- `order-baju.php` - Product selection page
- Database: `orders` table (ALTER TABLE)

### Constants to Define

```php
define('DELIVERY_FEE', 8.00); // RM8 delivery fee
```

---

## Step 1: Database Updates

### 1.1 Create SQL Migration Script

Create a file `database_updates.sql`:

```sql
-- ============================================
-- Order System Update: Pickup/Delivery + Final Amount
-- ============================================
-- Run this SQL script to update the orders table
-- Backward compatible: Sets defaults for existing records

-- Add order_type column (ENUM: 'pickup', 'delivery')
ALTER TABLE `orders` 
ADD COLUMN `order_type` ENUM('pickup', 'delivery') NULL DEFAULT 'delivery' 
COMMENT 'Order type: pickup or delivery' 
AFTER `postcode`;

-- Add delivery_fee column
ALTER TABLE `orders` 
ADD COLUMN `delivery_fee` DECIMAL(10,2) NOT NULL DEFAULT 0.00 
COMMENT 'Delivery fee in RM (0 for pickup)' 
AFTER `order_type`;

-- Add final_amount column (total after discount and delivery)
ALTER TABLE `orders` 
ADD COLUMN `final_amount` DECIMAL(10,2) NULL 
COMMENT 'Final amount including delivery fee and discount' 
AFTER `amount`;

-- Update existing records: Set final_amount = amount (for backward compatibility)
UPDATE `orders` 
SET `final_amount` = `amount`, 
    `order_type` = 'delivery',
    `delivery_fee` = 0.00
WHERE `final_amount` IS NULL;

-- Make final_amount NOT NULL after updating existing records
ALTER TABLE `orders` 
MODIFY COLUMN `final_amount` DECIMAL(10,2) NOT NULL 
COMMENT 'Final amount including delivery fee and discount';

-- Verify the changes
DESCRIBE `orders`;
```

### 1.2 Run the SQL Script

**Option A: Via phpMyAdmin**
1. Log into phpMyAdmin
2. Select your database
3. Go to SQL tab
4. Paste and execute the SQL script above

**Option B: Via Command Line**
```bash
mysql -u your_username -p your_database_name < database_updates.sql
```

**Option C: Via PHP Script**
Create `update_database_orders.php`:

```php
<?php
/**
 * Database Update Script for Orders Table
 * Run this once to add new columns
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/db.php';

try {
    $pdo = getDBConnection();
    
    echo "Starting database update...\n\n";
    
    // Add order_type column
    echo "Adding order_type column...\n";
    $pdo->exec("ALTER TABLE `orders` 
        ADD COLUMN `order_type` ENUM('pickup', 'delivery') NULL DEFAULT 'delivery' 
        COMMENT 'Order type: pickup or delivery' 
        AFTER `postcode`");
    echo "✓ order_type column added\n\n";
    
    // Add delivery_fee column
    echo "Adding delivery_fee column...\n";
    $pdo->exec("ALTER TABLE `orders` 
        ADD COLUMN `delivery_fee` DECIMAL(10,2) NOT NULL DEFAULT 0.00 
        COMMENT 'Delivery fee in RM (0 for pickup)' 
        AFTER `order_type`");
    echo "✓ delivery_fee column added\n\n";
    
    // Add final_amount column
    echo "Adding final_amount column...\n";
    $pdo->exec("ALTER TABLE `orders` 
        ADD COLUMN `final_amount` DECIMAL(10,2) NULL 
        COMMENT 'Final amount including delivery fee and discount' 
        AFTER `amount`");
    echo "✓ final_amount column added\n\n";
    
    // Update existing records
    echo "Updating existing records...\n";
    $pdo->exec("UPDATE `orders` 
        SET `final_amount` = `amount`, 
            `order_type` = 'delivery',
            `delivery_fee` = 0.00
        WHERE `final_amount` IS NULL");
    echo "✓ Existing records updated\n\n";
    
    // Make final_amount NOT NULL
    echo "Making final_amount NOT NULL...\n";
    $pdo->exec("ALTER TABLE `orders` 
        MODIFY COLUMN `final_amount` DECIMAL(10,2) NOT NULL 
        COMMENT 'Final amount including delivery fee and discount'");
    echo "✓ final_amount set to NOT NULL\n\n";
    
    echo "✓ Database update completed successfully!\n";
    
} catch (PDOException $e) {
    // Check if column already exists (error 1060)
    if ($e->getCode() == 42000 || strpos($e->getMessage(), 'Duplicate column') !== false) {
        echo "⚠ Column may already exist. Error: " . $e->getMessage() . "\n";
        echo "You can safely ignore this if columns are already added.\n";
    } else {
        echo "✗ Error: " . $e->getMessage() . "\n";
        exit(1);
    }
}
```

Run: `php update_database_orders.php`

---

## Step 2: Update Order Creation Logic

### 2.1 Add Delivery Fee Constant

In `checkout.php`, add after the product configuration section (around line 40):

```php
// Delivery Configuration
define('DELIVERY_FEE', 8.00); // RM8 delivery fee for delivery option
```

### 2.2 Update Order Calculation Section

In `checkout.php`, find the section that calculates cart totals (around line 74-90). Update it:

```php
// ============================================
// STEP 3: Calculate Cart Totals
// ============================================
$cart = $_SESSION['cart'];
$cartCount = count($cart);
$cartSubtotal = 0;
foreach ($cart as $item) {
    $cartSubtotal += $item['subtotal'];
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
```

### 2.3 Update Promotion Code Validation

In the promotion code validation section (around line 143-157), update discount calculation to work with delivery fee:

```php
// Calculate discount
$discount = 0;
if ($promotion['type'] === 'percentage') {
    // Calculate discount on subtotal only (before delivery fee)
    $discount = ($cartSubtotal * $promotion['value']) / 100;
    // Apply max discount if set
    if ($promotion['max_discount'] !== null && $discount > $promotion['max_discount']) {
        $discount = $promotion['max_discount'];
    }
} else { // fixed
    $discount = $promotion['value'];
    // Don't allow discount more than subtotal
    if ($discount > $cartSubtotal) {
        $discount = $cartSubtotal;
    }
}

// Calculate final amount with delivery fee
$finalAmountWithDelivery = $cartSubtotal + $deliveryFee - $discount;
// Ensure final amount is not negative
if ($finalAmountWithDelivery < 0) {
    $finalAmountWithDelivery = 0;
}

header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'code' => $promotion['code'],
    'type' => $promotion['type'],
    'value' => $promotion['value'],
    'discount' => round($discount, 2),
    'subtotal' => $cartSubtotal,
    'delivery_fee' => $deliveryFee,
    'final' => round($finalAmountWithDelivery, 2)
]);
exit;
```

### 2.4 Update Form Submission Handler

In the form submission section (around line 184), add order_type handling:

```php
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'checkout') {
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
            $errors[] = 'Address Line 1 is required for delivery';
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
    
    // Validation (rest of validation code remains the same, but update error messages to English)
    if (empty($fullname)) {
        $errors[] = 'Full name is required';
    }
    
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Valid email is required';
    }
    
    if (empty($phone)) {
        $errors[] = 'Phone number is required';
    }
    
    // ... rest of validation with English messages
    
    // Calculate final amounts
    $discountAmount = 0.00;
    $promoCode = '';
    
    if (!empty($promoCodeInput)) {
        // Validate promo code and get discount (existing logic)
        // ... promo code validation code ...
        // $discountAmount = calculated discount
        // $promoCode = validated code
    }
    
    // Calculate final amount: subtotal + delivery_fee - discount
    $finalAmount = $cartSubtotal + $deliveryFee - $discountAmount;
    if ($finalAmount < 0) {
        $finalAmount = 0;
    }
    
    // Store order_type in session
    $_SESSION['order_type'] = $orderType;
    
    // ... continue with order creation ...
}
```

### 2.5 Update Database INSERT Statement

Find the INSERT INTO orders statement (around line 350-400) and update it:

```php
// Prepare order data for database
$orderData = [
    ':name' => $fullname, // Backward compatibility
    ':fullname' => $fullname,
    ':email' => $email,
    ':phone' => $phone,
    ':address1' => $address1,
    ':address2' => $address2,
    ':address3' => $address3,
    ':state' => $state,
    ':district' => $district,
    ':postcode' => $postcode,
    ':variant' => $cartItem['variant'],
    ':size' => $cartItem['size'],
    ':quantity' => $cartItem['quantity'],
    ':amount' => $finalAmount, // This is the final amount
    ':promo_code' => $promoCode,
    ':discount_amount' => $discountAmount,
    ':order_type' => $orderType,
    ':delivery_fee' => $deliveryFee,
    ':final_amount' => $finalAmount,
    ':billExternalReferenceNo' => $billExternalReferenceNo,
    ':status' => 'pending'
];

$sql = "INSERT INTO `orders` (
    `name`, `fullname`, `email`, `phone`,
    `address1`, `address2`, `address3`, `state`, `district`, `postcode`,
    `variant`, `size`, `quantity`,
    `amount`, `promo_code`, `discount_amount`,
    `order_type`, `delivery_fee`, `final_amount`,
    `billExternalReferenceNo`, `status`
) VALUES (
    :name, :fullname, :email, :phone,
    :address1, :address2, :address3, :state, :district, :postcode,
    :variant, :size, :quantity,
    :amount, :promo_code, :discount_amount,
    :order_type, :delivery_fee, :final_amount,
    :billExternalReferenceNo, :status
)";

$stmt = $pdo->prepare($sql);
$stmt->execute($orderData);
```

---

## Step 3: Update Checkout Form (English Translation)

### 3.1 Form Field Labels - Find and Replace

In `checkout.php`, find the HTML form section and update all labels:

**Find:**
```html
<label for="fullname">Nama Penuh</label>
```

**Replace:**
```html
<label for="fullname">Full Name</label>
```

**Complete Translation Map:**

| Malay | English |
|-------|---------|
| Nama Penuh | Full Name |
| Email | Email (no change) |
| Nombor Telefon | Phone Number |
| Alamat Penghantaran | Delivery Address |
| Alamat Baris 1 | Address Line 1 |
| Alamat Baris 2 (Pilihan) | Address Line 2 (Optional) |
| Alamat Baris 3 (Pilihan) | Address Line 3 (Optional) |
| Negeri | State |
| Daerah/Bandar | District/City |
| Poskod | Postcode |
| 5 digit nombor sahaja | 5 digits only |
| Promotion Code | Promotion Code (no change) |
| Enter code | Enter code (no change) |
| Apply | Apply (no change) |
| Order Summary | Order Summary (no change) |
| Subtotal | Subtotal (no change) |
| Discount | Discount (no change) |
| Total | Total (no change) |
| Proceed to Payment | Proceed to Payment (no change) |
| Maklumat Pelanggan | Customer Information |

### 3.2 Update Error Messages

Find validation error messages and update:

```php
// In validation section
if (empty($fullname)) {
    $errors[] = 'Full name is required';
}

if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Valid email is required';
}

if (empty($phone)) {
    $errors[] = 'Phone number is required';
}

if (empty($address1)) {
    $errors[] = 'Address Line 1 is required';
}

if (empty($state) || !in_array($state, $malaysiaStates)) {
    $errors[] = 'Valid state is required';
}

if (empty($district)) {
    $errors[] = 'District/City is required';
}

if (empty($postcode)) {
    $errors[] = 'Postcode is required';
} elseif (!preg_match('/^\d{5}$/', $postcode)) {
    $errors[] = 'Postcode must be 5 digits';
}
```

### 3.3 Update Pre-Order Notice

Find pre-order banner text and update:

```html
<!-- Find: -->
<p>
    <strong>Ini adalah pre-order.</strong> Kami akan menghantar kemaskini kemajuan pesanan anda melalui email pada setiap peringkat proses. 
    Sila pastikan alamat email anda betul dan aktif untuk menerima notifikasi.
</p>

<!-- Replace with: -->
<p>
    <strong>This is a pre-order.</strong> We will send order progress updates via email at each stage of the process. 
    Please ensure your email address is correct and active to receive notifications.
</p>
```

### 3.4 Update Section Headings

```html
<!-- Find: -->
<h2>Maklumat Pelanggan</h2>

<!-- Replace with: -->
<h2>Customer Information</h2>

<!-- Find: -->
<h3>Alamat Penghantaran</h3>

<!-- Replace with: -->
<h3>Delivery Address</h3>
```

---

## Step 4: Add Pickup/Delivery Selection

### 4.1 Add Order Type Selection Field

In `checkout.php`, add the order type selection before the customer information section (around line 650-700):

```html
<!-- Order Type Selection -->
<div class="form-group">
    <label>Order Type <span class="required">*</span></label>
    <div class="order-type-selector">
        <label class="order-type-option">
            <input type="radio" name="order_type" value="pickup" <?php echo ($orderType ?? 'delivery') === 'pickup' ? 'checked' : ''; ?>>
            <span class="order-type-label">
                <strong>Pickup</strong>
                <small>Free - Collect from our store</small>
            </span>
        </label>
        <label class="order-type-option">
            <input type="radio" name="order_type" value="delivery" <?php echo ($orderType ?? 'delivery') === 'delivery' ? 'checked' : ''; ?>>
            <span class="order-type-label">
                <strong>Delivery</strong>
                <small>RM <?php echo number_format(DELIVERY_FEE, 2); ?> delivery fee</small>
            </span>
        </label>
    </div>
</div>
```

### 4.2 Add CSS for Order Type Selector

Add to `css/checkout.css`:

```css
/* Order Type Selection */
.order-type-selector {
    display: flex;
    gap: 1rem;
    margin-top: 0.5rem;
}

.order-type-option {
    flex: 1;
    display: flex;
    align-items: center;
    padding: 1rem;
    border: 2px solid rgba(255, 255, 255, 0.1);
    border-radius: 8px;
    background: rgba(255, 255, 255, 0.03);
    cursor: pointer;
    transition: all 0.3s ease;
}

.order-type-option:hover {
    border-color: rgba(255, 255, 255, 0.2);
    background: rgba(255, 255, 255, 0.05);
}

.order-type-option input[type="radio"] {
    margin-right: 0.75rem;
    width: 1.25rem;
    height: 1.25rem;
    cursor: pointer;
}

.order-type-option input[type="radio"]:checked + .order-type-label {
    color: #fff;
}

.order-type-option:has(input[type="radio"]:checked) {
    border-color: rgba(255, 255, 255, 0.4);
    background: rgba(255, 255, 255, 0.08);
}

.order-type-label {
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.order-type-label strong {
    font-size: 1rem;
    font-weight: 600;
    color: #fff;
}

.order-type-label small {
    font-size: 0.875rem;
    color: rgba(255, 255, 255, 0.7);
}

/* Responsive */
@media (max-width: 768px) {
    .order-type-selector {
        flex-direction: column;
    }
}
```

### 4.3 Add JavaScript to Update Totals Dynamically

Add JavaScript in `checkout.php` before closing `</script>` tag:

```javascript
// Order Type Selection Handler
document.querySelectorAll('input[name="order_type"]').forEach(radio => {
    radio.addEventListener('change', function() {
        updateOrderTotals();
    });
});

function updateOrderTotals() {
    const orderType = document.querySelector('input[name="order_type"]:checked').value;
    const deliveryFee = orderType === 'delivery' ? <?php echo DELIVERY_FEE; ?> : 0;
    const subtotal = <?php echo $cartSubtotal; ?>;
    const discount = <?php echo $discountAmount; ?>;
    const final = subtotal + deliveryFee - discount;
    
    // Update delivery fee display
    const deliveryFeeElement = document.getElementById('deliveryFeeDisplay');
    if (deliveryFeeElement) {
        deliveryFeeElement.textContent = 'RM ' + deliveryFee.toFixed(2);
    }
    
    // Update final total
    const finalTotalElement = document.getElementById('finalTotalDisplay');
    if (finalTotalElement) {
        finalTotalElement.textContent = 'RM ' + Math.max(0, final).toFixed(2);
    }
    
    // Show/hide address fields based on order type
    const addressFields = document.querySelectorAll('[data-required-for-delivery]');
    addressFields.forEach(field => {
        const formGroup = field.closest('.form-group');
        if (orderType === 'delivery') {
            formGroup.style.display = '';
            if (field.hasAttribute('required') === false) {
                field.setAttribute('required', 'required');
            }
        } else {
            formGroup.style.display = 'none';
            field.removeAttribute('required');
        }
    });
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    updateOrderTotals();
});
```

### 4.4 Update Address Fields with Conditional Display

Add `data-required-for-delivery` attribute to address fields:

```html
<div class="form-group required" data-required-for-delivery>
    <label for="address1">Address Line 1</label>
    <input type="text" name="address1" id="address1" 
           value="<?php echo isset($_POST['address1']) ? htmlspecialchars($_POST['address1']) : ''; ?>" 
           data-required-for-delivery>
</div>

<div class="form-group" data-required-for-delivery>
    <label for="address2">Address Line 2 (Optional)</label>
    <input type="text" name="address2" id="address2" 
           value="<?php echo isset($_POST['address2']) ? htmlspecialchars($_POST['address2']) : ''; ?>"
           data-required-for-delivery>
</div>

<!-- Similar for address3, state, district, postcode -->
```

---

## Step 5: Update Session Management

### 5.1 Store Order Type in Session

In `checkout.php`, after order type is selected/submitted:

```php
// In form submission handler
$orderType = isset($_POST['order_type']) && in_array($_POST['order_type'], ['pickup', 'delivery']) 
    ? $_POST['order_type'] 
    : 'delivery';

// Store in session
$_SESSION['order_type'] = $orderType;
```

### 5.2 Retrieve Order Type from Session

At the top of checkout.php (after session start):

```php
// Get order type from session or default to 'delivery'
$orderType = $_SESSION['order_type'] ?? 'delivery';
```

### 5.3 Update Cart Structure (Optional)

If you want to store order_type in cart structure:

```php
// When adding to cart (in order-baju.php, if needed)
// Currently not necessary as order_type is selected at checkout
// But you can add it if you want to persist it earlier:
$_SESSION['cart']['order_type'] = $orderType; // Optional
```

---

## Step 6: Update Order Calculation Logic

### 6.1 Update Calculation Flow

The calculation should follow this order:

1. **Calculate Subtotal**: Sum of all cart items
2. **Calculate Delivery Fee**: RM8 if delivery, RM0 if pickup (server-side)
3. **Calculate Discount**: From promo code (on subtotal only)
4. **Calculate Final Amount**: `subtotal + delivery_fee - discount`

### 6.2 Update Promotion Code Response

In the AJAX promotion code validation response:

```php
// Calculate final amount with delivery fee
$deliveryFee = ($orderType === 'delivery') ? DELIVERY_FEE : 0;
$finalAmountWithDelivery = $cartSubtotal + $deliveryFee - $discount;

echo json_encode([
    'success' => true,
    'code' => $promotion['code'],
    'type' => $promotion['type'],
    'value' => $promotion['value'],
    'discount' => round($discount, 2),
    'subtotal' => $cartSubtotal,
    'delivery_fee' => $deliveryFee,
    'final' => round($finalAmountWithDelivery, 2)
]);
```

### 6.3 Update Order Summary Display

In the order summary section of checkout.php:

```html
<div class="order-total">
    <div class="order-total-row">
        <span>Subtotal:</span>
        <span>RM <?php echo number_format($cartSubtotal, 2); ?></span>
    </div>
    
    <!-- Delivery Fee Row -->
    <div class="order-total-row" id="deliveryFeeRow">
        <span>Delivery Fee:</span>
        <span id="deliveryFeeDisplay">RM <?php echo number_format($deliveryFee, 2); ?></span>
    </div>
    
    <?php if ($discountAmount > 0): ?>
    <div class="order-total-row discount">
        <span>Discount (<?php echo htmlspecialchars($promoCode); ?>):</span>
        <span>- RM <?php echo number_format($discountAmount, 2); ?></span>
    </div>
    <?php endif; ?>
    
    <div class="order-total-row final">
        <span>Total:</span>
        <span id="finalTotalDisplay">RM <?php echo number_format($finalAmount, 2); ?></span>
    </div>
</div>
```

---

## Step 7: Update Order Summary Display

### 7.1 Show Order Type in Summary

Add order type display in the order summary:

```html
<!-- Order Type Display -->
<div class="order-type-display" style="margin-bottom: 1.5rem; padding: 1rem; background: rgba(255, 255, 255, 0.03); border-radius: 8px;">
    <strong>Order Type:</strong> 
    <span id="orderTypeDisplay"><?php echo ucfirst($orderType ?? 'delivery'); ?></span>
    <?php if (($orderType ?? 'delivery') === 'delivery'): ?>
        <span style="color: rgba(255, 255, 255, 0.7);">(RM <?php echo number_format(DELIVERY_FEE, 2); ?> delivery fee)</span>
    <?php else: ?>
        <span style="color: rgba(255, 255, 255, 0.7);">(Free pickup)</span>
    <?php endif; ?>
</div>
```

### 7.2 Update JavaScript to Show Order Type

```javascript
function updateOrderTotals() {
    const orderType = document.querySelector('input[name="order_type"]:checked').value;
    const orderTypeDisplay = document.getElementById('orderTypeDisplay');
    if (orderTypeDisplay) {
        orderTypeDisplay.textContent = orderType.charAt(0).toUpperCase() + orderType.slice(1);
    }
    
    // ... rest of update logic ...
}
```

---

## Step 8: Update Order-Baju Page (English)

### 8.1 Update Product Selection Labels

In `order-baju.php`, find and replace:

| Malay | English |
|-------|---------|
| Pilih Warna | Select Color |
| Pilih Saiz | Select Size |
| Kuantiti | Quantity |
| Tambah ke Troli | Add to Cart |
| Troli | Cart |

### 8.2 Update Form Labels

Find the form section and update:

```html
<!-- Find: -->
<h2>Pilih Produk</h2>
<!-- Replace: -->
<h2>Select Product</h2>

<!-- Find: -->
<label>Warna</label>
<!-- Replace: -->
<label>Color</label>

<!-- Find: -->
<label>Saiz</label>
<!-- Replace: -->
<label>Size</label>

<!-- Find: -->
<label>Kuantiti</label>
<!-- Replace: -->
<label>Quantity</label>

<!-- Find: -->
<button type="submit" class="add-to-cart-btn">Tambah ke Troli</button>
<!-- Replace: -->
<button type="submit" class="add-to-cart-btn">Add to Cart</button>
```

### 8.3 Update Cart Popup Text

Find cart popup/modal and update:

```html
<!-- Find: -->
<h3>Troli Anda</h3>
<!-- Replace: -->
<h3>Your Cart</h3>

<!-- Find: -->
<p>Troli kosong</p>
<!-- Replace: -->
<p>Cart is empty</p>

<!-- Find: -->
<a href="checkout.php" class="checkout-btn">Semak Keluar</a>
<!-- Replace: -->
<a href="checkout.php" class="checkout-btn">Checkout</a>
```

---

## Step 9: Testing Checklist

### 9.1 Database Testing

- [ ] Run database migration script
- [ ] Verify new columns exist in `orders` table
- [ ] Verify existing orders have `final_amount` set
- [ ] Test INSERT with new columns

### 9.2 Order Type Testing

- [ ] Select "Pickup" - verify delivery fee is RM0
- [ ] Select "Delivery" - verify delivery fee is RM8
- [ ] Switch between pickup/delivery - verify totals update
- [ ] Select pickup - verify address fields can be hidden/optional
- [ ] Select delivery - verify address fields are required

### 9.3 Calculation Testing

- [ ] Test order without promo code
  - [ ] Subtotal: RM50
  - [ ] Delivery: RM8 (if delivery)
  - [ ] Final: RM58 (or RM50 for pickup)
- [ ] Test order with promo code
  - [ ] Subtotal: RM50
  - [ ] Discount: RM10
  - [ ] Delivery: RM8 (if delivery)
  - [ ] Final: RM48 (or RM40 for pickup)
- [ ] Test discount doesn't exceed subtotal
- [ ] Test final amount never goes negative

### 9.4 Form Testing

- [ ] All English labels display correctly
- [ ] All error messages in English
- [ ] Form validation works
- [ ] Pickup option - address fields optional
- [ ] Delivery option - address fields required

### 9.5 Payment Flow Testing

- [ ] Order created in database with correct `order_type`
- [ ] `delivery_fee` saved correctly
- [ ] `final_amount` saved correctly
- [ ] Payment amount matches `final_amount`
- [ ] Callback.php receives correct data
- [ ] Return.php displays correct order info

### 9.6 Backward Compatibility Testing

- [ ] Existing orders still display correctly
- [ ] Old orders have `order_type = 'delivery'` and `delivery_fee = 0`
- [ ] Old orders have `final_amount = amount`

---

## Step 10: Admin Panel Preparation Notes

### 10.1 Database Schema for Admin

The updated `orders` table now includes:

```sql
- order_type (ENUM: 'pickup', 'delivery')
- delivery_fee (DECIMAL)
- final_amount (DECIMAL)
- status (VARCHAR) - can extend: 'pending', 'packing', 'ready', 'paid', 'failed'
```

### 10.2 Future Admin Panel Requirements

**Order List View:**
- Display order type (Pickup/Delivery)
- Show delivery fee
- Show final amount
- Filter by order type
- Filter by status

**Order Detail View:**
- Show all customer info
- Show cart items (variant, size, quantity)
- Show order type
- Show delivery fee
- Show promo code and discount
- Show final amount
- Allow status update

**Order Status Workflow:**
```
pending → packing → ready → (paid - auto on payment)
                    ↓
                  (pickup) - customer collects
                    ↓
                  (delivery) - shipped
```

**Future Enhancements:**
- Add `stock_status` column: 'ready', 'out_of_stock', 'pre_order'
- Add `shipped_at` timestamp for delivery orders
- Add `picked_up_at` timestamp for pickup orders
- Add tracking number for delivery orders

### 10.3 Sample Admin Query

```sql
-- Get all orders with full details
SELECT 
    id,
    fullname,
    email,
    phone,
    order_type,
    delivery_fee,
    amount as subtotal,
    discount_amount,
    final_amount,
    status,
    created_at
FROM orders
ORDER BY created_at DESC;

-- Get pickup orders
SELECT * FROM orders WHERE order_type = 'pickup';

-- Get delivery orders
SELECT * FROM orders WHERE order_type = 'delivery';
```

---

## Summary of Changes

### Files Modified

1. **checkout.php**
   - Added DELIVERY_FEE constant
   - Added order_type selection form field
   - Updated all labels to English
   - Updated error messages to English
   - Updated calculation logic to include delivery fee
   - Updated database INSERT to include new columns
   - Added JavaScript for dynamic total updates

2. **order-baju.php**
   - Updated labels to English
   - Updated cart popup text to English

3. **css/checkout.css**
   - Added styles for order-type-selector

4. **Database**
   - Added `order_type` column
   - Added `delivery_fee` column
   - Added `final_amount` column

### Constants Added

```php
define('DELIVERY_FEE', 8.00);
```

### Session Variables Added

```php
$_SESSION['order_type'] = 'pickup' or 'delivery';
```

### Calculation Formula

```
Final Amount = Subtotal + Delivery Fee - Discount
```

---

## Deployment Steps

1. **Backup Database**: Create backup before running migration
2. **Run Database Migration**: Execute SQL script or PHP script
3. **Update Files**: Upload modified PHP and CSS files
4. **Test Thoroughly**: Use testing checklist
5. **Monitor**: Check error logs after deployment

---

**Last Updated**: January 2025
**Version**: 2.0.0 (Pickup/Delivery Update)

