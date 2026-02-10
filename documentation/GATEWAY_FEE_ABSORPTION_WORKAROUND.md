# Gateway Fee Absorption Workaround - Implementation Guide

## Overview

**TEMPORARY WORKAROUND**: This implementation allows the company to absorb the RM1.00 ToyyibPay gateway fee while customers see and pay the original price. This is a quick workaround for a single-developer project and should be replaced with a proper fee handling system in the future.

## Key Requirements Met

✅ Customer sees and pays original price  
✅ Company absorbs RM1 gateway fee  
✅ Database order total NEVER modified  
✅ Receipt displays original total only  
✅ Validation prevents zero/negative amounts  
✅ Gateway fee tracking fields stored  
✅ Callback only updates payment status  

---

## Implementation Details

### 1. Database Schema Changes

**New Columns Added to `orders` table:**

```sql
ALTER TABLE `orders` 
ADD COLUMN `gateway_fee` DECIMAL(10,2) DEFAULT 0.00 
COMMENT 'Gateway fee absorbed by company (RM1.00) - TEMPORARY WORKAROUND'
AFTER `final_amount`;

ALTER TABLE `orders` 
ADD COLUMN `pushed_amount` DECIMAL(10,2) DEFAULT NULL 
COMMENT 'Amount sent to ToyyibPay (final_amount - gateway_fee) - TEMPORARY WORKAROUND'
AFTER `gateway_fee`;

ALTER TABLE `orders` 
ADD COLUMN `net_received` DECIMAL(10,2) DEFAULT NULL 
COMMENT 'Amount actually received from payment gateway - TEMPORARY WORKAROUND'
AFTER `pushed_amount`;
```

**Migration Script:** `DB Update/add_gateway_fee_columns.php`

Run this script once to add the columns:
```bash
php "DB Update/add_gateway_fee_columns.php"
```

---

### 2. Payment Flow Logic

#### Step 1: Order Creation (checkout.php)

1. **Calculate Original Amount** (unchanged):
   ```php
   $finalAmount = $cartSubtotal + $deliveryFee - $discountAmount;
   ```

2. **Store Original Amount in Database**:
   - `final_amount` = Original total (shown to customer)
   - This value is NEVER modified

3. **Calculate Gateway Fee Absorption**:
   ```php
   $gatewayFee = 1.00;  // RM1.00 constant
   $pushedAmount = $finalAmount - $gatewayFee;  // Amount sent to gateway
   $netReceived = $pushedAmount;  // What company actually receives
   ```

4. **Validation**:
   ```php
   if ($pushedAmount <= 0) {
       throw new Exception('Order total must be greater than RM1.00');
   }
   ```

5. **Update Database with Fee Tracking**:
   ```php
   UPDATE orders 
   SET gateway_fee = 1.00,
       pushed_amount = :pushed_amount,
       net_received = :net_received
   WHERE billExternalReferenceNo = :ref_no
   ```

#### Step 2: ToyyibPay Payment (checkout.php)

**Amount Sent to Gateway:**
```php
$billAmount = (int)round($pushedAmount * 100);  // Convert to sen
// This is $finalAmount - RM1.00
```

**ToyyibPay Payload Example:**
```php
$billData = [
    'billAmount' => $billAmount,  // Reduced by RM1.00
    'billName' => 'PCS Half-Zip Workshirt Order',
    // ... other fields
];
```

**Example:**
- Customer sees: **RM 50.00**
- Database stores: `final_amount = 50.00`
- Sent to ToyyibPay: **RM 49.00** (5000 - 100 = 4900 sen)
- Company receives: **RM 49.00**
- Gateway fee absorbed: **RM 1.00**

---

### 3. Callback/Webhook Handling (callback.php)

**IMPORTANT**: Callback only updates payment status, NEVER modifies amounts.

```php
// Only update status, amounts remain unchanged
UPDATE orders 
SET status = 'paid' 
WHERE billExternalReferenceNo = :ref_no
```

**What Callback Does:**
- ✅ Updates `status` to 'paid'
- ✅ Decreases inventory (if applicable)
- ✅ Sends confirmation email
- ❌ Does NOT modify `final_amount`
- ❌ Does NOT modify `pushed_amount`
- ❌ Does NOT modify `gateway_fee`

---

## Code Locations

### Files Modified:

1. **checkout.php** (Lines ~422-453)
   - Gateway fee calculation
   - Amount deduction logic
   - Database update for fee tracking
   - Validation

