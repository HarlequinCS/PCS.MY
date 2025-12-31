# Enhanced Checkout System - Setup Instructions

## Overview

This enhanced checkout system includes:
- Full address form (3 address lines, state, district, postcode)
- Company name field
- Delivery notes
- Promotion code system with discount calculation
- Complete database integration
- ToyyibPay payment integration

## Database Setup

### Step 1: Run Database Migration

Execute the SQL script to update your database:

```bash
mysql -u username -p database_name < database_checkout_update.sql
```

Or import via phpMyAdmin:
1. Open phpMyAdmin
2. Select your database
3. Click "Import"
4. Choose `database_checkout_update.sql`
5. Click "Go"

### Step 2: Verify Database Changes

The script will:
- ✅ Add new columns to `orders` table
- ✅ Create `promotions` table
- ✅ Insert sample promotion codes

**New columns in `orders` table:**
- `fullname` - Full name of customer
- `address1` - Address Line 1 (required)
- `address2` - Address Line 2 (optional)
- `address3` - Address Line 3 (optional)
- `state` - State in Malaysia
- `district` - District/City
- `postcode` - Postcode (5 digits)
- `delivery_notes` - Delivery instructions
- `company_name` - Company name (optional)
- `promo_code` - Promotion code applied
- `discount_amount` - Discount amount applied

**New `promotions` table:**
- Stores promotion codes
- Supports percentage and fixed discounts
- Includes usage limits and validity dates

## Sample Promotion Codes

The migration script includes these sample codes:

1. **WELCOME10** - 10% discount (max RM50)
2. **SAVE20** - RM20 fixed discount (min purchase RM100)
3. **SUMMER15** - 15% discount (min purchase RM150, max discount RM75)

## File Structure

```
order-baju.php          - Product selection with cart
checkout.php            - Enhanced checkout with full form
return.php              - Payment return page (clears cart)
callback.php            - Payment callback handler
database_checkout_update.sql - Database migration script
```

## Features

### 1. Address Form
- **Address Line 1** (required) - Main address
- **Address Line 2** (optional) - Additional address info
- **Address Line 3** (optional) - Additional address info
- **State** (required) - Dropdown with all Malaysia states
- **District/City** (required) - Text input
- **Postcode** (required) - 5 digits, numeric validation

### 2. Additional Fields
- **Company Name** (optional) - For business orders
- **Delivery Notes** (optional) - Special delivery instructions

### 3. Promotion Code System
- **Real-time validation** via AJAX
- **Percentage discounts** - e.g., 10% off
- **Fixed discounts** - e.g., RM20 off
- **Minimum purchase** requirements
- **Maximum discount** limits (for percentage)
- **Usage limits** - Track how many times code used
- **Validity dates** - Start and end dates

### 4. Discount Calculation
- Applied to cart subtotal
- Proportional discount per item
- Final amount calculated after discount
- Displayed in order summary

## Validation Rules

### Required Fields
- Full Name
- Email (valid format)
- Phone
- Address Line 1
- State
- District/City
- Postcode (5 digits, numeric)

### Optional Fields
- Address Line 2
- Address Line 3
- Company Name
- Delivery Notes
- Promotion Code

### Validation Logic
- **Email:** Must be valid email format
- **Postcode:** Must be exactly 5 digits, numeric only
- **State:** Must be from Malaysia states list
- **Promotion Code:** 
  - Must exist in database
  - Must be active
  - Must be within validity dates
  - Must meet minimum purchase (if set)
  - Must not exceed usage limit (if set)

## Promotion Code Management

### Creating Promotion Codes

Insert into `promotions` table:

```sql
INSERT INTO promotions (code, type, value, active, min_purchase, max_discount, valid_from, valid_until)
VALUES ('NEWCODE', 'percentage', 20.00, 1, 100.00, 50.00, NOW(), DATE_ADD(NOW(), INTERVAL 1 YEAR));
```

**Parameters:**
- `code` - Promotion code (unique, uppercase recommended)
- `type` - 'percentage' or 'fixed'
- `value` - Discount value (percentage or fixed amount)
- `active` - 1 = active, 0 = inactive
- `min_purchase` - Minimum purchase amount (0 = no minimum)
- `max_discount` - Maximum discount (for percentage, NULL = no limit)
- `usage_limit` - Maximum uses (NULL = unlimited)
- `valid_from` - Start date (NULL = no start date)
- `valid_until` - End date (NULL = no end date)

### Example Codes

**10% discount, no minimum:**
```sql
INSERT INTO promotions (code, type, value, active) 
VALUES ('DISCOUNT10', 'percentage', 10.00, 1);
```

**RM50 fixed discount, min purchase RM200:**
```sql
INSERT INTO promotions (code, type, value, active, min_purchase) 
VALUES ('SAVE50', 'fixed', 50.00, 1, 200.00);
```

**15% discount, max RM100, min purchase RM150:**
```sql
INSERT INTO promotions (code, type, value, active, min_purchase, max_discount) 
VALUES ('BIG15', 'percentage', 15.00, 1, 150.00, 100.00);
```

## Testing

### Test Promotion Codes

1. Add items to cart
2. Go to checkout
3. Enter promotion code: `WELCOME10`
4. Click "Apply"
5. Verify discount applied
6. Complete checkout

### Test Address Validation

1. Try submitting with empty required fields
2. Try invalid email format
3. Try invalid postcode (non-numeric, wrong length)
4. Verify all validations work

### Test Discount Calculation

1. Add items totaling RM200
2. Apply 10% code
3. Verify discount = RM20
4. Verify final amount = RM180

## Troubleshooting

### Promotion code not working

**Check:**
- Code exists in database
- `active` = 1
- Current date between `valid_from` and `valid_until`
- Cart subtotal meets `min_purchase`
- `used_count` < `usage_limit` (if set)

### Database errors

**Check:**
- All new columns added to `orders` table
- `promotions` table exists
- Database connection working
- PDO prepared statements used

### Address validation failing

**Check:**
- All required fields filled
- Postcode is exactly 5 digits
- State selected from dropdown
- Email format valid

## Security Notes

- ✅ All inputs validated
- ✅ SQL injection prevented (prepared statements)
- ✅ XSS prevented (htmlspecialchars)
- ✅ Email format validated
- ✅ Postcode format validated
- ✅ Promotion code validated server-side

## Production Checklist

Before going live:

- [ ] Run database migration
- [ ] Test all promotion codes
- [ ] Verify address validation
- [ ] Test discount calculations
- [ ] Update ToyyibPay credentials (if needed)
- [ ] Test complete checkout flow
- [ ] Verify database inserts
- [ ] Test payment processing
- [ ] Verify cart clearing on return

## Support

For issues or questions:
- Check error logs
- Verify database structure
- Test promotion codes manually
- Check browser console for JavaScript errors

