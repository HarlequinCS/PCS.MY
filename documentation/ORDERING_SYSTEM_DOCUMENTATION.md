# Ordering System Documentation

**Projek Cuti Sem E-Commerce Ordering System**

This document provides comprehensive documentation of the ordering system implementation, including architecture, workflow, database schema, payment integration, and configuration.

---

## Table of Contents

1. [Overview](#overview)
2. [System Architecture](#system-architecture)
3. [Order Flow Workflow](#order-flow-workflow)
4. [File Structure](#file-structure)
5. [Database Schema](#database-schema)
6. [Session Management](#session-management)
7. [Payment Integration](#payment-integration)
8. [Promotion Code System](#promotion-code-system)
9. [Configuration](#configuration)
10. [Frontend Components](#frontend-components)
11. [Security Considerations](#security-considerations)
12. [Error Handling](#error-handling)
13. [Deployment Notes](#deployment-notes)

---

## Overview

The ordering system is a complete e-commerce solution built with PHP, implementing a session-based shopping cart, full address collection, promotion code discounts, and payment processing through ToyyibPay (Malaysian payment gateway).

### Key Features

- **Session-based Shopping Cart**: Stores cart items in PHP sessions
- **Product Variants**: Support for multiple color variants (Hitam, Hijau, Merah)
- **Size Selection**: S, M, L, XL, XXL sizes
- **Quantity Management**: Add multiple quantities of items
- **Full Address Collection**: Complete Malaysian address form with state/district/postcode
- **Promotion Code System**: Apply discount codes with validation
- **Payment Integration**: ToyyibPay payment gateway integration
- **Order Tracking**: Database storage with status tracking
- **Auto-redirect**: Automatic redirect after successful payment

---

## System Architecture

### Technology Stack

- **Backend**: PHP 7.4+
- **Database**: MySQL (PDO)
- **Payment Gateway**: ToyyibPay (Staging/Production)
- **Frontend**: HTML5, CSS3, JavaScript (Vanilla)
- **Session Management**: PHP Sessions

### Architecture Pattern

The system follows a traditional PHP MVC-like structure:

```
User Request → PHP Page → Database/API → Response
```

---

## Order Flow Workflow

### Complete User Journey

```
1. Product Selection (order-baju.php)
   ├── User selects variant (color)
   ├── User selects size (S/M/L/XL/XXL)
   ├── User sets quantity
   └── User clicks "Add to Cart"
   
2. Cart Management (Session-based)
   ├── Items stored in $_SESSION['cart']
   ├── Cart popup displays items
   └── User can proceed to checkout
   
3. Checkout (checkout.php)
   ├── Display cart summary
   ├── Collect customer information
   │   ├── Full name
   │   ├── Email
   │   ├── Phone number
   │   └── Complete address (3 lines, state, district, postcode)
   ├── Apply promotion code (optional)
   ├── Calculate final amount
   └── Submit order
   
4. Payment Processing
   ├── Create order in database (status: 'pending')
   ├── Generate unique reference number
   ├── Create ToyyibPay bill
   └── Redirect to ToyyibPay payment page
   
5. Payment Gateway (ToyyibPay)
   ├── User completes payment
   ├── ToyyibPay processes payment
   └── Redirects back to return.php
   
6. Payment Callback (callback.php)
   ├── ToyyibPay sends server-to-server POST
   ├── Update order status in database
   └── Log payment details
   
7. Payment Return (return.php)
   ├── Display payment status to user
   ├── Show order details
   ├── Clear cart if successful
   └── Auto-redirect to homepage
```

---

## File Structure

### Core Files

```
├── order-baju.php          # Product selection page
├── checkout.php            # Checkout and payment processing
├── callback.php            # Payment callback handler (server-to-server)
├── return.php              # Payment return page (user-facing)
├── config/
│   ├── database.php        # Database configuration
│   └── version.php         # Asset versioning configuration
├── includes/
│   └── db.php              # Database connection helper
└── css/
    ├── order-baju.css      # Product page styles
    ├── checkout.css        # Checkout page styles
    └── return.css          # Return page styles
```

### Key Components

- **order-baju.php**: Product selection, variant/size/quantity selection, cart management
- **checkout.php**: Customer information collection, promotion code validation, payment processing
- **callback.php**: Server-to-server payment confirmation (critical for payment validation)
- **return.php**: User-facing payment status page

---

## Database Schema

### Orders Table

The system auto-creates the `orders` table if it doesn't exist. Schema:

```sql
CREATE TABLE IF NOT EXISTS `orders` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Field Descriptions

- **id**: Auto-increment primary key
- **name**: Legacy field (backward compatibility with ToyyibPay)
- **fullname**: Customer's full name
- **email**: Customer email (required)
- **phone**: Customer phone number (required)
- **address1, address2, address3**: Multi-line address (address1 required)
- **state**: Malaysian state (required)
- **district**: District/city (required)
- **postcode**: 5-digit postcode (required)
- **variant**: Product variant key (hitamdepan, hijaufront, merahdepan)
- **size**: Size selection (S, M, L, XL, XXL)
- **quantity**: Number of items
- **amount**: Final total amount after discount
- **promo_code**: Applied promotion code (if any)
- **discount_amount**: Discount amount in RM
- **billExternalReferenceNo**: Unique reference for ToyyibPay (format: BILL-timestamp-random)
- **status**: Order status ('pending', 'paid', 'failed')
- **created_at**: Order creation timestamp

---

## Session Management

### Cart Structure

Cart items are stored in `$_SESSION['cart']` as an array:

```php
$_SESSION['cart'] = [
    [
        'variant' => 'hitamdepan',
        'variant_name' => 'Hitam',
        'variant_image' => 'images/hitamdepan.svg',
        'size' => 'M',
        'quantity' => 2,
        'price' => 50.00,
        'subtotal' => 100.00
    ],
    // ... more items
];
```

### Session Lifecycle

1. **Cart Creation**: Session starts when user visits order-baju.php
2. **Cart Updates**: Items added via AJAX/form submission
3. **Checkout**: Cart data used to display order summary
4. **Payment Success**: Cart cleared in return.php (status_id = 1)
5. **Payment Failed**: Cart remains for retry

### Security Notes

- Sessions use PHP's native session management
- Session data stored server-side
- No sensitive payment data in sessions
- Cart cleared after successful payment

---

## Payment Integration

### ToyyibPay Integration

The system integrates with ToyyibPay payment gateway (Malaysian payment provider).

#### Configuration

Located in `checkout.php`:

```php
define('STAGING_SECRET_KEY', 'dh1kldj2-j8db-ofm0-qauv-e037egz4oiui');
define('STAGING_CATEGORY_CODE', 'g3ii2ms2');
$baseUrl = 'https://projectcutisem.com';
```

**⚠️ IMPORTANT**: Update these credentials for production.

#### Payment Flow

1. **Bill Creation** (`checkout.php`):
   - Collects order data
   - Creates order record in database (status: 'pending')
   - Generates unique `billExternalReferenceNo`
   - Calls ToyyibPay API to create bill
   - Redirects user to ToyyibPay payment page

2. **Payment Processing** (ToyyibPay):
   - User completes payment on ToyyibPay
   - ToyyibPay processes payment
   - Sends server-to-server POST to `callback.php`
   - Redirects user to `return.php`

3. **Payment Callback** (`callback.php`):
   - Receives POST data from ToyyibPay
   - Logs callback data to file
   - Updates order status in database
   - **This is the authoritative source for payment confirmation**

4. **Payment Return** (`return.php`):
   - Displays payment status to user
   - Shows order details
   - Clears cart if successful
   - Auto-redirects to homepage after animation

#### Payment Status Codes

- **1**: Success (Payment completed)
- **2**: Pending (Payment processing)
- **3**: Failed (Payment failed)

#### Callback URL

**Critical**: ToyyibPay sends server-to-server POST to:
```
https://projectcutisem.com/callback.php
```

This URL must be:
- Publicly accessible
- Handle POST requests
- Update database based on payment status
- Not rely on user session data

---

## Promotion Code System

### Implementation

Promotion codes are validated in `checkout.php` via AJAX and server-side validation.

#### Database-Driven Promotion Codes

Promotion codes are stored in a `promotions` database table. The system queries this table for validation.

**Promotions Table Schema** (create this table if it doesn't exist):

```sql
CREATE TABLE IF NOT EXISTS `promotions` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `code` VARCHAR(50) NOT NULL UNIQUE,
    `type` ENUM('fixed', 'percentage') NOT NULL DEFAULT 'fixed',
    `value` DECIMAL(10,2) NOT NULL COMMENT 'Discount value (RM for fixed, percentage for percentage)',
    `max_discount` DECIMAL(10,2) NULL COMMENT 'Maximum discount amount (for percentage type)',
    `min_purchase` DECIMAL(10,2) DEFAULT 0 COMMENT 'Minimum purchase amount required',
    `usage_limit` INT(11) NULL COMMENT 'Maximum number of times code can be used (NULL = unlimited)',
    `used_count` INT(11) DEFAULT 0 COMMENT 'Number of times code has been used',
    `active` TINYINT(1) DEFAULT 1 COMMENT '1 = active, 0 = inactive',
    `valid_from` DATETIME NULL COMMENT 'Code valid from date (NULL = no start date)',
    `valid_until` DATETIME NULL COMMENT 'Code valid until date (NULL = no expiration)',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `code` (`code`),
    INDEX `idx_active` (`active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

**Example Promotion Codes**:

```sql
-- Fixed RM10 discount, no minimum purchase, unlimited uses
INSERT INTO promotions (code, type, value, active) 
VALUES ('SAVE10', 'fixed', 10.00, 1);

-- 10% discount, max RM20, minimum purchase RM50, 100 uses
INSERT INTO promotions (code, type, value, max_discount, min_purchase, usage_limit, active)
VALUES ('WELCOME10', 'percentage', 10.00, 20.00, 50.00, 100, 1);

-- RM5 discount, valid until end of month, unlimited uses
INSERT INTO promotions (code, type, value, valid_until, active)
VALUES ('SUMMER5', 'fixed', 5.00, '2025-12-31 23:59:59', 1);
```

#### Validation Logic

1. **AJAX Validation** (Real-time):
   - User enters code
   - JavaScript sends AJAX request to `checkout.php` (action: `validate_promo`)
   - Server queries `promotions` table
   - Validates code exists, is active, within valid date range
   - Checks minimum purchase requirement
   - Checks usage limit (if set)
   - Calculates discount amount
   - Returns JSON response with discount details
   - Updates UI with discount amount

2. **Server-side Validation** (On Submit):
   - Re-validates code on form submission (security check)
   - Applies discount to final amount
   - Saves promo code and discount in order

#### Discount Types

- **Fixed**: Discount amount in RM (e.g., RM10 off)
- **Percentage**: Percentage discount (e.g., 10% off)
  - Optional `max_discount` limit for percentage discounts

#### Validation Rules

- Promotion codes are case-insensitive (converted to uppercase)
- Code must exist in `promotions` table
- Code must be active (`active = 1`)
- Code must be within valid date range (`valid_from` and `valid_until`)
- Minimum purchase requirement must be met (`min_purchase`)
- Usage limit must not be exceeded (`used_count < usage_limit`)
- Discount cannot exceed order total (for fixed type)
- Percentage discounts respect `max_discount` if set

#### Usage Tracking

**Note**: The current implementation does not automatically increment `used_count` when a promotion code is used. You may want to add this functionality when orders are confirmed/paid.

---

## Configuration

### Product Configuration

Located in `order-baju.php` and `checkout.php`:

```php
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
```

### Database Configuration

Located in `config/database.php`:

```php
return [
    'host' => 'localhost',
    'database' => 'u284911434_pcsmy',
    'username' => 'u284911434_pcsadmin',
    'password' => 'PCSadmin25',
    'charset' => 'utf8mb4',
    'options' => [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]
];
```

### Debug Mode

Located in `checkout.php`:

```php
define('DEBUG_MODE', false); // Set to false in production
```

When `DEBUG_MODE` is `true`:
- Detailed error messages shown to users
- Additional logging
- Development-friendly error display

When `DEBUG_MODE` is `false` (Production):
- Generic error messages
- Security-focused error handling
- Minimal information disclosure

---

## Frontend Components

### Order Page (order-baju.php)

**Features**:
- Product variant selection (color swatches)
- Size selection buttons
- Quantity stepper (+/-)
- Add to Cart button
- Cart popup with item display
- Responsive design (mobile/desktop)

**Key Classes**:
- `.order-main`: Main container
- `.product-visual-column`: Left column (product images)
- `.order-form-column`: Right column (form)
- `.variant-selector`: Variant selection buttons
- `.size-selector`: Size selection buttons
- `.quantity-stepper`: Quantity input with +/- buttons

### Checkout Page (checkout.php)

**Features**:
- Order summary with cart items
- Promotion code input with AJAX validation
- Full address form (Malaysian states)
- Form validation (client-side and server-side)
- Pre-order notice banner
- Responsive two-column layout

**Key Classes**:
- `.checkout-main`: Main container
- `.checkout-grid`: Two-column grid layout
- `.checkout-section`: Order summary / Customer info sections
- `.promo-section`: Promotion code input
- `.form-group`: Form field groups
- `.pre-order-banner`: Pre-order notice

### Return Page (return.php)

**Features**:
- Payment status display (Success/Pending/Failed)
- Order details display
- Animated success button
- Auto-redirect to homepage
- Full-screen layout
- Logo at top

**Key Classes**:
- `.return-main`: Full-screen main container
- `.return-logo`: Logo section
- `.status-card`: Payment status card
- `.status-icon`: Status icon (✅/⏳/❌)
- `.order-details`: Order information display

---

## Security Considerations

### Input Validation

- **Server-side validation**: All inputs validated on server
- **SQL Injection Prevention**: PDO prepared statements
- **XSS Prevention**: `htmlspecialchars()` on all user inputs
- **CSRF Protection**: Can be extended (currently not implemented)

### Session Security

- Sessions stored server-side
- No sensitive data in cookies
- Cart cleared after successful payment
- Session timeout handled by PHP

### Payment Security

- Payment processing handled by ToyyibPay
- No credit card data stored locally
- Callback validation (server-to-server)
- Order status updates from callback only

### Database Security

- Database credentials in separate config file
- PDO with exception handling
- Prepared statements for all queries
- Error logging (no sensitive data in user-facing errors)

---

## Error Handling

### Error Levels

1. **User-facing Errors**:
   - Form validation errors
   - Payment processing errors
   - Generic error messages (production mode)

2. **System Errors**:
   - Database connection errors
   - API communication errors
   - Logged to error log

### Error Logging

Errors are logged using PHP's `error_log()` function:
- Database errors
- Payment API errors
- Callback processing errors
- General exceptions

### Debug Mode

When `DEBUG_MODE = true`:
- Detailed error messages
- Stack traces
- Development-friendly output

When `DEBUG_MODE = false` (Production):
- Generic error messages
- Security-focused error handling
- Minimal information disclosure

---

## Deployment Notes

### Pre-Deployment Checklist

- [ ] Update database credentials in `config/database.php`
- [ ] Update ToyyibPay credentials (production keys)
- [ ] Set `DEBUG_MODE = false` in `checkout.php`
- [ ] Verify callback URL is publicly accessible
- [ ] Test payment flow (test transactions)
- [ ] Verify database table creation
- [ ] Check file permissions
- [ ] Update base URL if domain changed

### Production Configuration

1. **Database**:
   - Update credentials in `config/database.php`
   - Ensure database user has proper permissions
   - Test connection

2. **Payment Gateway**:
   - Replace staging credentials with production
   - Update `STAGING_SECRET_KEY` → Production key
   - Update `STAGING_CATEGORY_CODE` → Production category
   - Verify callback URL in ToyyibPay dashboard

3. **Base URL**:
   - Update `$baseUrl` in `checkout.php`
   - Verify all redirect URLs are correct

4. **Error Handling**:
   - Set `DEBUG_MODE = false`
   - Configure error logging
   - Set up monitoring for callback failures

### File Permissions

- PHP files: 644 (readable by web server)
- Config files: 600 or 644 (secure credentials)
- Log files: 644 (writable by web server)

### SSL/HTTPS

- **Required** for payment processing
- Ensure all payment pages use HTTPS
- Update callback URL to HTTPS
- Configure secure session cookies (optional)

---

## Maintenance

### Regular Tasks

1. **Monitor Callback Logs**:
   - Check `toyyibpay_callback.log` for payment callbacks
   - Verify all payments are processed

2. **Database Maintenance**:
   - Monitor order table size
   - Archive old orders (if needed)
   - Backup database regularly

3. **Error Log Monitoring**:
   - Check PHP error logs
   - Monitor for failed payments
   - Review callback failures

### Adding New Promotion Codes

1. Edit `checkout.php`
2. Add code to `$validPromoCodes` array
3. Test code validation
4. Update documentation

### Adding New Product Variants

1. Edit `order-baju.php` and `checkout.php`
2. Add variant to `$variants` array
3. Add variant image to `images/` directory
4. Test variant selection

---

## Troubleshooting

### Common Issues

1. **Cart Not Persisting**:
   - Check session configuration
   - Verify session_start() is called
   - Check PHP session settings

2. **Payment Callback Not Working**:
   - Verify callback URL is publicly accessible
   - Check server logs for POST requests
   - Verify ToyyibPay callback URL configuration

3. **Order Status Not Updating**:
   - Check callback.php is receiving POST data
   - Verify database connection
   - Check error logs

4. **Promotion Code Not Working**:
   - Verify code is in `$validPromoCodes` array
   - Check AJAX request is working
   - Verify server-side validation

---

## Version History

- **v1.0.0** (Current): Initial implementation
  - Session-based cart
  - Full address collection
  - Promotion code system
  - ToyyibPay integration
  - Auto-redirect after payment

---

## Support & Contact

For issues or questions:
- Check error logs
- Review callback logs
- Contact system administrator
- Refer to ToyyibPay documentation

---

**Last Updated**: January 2025
**Version**: 1.0.0