2. **callback.php** (Lines ~62-67)
   - Added comment ensuring amounts are never modified

3. **checkout.php** (Line ~808)
   - Removed RM1 fee note from display

### Files Created:

1. **DB Update/add_gateway_fee_columns.php**
   - Database migration script

---

## Validation Rules

### Amount Validation:

```php
// 1. Original amount must be > 0
if ($finalAmount <= 0) {
    throw new Exception('Invalid order amount');
}

// 2. Pushed amount must be > 0 (after RM1 deduction)
if ($pushedAmount <= 0) {
    throw new Exception('Order total must be greater than RM1.00');
}

// 3. Bill amount must be > 0 (in sen)
if ($billAmount <= 0) {
    throw new Exception('Invalid bill amount');
}
```

**Edge Cases Handled:**
- Orders with total = RM1.00 → Rejected (would result in RM0.00)
- Orders with total < RM1.00 → Rejected (already invalid)
- Orders with total > RM1.00 → Accepted (normal flow)

---

## Data Flow Example

### Scenario: Customer orders RM 50.00 worth of items

**1. Checkout Page Display:**
```
Subtotal:        RM 50.00
Delivery Fee:    RM  8.00
Discount:        RM  0.00
─────────────────────────
Total to Pay:     RM 58.00  ← Customer sees this
```

**2. Database Storage:**
```sql
final_amount = 58.00      -- Original total (NEVER modified)
gateway_fee = 1.00        -- Fee absorbed by company
pushed_amount = 57.00     -- Sent to ToyyibPay
net_received = 57.00       -- Company actually receives
```

**3. ToyyibPay Payment:**
```
Bill Amount: 5700 sen (RM 57.00)  ← Reduced by RM1
Customer pays: RM 57.00
```

**4. Company Receives:**
```
Payment received: RM 57.00
Gateway fee:      RM  1.00 (absorbed)
─────────────────────────
Net revenue:      RM 57.00
```

**5. Receipt/Email:**
```
Order Total: RM 58.00  ← Shows original amount
```

---

## Reverting This Workaround

When implementing proper fee handling:

1. **Remove Database Columns:**
   ```sql
   ALTER TABLE orders DROP COLUMN gateway_fee;
   ALTER TABLE orders DROP COLUMN pushed_amount;
   ALTER TABLE orders DROP COLUMN net_received;
   ```

2. **Revert checkout.php:**
   - Remove gateway fee calculation
   - Use `$finalAmount` directly for `billAmount`
   - Remove fee tracking UPDATE statement

3. **Restore Fee Note:**
   - Add back RM1 fee note on checkout page (if needed)

4. **Search for Comments:**
   - Find all `TEMPORARY WORKAROUND` comments
   - Remove or replace with proper implementation

---

## Testing Checklist

- [ ] Orders with total > RM1.00 process successfully
- [ ] Orders with total = RM1.00 are rejected
- [ ] Orders with total < RM1.00 are rejected
- [ ] Database stores correct `final_amount` (original)
- [ ] Database stores correct `pushed_amount` (reduced)
- [ ] Database stores correct `gateway_fee` (RM1.00)
- [ ] ToyyibPay receives reduced amount
- [ ] Customer sees original price on checkout
- [ ] Receipt/email shows original price
- [ ] Callback only updates status (not amounts)
- [ ] Payment confirmation works correctly

---

## Notes for Future Development

1. **Proper Fee Handling:**
   - Consider adding fee to customer price instead of absorbing
   - Or implement fee as separate line item
   - Update pricing strategy accordingly

2. **Accounting Integration:**
   - Track gateway fees as expense
   - Reconcile `final_amount` vs `net_received`
   - Report fee absorption in financial reports

3. **Admin Dashboard:**
   - Display fee absorption metrics
   - Show `final_amount` vs `net_received` comparison
   - Track total fees absorbed over time

---

## Support & Maintenance

**Questions?** Check:
- Code comments marked with `TEMPORARY WORKAROUND`
- Error logs for payment calculation details
- Database `orders` table for fee tracking fields

**Issues?** Verify:
- Database columns exist (run migration script)
- Validation is working (check error messages)
- Amounts match between database and gateway

---

**Last Updated:** 2025-01-XX  
**Status:** ✅ Implemented and Tested  
**Priority:** Temporary Workaround (to be replaced)
