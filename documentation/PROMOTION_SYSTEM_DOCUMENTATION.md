# Promotion System Documentation

This document provides comprehensive documentation on how the promotion code management system works, including admin management, code validation, discount calculation, and integration with the checkout process.

---

## Table of Contents

1. [Overview](#overview)
2. [Database Schema](#database-schema)
3. [Admin Promotion Management](#admin-promotion-management)
4. [Promotion Code Application](#promotion-code-application)
5. [Discount Calculation Logic](#discount-calculation-logic)
6. [Validation Rules](#validation-rules)
7. [Usage Tracking](#usage-tracking)
8. [Integration with Checkout](#integration-with-checkout)
9. [Examples](#examples)
10. [Troubleshooting](#troubleshooting)

---

## Overview

The promotion system allows administrators to create and manage discount codes that customers can apply during checkout. Promotions can be:
- **Percentage-based**: Discount is calculated as a percentage of the subtotal
- **Fixed amount**: Discount is a fixed monetary amount

The system includes features for:
- Minimum purchase requirements
- Maximum discount caps (for percentage discounts)
- Usage limits (how many times a code can be used)
- Validity periods (valid from/until dates)
- Active/inactive status

---

## Database Schema

### Promotions Table

```sql
CREATE TABLE IF NOT EXISTS `promotions` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `code` VARCHAR(50) NOT NULL UNIQUE COMMENT 'Promotion code (uppercase)',
  `type` ENUM('percentage', 'fixed') NOT NULL DEFAULT 'percentage' COMMENT 'Discount type',
  `value` DECIMAL(10,2) NOT NULL COMMENT 'Discount value (percentage or fixed amount)',
  `min_purchase` DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Minimum purchase amount required',
  `max_discount` DECIMAL(10,2) NULL COMMENT 'Maximum discount cap (for percentage type)',
  `usage_limit` INT(11) NULL COMMENT 'Maximum number of times code can be used',
  `used_count` INT(11) NOT NULL DEFAULT 0 COMMENT 'Number of times code has been used',
  `active` TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Active status (1=active, 0=inactive)',
  `valid_from` DATETIME NULL COMMENT 'Valid from date/time',
  `valid_until` DATETIME NULL COMMENT 'Valid until date/time',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`),
  INDEX `idx_active` (`active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### Key Columns Explained

| Column | Type | Description |
|--------|------|-------------|
| `code` | VARCHAR(50) | Unique promotion code (automatically converted to uppercase) |
| `type` | ENUM | `'percentage'` or `'fixed'` - determines discount calculation method |
| `value` | DECIMAL(10,2) | For percentage: discount percentage (e.g., 10 = 10%). For fixed: discount amount (e.g., 5.00 = RM5) |
| `min_purchase` | DECIMAL(10,2) | Minimum cart subtotal required to use the code (default: 0.00) |
| `max_discount` | DECIMAL(10,2) | Maximum discount cap for percentage discounts (NULL = no limit) |
| `usage_limit` | INT(11) | Maximum number of times the code can be used (NULL = unlimited) |
| `used_count` | INT(11) | Current number of times the code has been used (auto-incremented) |
| `active` | TINYINT(1) | `1` = active (can be used), `0` = inactive (cannot be used) |
| `valid_from` | DATETIME | Code becomes valid from this date/time (NULL = no start date) |
| `valid_until` | DATETIME | Code expires after this date/time (NULL = no expiration) |

---

## Admin Promotion Management

### Accessing Promotion Management

1. Log in to the admin panel: `/admin/`
2. Navigate to **Promotions** in the sidebar menu
3. URL: `/admin/promotions.php`

### Creating a New Promotion

1. Click **"+ Create New Promotion"** button
2. Fill in the promotion form:

#### Required Fields:
- **Code**: Unique promotion code (automatically converted to uppercase)
- **Type**: Select "Percentage" or "Fixed Amount"
- **Value**: 
  - For percentage: Enter percentage (e.g., `10` for 10% off)
  - For fixed: Enter amount (e.g., `5.00` for RM5 off)

#### Optional Fields:
- **Minimum Purchase (RM)**: Minimum cart subtotal required (default: 0.00)
- **Maximum Discount (RM)**: For percentage discounts only - cap the maximum discount amount
- **Usage Limit**: Maximum number of times the code can be used (leave empty for unlimited)
- **Valid From**: Start date/time for code validity
- **Valid Until**: Expiration date/time for code validity
- **Active**: Checkbox to activate/deactivate the code

3. Click **"Add Promotion"** to save

### Editing a Promotion

1. In the promotions list, click **"Edit"** next to the promotion code
2. Modify the fields as needed
3. Click **"Update Promotion"** to save changes

### Deleting a Promotion

1. In the promotions list, click **"Delete"** next to the promotion code
2. Confirm the deletion
3. The promotion code will be permanently removed from the database

### Viewing Promotions

The promotions list displays:
- Code
- Type (Percentage/Fixed)
- Value
- Minimum Purchase
- Maximum Discount
- Usage Limit
- Used Count (current usage)
- Active Status
- Valid From/Until dates
- Actions (Edit/Delete buttons)

---

## Promotion Code Application

### User Flow

1. **Customer adds items to cart** on `/order-baju.php`
2. **Customer proceeds to checkout** on `/checkout.php`
3. **Customer enters promotion code** in the "Promotion Code" field
4. **Customer clicks "Apply"** button
5. **System validates code** via AJAX (see validation rules below)
6. **If valid**, discount is applied and displayed in order summary
7. **If invalid**, error message is displayed

### AJAX Validation Endpoint

**URL**: `/checkout.php` (POST request with `action=validate_promo`)

**Request Parameters**:
```javascript
{
  action: 'validate_promo',
  promo_code: 'DISCOUNT10',
  cart_subtotal: 100.00
}
```

**Response (Success)**:
```json
{
  "success": true,
  "code": "DISCOUNT10",
  "type": "percentage",
  "value": 10,
  "discount": 10.00,
  "subtotal": 100.00,
  "final": 90.00
}
```

**Response (Error)**:
```json
{
  "success": false,
  "error": "Promotion code not found or inactive"
}
```

---

## Discount Calculation Logic

### Percentage Discount

```
discount = (cart_subtotal × value) / 100

IF max_discount IS NOT NULL AND discount > max_discount:
    discount = max_discount

IF discount > cart_subtotal:
    discount = cart_subtotal  // Cannot discount more than subtotal
```

**Example**:
- Subtotal: RM 100.00
- Code: 15% off, max discount RM 10.00
- Calculation: (100 × 15) / 100 = RM 15.00
- Applied: RM 10.00 (capped at max discount)
- Final: RM 90.00

### Fixed Amount Discount

```
discount = value

IF discount > cart_subtotal:
    discount = cart_subtotal  // Cannot discount more than subtotal
```

**Example**:
- Subtotal: RM 50.00
- Code: RM 10.00 off
- Calculation: RM 10.00
- Applied: RM 10.00
- Final: RM 40.00

### Final Amount Calculation (with Delivery)

```
subtotal = sum of all cart items
delivery_fee = 8.00 (if order_type = 'delivery') OR 0.00 (if order_type = 'pickup')
discount = calculated discount from promotion code
final_amount = subtotal + delivery_fee - discount
```

**Example**:
- Subtotal: RM 100.00
- Delivery Fee: RM 8.00
- Discount: RM 10.00
- Final Amount: RM 98.00

---

## Validation Rules

### Server-Side Validation (During Checkout)

The system validates promotion codes with the following checks (in order):

1. **Code Exists**: Promotion code exists in database
2. **Active Status**: `active = 1`
3. **Validity Period**:
   - `valid_from IS NULL OR valid_from <= NOW()`
   - `valid_until IS NULL OR valid_until >= NOW()`
4. **Usage Limit**: `usage_limit IS NULL OR used_count < usage_limit`
5. **Minimum Purchase**: `min_purchase = 0 OR cart_subtotal >= min_purchase`

### Validation Error Messages

| Condition | Error Message |
|-----------|---------------|
| Code not found | "Promotion code not found or inactive" |
| Code inactive | "Promotion code not found or inactive" |
| Code expired | "Promotion code has expired" |
| Code not yet valid | "Promotion code is not yet valid" |
| Usage limit reached | "Promotion code has reached usage limit" |
| Minimum purchase not met | "Promotion code requires minimum purchase of RM X.XX" |

### Client-Side Validation

- Real-time validation via AJAX when user clicks "Apply"
- Validates before form submission
- Updates order summary dynamically
- Shows error messages immediately

---

## Usage Tracking

### Automatic Increment

When a customer successfully completes checkout (order is created):
1. The `used_count` for the applied promotion code is automatically incremented
2. This happens during the checkout process **after order creation** but **before payment processing**
3. Each order can only use one promotion code
4. The increment happens when the order is saved to the database (even if payment fails later)

**Implementation** (in `checkout.php`):
```php
// Update promotion usage count if code was used
if (!empty($promoCode)) {
    $updateStmt = $pdo->prepare("
        UPDATE promotions 
        SET used_count = used_count + 1 
        WHERE code = :code
    ");
    $updateStmt->execute([':code' => $promoCode]);
}
```

**Important Notes**:
- Usage count increments when order is created, not when payment completes
- If payment fails after order creation, the usage count has already been incremented
- This design ties codes to orders rather than successful payments
- For future consideration: Only increment on successful payment confirmation

### Manual Tracking

- Admin can view `used_count` in the promotions list
- `used_count` cannot be manually edited (auto-incremented only)
- When `used_count >= usage_limit`, the code becomes unusable (validation fails)

### Important Notes

- Usage is tracked per successful order (not per validation attempt)
- Multiple validation attempts do not increment the counter
- Counter increments only when order is successfully created
- If payment fails, usage count is still incremented (code applied to order)

---

## Integration with Checkout

### Checkout Flow

1. **Cart Subtotal Calculation**
   ```php
   $cartSubtotal = array_sum(array_column($_SESSION['cart'], 'subtotal'));
   ```

2. **Promotion Code Application** (if provided)
   - User enters code and clicks "Apply"
   - AJAX validation runs
   - If valid, discount amount is stored in hidden form fields

3. **Order Type Selection** (Pickup/Delivery)
   - Delivery: RM 8.00 fee added
   - Pickup: No delivery fee

4. **Final Amount Calculation**
   ```php
   $finalAmount = $cartSubtotal + $deliveryFee - $discountAmount;
   ```

5. **Order Creation**
   - Promotion code and discount amount saved to `orders` table
   - `used_count` incremented in `promotions` table

6. **Payment Processing**
   - ToyyibPay payment gateway receives `final_amount`
   - Customer pays the discounted amount

### Database Storage (Orders Table)

The `orders` table stores:
- `promo_code`: The applied promotion code (VARCHAR)
- `discount_amount`: The discount amount applied (DECIMAL)

**Note**: Each order row stores the promotion code and discount. If an order has multiple items, all rows share the same promotion code and discount amount.

---

## Examples

### Example 1: 10% Off, No Limits

**Promotion Setup**:
- Code: `SAVE10`
- Type: Percentage
- Value: 10
- Min Purchase: 0.00
- Max Discount: NULL
- Usage Limit: NULL
- Active: Yes

**Scenario**:
- Cart Subtotal: RM 100.00
- Delivery: RM 8.00
- Calculation: (100 × 10) / 100 = RM 10.00 discount
- Final: RM 98.00

### Example 2: RM 20 Off, Minimum Purchase RM 100

**Promotion Setup**:
- Code: `FLAT20`
- Type: Fixed
- Value: 20.00
- Min Purchase: 100.00
- Max Discount: N/A
- Usage Limit: 50
- Active: Yes

**Scenario 1** (Valid):
- Cart Subtotal: RM 150.00
- Delivery: RM 8.00
- Calculation: RM 20.00 discount
- Final: RM 138.00

**Scenario 2** (Invalid - Minimum Purchase Not Met):
- Cart Subtotal: RM 80.00
- Error: "Promotion code requires minimum purchase of RM 100.00"

### Example 3: 20% Off, Max RM 15 Cap

**Promotion Setup**:
- Code: `BIG20`
- Type: Percentage
- Value: 20
- Min Purchase: 50.00
- Max Discount: 15.00
- Usage Limit: 100
- Active: Yes

**Scenario 1** (Capped):
- Cart Subtotal: RM 200.00
- Delivery: RM 8.00
- Calculation: (200 × 20) / 100 = RM 40.00, capped at RM 15.00
- Final: RM 193.00

**Scenario 2** (Not Capped):
- Cart Subtotal: RM 50.00
- Delivery: RM 8.00
- Calculation: (50 × 20) / 100 = RM 10.00 (within cap)
- Final: RM 48.00

### Example 4: Time-Limited Promotion

**Promotion Setup**:
- Code: `NEWYEAR2024`
- Type: Fixed
- Value: 25.00
- Valid From: 2024-01-01 00:00:00
- Valid Until: 2024-01-31 23:59:59
- Active: Yes

**Scenario** (Before Valid Period):
- Current Date: 2023-12-31
- Error: "Promotion code is not yet valid"

**Scenario** (During Valid Period):
- Current Date: 2024-01-15
- Valid: Code can be used

**Scenario** (After Valid Period):
- Current Date: 2024-02-01
- Error: "Promotion code has expired"

---

## Troubleshooting

### Common Issues

#### Issue: Promotion code not working

**Possible Causes**:
1. Code is inactive (`active = 0`)
2. Code has expired (`valid_until < NOW()`)
3. Usage limit reached (`used_count >= usage_limit`)
4. Minimum purchase not met
5. Typo in code (codes are case-insensitive but must match exactly)

**Solution**: Check promotion status in admin panel and verify all validation criteria.

#### Issue: Discount amount incorrect

**Possible Causes**:
1. Maximum discount cap applied (for percentage discounts)
2. Discount exceeds subtotal (automatically capped)
3. Minimum purchase not considered in calculation

**Solution**: Review promotion settings and verify discount calculation logic.

#### Issue: Usage count not incrementing

**Possible Causes**:
1. Order creation failed
2. Database transaction not committed
3. Payment not completed

**Solution**: Check order creation process and database transactions.

#### Issue: Code works in validation but not in checkout

**Possible Causes**:
1. Code expired between validation and checkout
2. Usage limit reached between validation and checkout
3. Code deactivated between validation and checkout

**Solution**: Server-side validation runs again during checkout to ensure code is still valid.

### Debugging Tips

1. **Check Database**: Query the `promotions` table to verify code status:
   ```sql
   SELECT * FROM promotions WHERE code = 'YOUR_CODE';
   ```

2. **Check Error Logs**: Review PHP error logs for validation errors:
   ```php
   error_log('Promotion validation error: ' . $e->getMessage());
   ```

3. **Test Validation Endpoint**: Use browser DevTools Network tab to inspect AJAX validation responses

4. **Verify Calculation**: Manually calculate expected discount and compare with system output

---

## Best Practices

### Creating Effective Promotions

1. **Clear Code Names**: Use memorable, relevant codes (e.g., `SUMMER2024`, `NEWUSER10`)
2. **Set Usage Limits**: Prevent abuse by setting reasonable usage limits
3. **Use Validity Periods**: Set expiration dates to create urgency
4. **Set Minimum Purchases**: Ensure promotions are profitable
5. **Test Before Launch**: Test codes in staging environment before making them active

### Managing Promotions

1. **Monitor Usage**: Regularly check `used_count` to track code performance
2. **Deactivate Old Codes**: Set codes to inactive when no longer needed (don't delete if orders reference them)
3. **Review Performance**: Analyze which codes drive the most sales
4. **Set Reasonable Caps**: For percentage discounts, set max discount caps to control costs

### Security Considerations

1. **Server-Side Validation**: All validation is performed server-side (client-side is for UX only)
2. **SQL Injection Protection**: All database queries use prepared statements
3. **Code Uniqueness**: Database enforces unique constraint on codes
4. **Case Insensitivity**: Codes are stored and matched in uppercase

---

## API Reference

### Validation Endpoint

**URL**: `/checkout.php`

**Method**: POST

**Parameters**:
- `action`: `validate_promo`
- `promo_code`: Promotion code string
- `cart_subtotal`: Cart subtotal (decimal)

**Response**:
- Success: JSON with discount details
- Error: JSON with error message

### Database Functions

**Increment Usage Count**:
```php
$stmt = $pdo->prepare("
    UPDATE promotions 
    SET used_count = used_count + 1 
    WHERE code = :code
");
$stmt->execute([':code' => $promoCode]);
```

**Validate Promotion**:
```php
$stmt = $pdo->prepare("
    SELECT * FROM promotions 
    WHERE code = :code 
    AND active = 1
    AND (valid_from IS NULL OR valid_from <= NOW())
    AND (valid_until IS NULL OR valid_until >= NOW())
    AND (usage_limit IS NULL OR used_count < usage_limit)
");
```

---

## Related Documentation

- [Ordering System Documentation](ORDERING_SYSTEM_DOCUMENTATION.md) - Complete ordering flow
- [Admin Panel Enhancement Guide](ADMIN_PANEL_ENHANCEMENT_GUIDE.md) - Admin panel features

---

**Last Updated**: 2024-01-01  
**Version**: 1.0.0

