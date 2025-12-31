# Payment System Developer Guide

## 📋 Table of Contents

1. [Overview](#overview)
2. [System Architecture](#system-architecture)
3. [Payment Flow](#payment-flow)
4. [File Documentation](#file-documentation)
   - [order-baju.php](#order-bajuphp)
   - [return.php](#returnphp)
   - [callback.php](#callbackphp)
5. [Database Structure](#database-structure)
6. [Configuration](#configuration)
7. [API Integration](#api-integration)
8. [Error Handling](#error-handling)
9. [Security Considerations](#security-considerations)
10. [Testing](#testing)
11. [Troubleshooting](#troubleshooting)

---

## Overview

This payment system integrates with **ToyyibPay** (Malaysian payment gateway) for processing online payments. The system handles order creation, payment processing, and order confirmation for a single product (Premium Shirt) with multiple variants and sizes.

### Key Features

- ✅ Product order form with variant and size selection
- ✅ Real-time price calculation
- ✅ Database order storage
- ✅ ToyyibPay API integration
- ✅ Payment status handling
- ✅ Server-to-server callback processing
- ✅ User-friendly return page with animations
- ✅ Auto-redirect after payment confirmation

---

## System Architecture

```
┌─────────────────┐
│   User Browser  │
└────────┬────────┘
         │
         ▼
┌─────────────────┐
│ order-baju.php  │ ◄─── Order Form & Payment Initiation
└────────┬────────┘
         │
         │ 1. Create Order (DB)
         │ 2. Create Bill (ToyyibPay API)
         │ 3. Redirect to Payment
         │
         ▼
┌─────────────────┐
│  ToyyibPay API  │ ◄─── Payment Processing
└────────┬────────┘
         │
         ├─────────────────┐
         │                 │
         ▼                 ▼
┌─────────────────┐  ┌─────────────────┐
│  return.php     │  │  callback.php   │
│  (User-facing)  │  │  (Server-to-    │
│                 │  │   Server)       │
└─────────────────┘  └─────────────────┘
         │                 │
         │                 │ Update DB Status
         │                 │
         └────────┬────────┘
                  │
                  ▼
         ┌─────────────────┐
         │  Database       │
         │  (orders table) │
         └─────────────────┘
```

---

## Payment Flow

### Step-by-Step Process

1. **User visits order page** (`order-baju.php`)
   - Selects product variant (hitamdepan, hijaufront, merahdepan)
   - Selects size (S, M, L, XL, XXL)
   - Enters quantity
   - Fills in personal information (name, email, phone)

2. **Form submission** (`order-baju.php`)
   - Validates all input fields
   - Calculates total amount (price × quantity)
   - Generates unique `billExternalReferenceNo`
   - Saves order to database with status `'pending'`
   - Prepares ToyyibPay bill data
   - Calls ToyyibPay API to create bill
   - Receives `BillCode` from API
   - Redirects user to ToyyibPay payment page

3. **Payment processing** (ToyyibPay)
   - User completes payment on ToyyibPay platform
   - Payment methods: FPX, Credit/Debit Card, E-wallets

4. **After payment** (Two parallel processes)

   **A. Return URL** (`return.php`)
   - User redirected back to `return.php`
   - Displays payment status to user
   - Shows success animation (if payment successful)
   - Auto-redirects to homepage after 3 seconds

   **B. Callback URL** (`callback.php`)
   - ToyyibPay sends POST request to `callback.php`
   - Server-to-server notification (authoritative source)
   - Logs all callback data
   - Updates database order status (if implemented)
   - Returns HTTP 200 to acknowledge receipt

---

## File Documentation

### order-baju.php

**Purpose:** Main order form page that handles order creation and payment initiation.

**Location:** `/order-baju.php`

**Key Responsibilities:**
- Display product order form
- Handle form validation
- Save orders to database
- Integrate with ToyyibPay API
- Redirect users to payment page

#### Configuration

```php
// ToyyibPay Credentials
define('STAGING_SECRET_KEY', 'dh1kldj2-j8db-ofm0-qauv-e037egz4oiui');
define('STAGING_CATEGORY_CODE', 'g3ii2ms2');

// Base URL
$baseUrl = 'https://projectcutisem.com';

// Product Configuration
define('PRODUCT_NAME', 'Baju Premium');
define('PRICE_PER_SHIRT', 50.00); // RM50 per shirt

// Debug Mode
define('DEBUG_MODE', true); // Set to false in production
```

#### Product Variants

```php
$variants = [
    'hitamdepan' => [
        'name' => 'Hitam Depan',
        'image' => 'images/hitamdepan.svg'
    ],
    'hijaufront' => [
        'name' => 'Hijau Front',
        'image' => 'images/hijaufront.svg'
    ],
    'hijaudepan' => [
        'name' => 'Merah Depan',
        'image' => 'images/merahdepan.svg'
    ]
];
```

#### Size Options

```php
$sizes = ['S', 'M', 'L', 'XL', 'XXL'];
```

#### Form Fields

| Field | Type | Required | Validation |
|-------|------|----------|------------|
| variant | radio | Yes | Must be valid variant key |
| size | select | Yes | Must be in sizes array |
| quantity | number | Yes | Minimum 1 |
| name | text | Yes | Non-empty string |
| email | email | Yes | Valid email format |
| phone | tel | Yes | Non-empty string |

#### Database Operations

**Auto-create table:**
- Function `createOrdersTableIfNotExists()` automatically creates the `orders` table if it doesn't exist
- Called on every page load
- Logs success/error to error log

**Order insertion:**
```php
INSERT INTO orders (
    name, email, phone, variant, size, quantity,
    amount, billExternalReferenceNo, status, created_at
) VALUES (
    :name, :email, :phone, :variant, :size, :quantity,
    :amount, :billExternalReferenceNo, 'pending', NOW()
)
```

#### ToyyibPay API Integration

**Endpoint:** `https://dev.toyyibpay.com/index.php/api/createBill`

**Request Method:** POST

**Content-Type:** `application/x-www-form-urlencoded`

**Required Parameters:**

```php
$billData = [
    'userSecretKey' => STAGING_SECRET_KEY,
    'categoryCode' => STAGING_CATEGORY_CODE,
    'billName' => PRODUCT_NAME,
    'billDescription' => 'Baju Premium - Variant: {variant}, Size: {size}, Quantity: {quantity}',
    'billPriceSetting' => 1, // Fixed amount
    'billPayorInfo' => 1, // Collect payer info
    'billAmount' => $billAmount, // Amount in sen (RM × 100)
    'billExternalReferenceNo' => $billExternalReferenceNo,
    'billTo' => $name,
    'billEmail' => $email,
    'billPhone' => $phone,
    'billReturnUrl' => $baseUrl . '/return.php',
    'billCallbackUrl' => $baseUrl . '/callback.php',
    // Optional parameters...
];
```

**Response Format:**

```json
[
    {
        "BillCode": "abc123xyz",
        "BillName": "Baju Premium",
        "BillDescription": "...",
        "BillAmount": "5000",
        "BillExternalReferenceNo": "BILL-1234567890-abc",
        "BillStatus": "1"
    }
]
```

**Error Handling:**

- cURL errors: Logged and displayed to user
- HTTP errors: Logged with status code and response
- JSON decode errors: Logged with error message
- API errors: Logged with error message from ToyyibPay
- Missing BillCode: Logged and displayed to user

#### JavaScript Features

- Variant image preview on selection
- Real-time total amount calculation
- Form validation feedback
- Console logging for debugging

#### Dependencies

- `includes/db.php` - Database connection
- `config/database.php` - Database configuration
- `css/order-baju.css` - Styling
- `js/workshirt.js` - JavaScript functionality (if used)

---

### return.php

**Purpose:** User-facing return page after payment completion. Displays payment status and provides user feedback.

**Location:** `/return.php`

**Key Responsibilities:**
- Display payment status to user
- Show order details (if available)
- Provide animated success feedback
- Auto-redirect to homepage after countdown

#### URL Parameters

| Parameter | Type | Description |
|-----------|------|-------------|
| status_id | int | Payment status (1=Success, 2=Pending, 3=Failed) |
| billcode | string | ToyyibPay bill code (optional) |
| order_id | string | Order reference number (optional) |

#### Status Handling

```php
switch ($statusId) {
    case 1: // Success
        $statusMessage = 'Payment Successful!';
        $statusClass = 'success';
        $statusIcon = '✅';
        break;
    case 2: // Pending
        $statusMessage = 'Payment Pending';
        $statusClass = 'pending';
        $statusIcon = '⏳';
        break;
    case 3: // Failed
        $statusMessage = 'Payment Failed';
        $statusClass = 'failed';
        $statusIcon = '❌';
        break;
    default: // Unknown
        $statusMessage = 'Payment Status Unknown';
        $statusClass = 'unknown';
        $statusIcon = '❓';
        break;
}
```

#### Features

**Success Animation:**
- Animated order button with truck animation
- Auto-triggers 1 second after page load
- 10-second truck animation
- 3-second countdown before redirect
- Button text updates: "Complete Order" → "Redirecting in 3s..." → "Redirecting in 2s..." → etc.

**Order Details Display:**
- Shows Order ID (if available)
- Shows Bill Code (if available)
- Shows order status

**Responsive Design:**
- Mobile-friendly layout
- Animated background elements
- Smooth transitions and animations

#### JavaScript Functionality

**Auto-trigger Animation:**
```javascript
// Automatically triggers button animation after 1 second
setTimeout(function() {
    orderButton.click();
}, 1000);
```

**Countdown & Redirect:**
```javascript
// Starts 3-second countdown after animation completes (10 seconds)
setTimeout(function() {
    startCountdownAndRedirect(3);
}, 10000);
```

**Homepage Redirect:**
```javascript
const homepageUrl = '/'; // Adjust as needed
window.location.href = homepageUrl;
```

#### Dependencies

- `css/order-baju.css` - Base styling
- `css/orderbutton.css` - Button animation styles
- `js/orderbutton.js` - Button animation logic
- jQuery 3.7.1 (CDN) - Required for button animation
- Google Fonts: Roboto (CDN)
- Meyer Reset CSS (CDN)

#### Important Notes

⚠️ **DO NOT use this page for payment validation!**

- This page is for **user experience only**
- Status can be manipulated by users
- Always use `callback.php` as the authoritative source for payment status
- This page may not receive all payment updates

---

### callback.php

**Purpose:** Server-to-server callback handler that receives payment notifications from ToyyibPay. This is the **authoritative source** for payment confirmation.

**Location:** `/callback.php`

**Key Responsibilities:**
- Receive POST data from ToyyibPay
- Log all callback data for debugging
- Update database order status (if implemented)
- Return HTTP 200 to acknowledge receipt

#### How It Works

1. **ToyyibPay sends POST request** to `callback.php` after payment status changes
2. **Callback captures all POST data** and logs it
3. **Extracts payment information** (status_id, billcode, order_id, etc.)
4. **Updates database** (if payment successful and code is implemented)
5. **Returns HTTP 200** to acknowledge receipt

#### POST Data Structure

```php
$postData = [
    'billcode' => 'abc123xyz',
    'order_id' => 'BILL-1234567890-abc',
    'status_id' => '1', // 1=Success, 2=Pending, 3=Failed
    'status' => '1',
    'msg' => 'success',
    'transaction_id' => 'TXN123456',
    'amount' => '5000' // Amount in sen
];
```

#### Logging

**Log File:** `toyyibpay_callback.log` (in same directory as callback.php)

**Log Format:**
```
[2024-01-15 14:30:25] POST Data: {
    "billcode": "abc123xyz",
    "order_id": "BILL-1234567890-abc",
    "status_id": "1",
    ...
}
```

#### Database Update (Optional)

Currently commented out. To implement:

```php
if ($statusId == 1) {
    try {
        $pdo = getDBConnection();
        
        $stmt = $pdo->prepare("
            UPDATE orders 
            SET 
                status = 'paid',
                updated_at = NOW()
            WHERE 
                billExternalReferenceNo = :order_id
        ");
        
        $stmt->execute([
            ':order_id' => $orderId
        ]);
        
        error_log("Payment updated successfully: Order ID {$orderId}");
        
    } catch (PDOException $e) {
        error_log("Database update failed: " . $e->getMessage());
    }
}
```

#### Response

**Always returns HTTP 200:**
```php
http_response_code(200);
exit;
```

**Important:**
- Must return HTTP 200 to acknowledge receipt
- No HTML output
- No user redirects
- No error messages to user
- All errors should be logged, not displayed

#### Security Considerations

⚠️ **Production Recommendations:**

1. **IP Whitelisting:** Verify callback source IP
2. **Signature Verification:** Verify callback signature (if provided by ToyyibPay)
3. **HTTPS Only:** Use HTTPS for callback URL
4. **Rate Limiting:** Implement rate limiting to prevent abuse
5. **Log Rotation:** Implement log rotation for callback.log

#### Testing

**Manual Testing:**
1. Complete a test payment
2. Check `toyyibpay_callback.log` for callback data
3. Verify database update (if implemented)
4. Check server logs for errors

**Monitoring:**
- Monitor callback.log file size
- Set up alerts for callback failures
- Track callback response times

---

## Database Structure

### Orders Table

**Table Name:** `orders`

**Auto-creation:** Automatically created by `order-baju.php` if it doesn't exist.

**Schema:**

```sql
CREATE TABLE IF NOT EXISTS `orders` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(255) NOT NULL COMMENT 'Nama user (billTo)',
    `email` VARCHAR(255) NOT NULL COMMENT 'Email user (billEmail)',
    `phone` VARCHAR(50) NOT NULL COMMENT 'Phone user (billPhone)',
    `variant` VARCHAR(50) NOT NULL COMMENT 'Variant: hitamdepan, hijaufront, merahdepan',
    `size` VARCHAR(10) NOT NULL COMMENT 'Size: S, M, L, XL, XXL',
    `quantity` INT(11) NOT NULL DEFAULT 1 COMMENT 'Quantity ordered',
    `amount` DECIMAL(10,2) NOT NULL COMMENT 'Total amount in RM',
    `billExternalReferenceNo` VARCHAR(255) NOT NULL COMMENT 'Unique reference number untuk ToyyibPay',
    `status` VARCHAR(50) NOT NULL DEFAULT 'pending' COMMENT 'Order status: pending, paid, failed',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Order creation timestamp',
    PRIMARY KEY (`id`),
    INDEX `idx_billExternalReferenceNo` (`billExternalReferenceNo`),
    INDEX `idx_status` (`status`),
    INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Status Values:**
- `pending` - Order created, payment not yet completed
- `paid` - Payment successful (updated by callback.php)
- `failed` - Payment failed (updated by callback.php)

**Indexes:**
- Primary key on `id`
- Index on `billExternalReferenceNo` (for quick lookups)
- Index on `status` (for filtering)
- Index on `created_at` (for sorting)

---

## Configuration

### Environment Setup

**Staging Environment:**
- API Endpoint: `https://dev.toyyibpay.com/index.php/api/createBill`
- Payment Page: `https://dev.toyyibpay.com/{BillCode}`
- Secret Key: `dh1kldj2-j8db-ofm0-qauv-e037egz4oiui`
- Category Code: `g3ii2ms2`

**Production Environment:**
- API Endpoint: `https://toyyibpay.com/index.php/api/createBill`
- Payment Page: `https://toyyibpay.com/{BillCode}`
- Secret Key: (Get from ToyyibPay dashboard)
- Category Code: (Get from ToyyibPay dashboard)

### Configuration Files

**Database Configuration:** `config/database.php`
```php
return [
    'host' => 'localhost',
    'database' => 'your_database',
    'username' => 'your_username',
    'password' => 'your_password',
    'charset' => 'utf8mb4',
    'options' => [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]
];
```

**Base URL Configuration:**
Update in `order-baju.php`:
```php
$baseUrl = 'https://yourdomain.com'; // Change to your domain
```

**Product Configuration:**
Update in `order-baju.php`:
```php
define('PRODUCT_NAME', 'Baju Premium');
define('PRICE_PER_SHIRT', 50.00); // Adjust price as needed
```

### Debug Mode

**Development:**
```php
define('DEBUG_MODE', true); // Shows detailed error messages
```

**Production:**
```php
define('DEBUG_MODE', false); // Shows generic error messages
```

---

## API Integration

### ToyyibPay API

**Create Bill Endpoint:**
```
POST https://dev.toyyibpay.com/index.php/api/createBill
Content-Type: application/x-www-form-urlencoded
```

**Request Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| userSecretKey | string | Yes | ToyyibPay secret key |
| categoryCode | string | Yes | Payment category code |
| billName | string | Yes | Bill name |
| billDescription | string | Yes | Bill description |
| billPriceSetting | int | Yes | 1=Fixed, 2=Multiple |
| billPayorInfo | int | Yes | 1=Collect, 0=Don't collect |
| billAmount | int | Yes | Amount in sen (RM × 100) |
| billExternalReferenceNo | string | Yes | Unique reference number |
| billTo | string | No | Payer name |
| billEmail | string | No | Payer email |
| billPhone | string | No | Payer phone |
| billReturnUrl | string | No | Return URL after payment |
| billCallbackUrl | string | No | Callback URL for notifications |

**Response Format:**

**Success:**
```json
[
    {
        "BillCode": "abc123xyz",
        "BillName": "Baju Premium",
        "BillDescription": "...",
        "BillAmount": "5000",
        "BillExternalReferenceNo": "BILL-1234567890-abc",
        "BillStatus": "1"
    }
]
```

**Error:**
```json
[
    {
        "status": "error",
        "msg": "Error message here"
    }
]
```

---

## Error Handling

### order-baju.php

**Validation Errors:**
- Displayed to user in red error box
- All validation errors collected before display
- Form values preserved on error

**API Errors:**
- Logged to error log
- Displayed to user (detailed if DEBUG_MODE=true, generic if false)
- User can retry submission

**Database Errors:**
- Logged to error log
- Displayed to user
- Order not created if database fails

### return.php

**Missing Parameters:**
- Defaults to status "unknown"
- Displays appropriate message
- No errors thrown

**JavaScript Errors:**
- Logged to console
- Global error handler catches all errors
- Page still functions if JavaScript fails

### callback.php

**All Errors:**
- Logged to error log
- Never displayed to user
- Always returns HTTP 200
- Database errors don't fail callback

---

## Security Considerations

### Input Validation

**order-baju.php:**
- ✅ All inputs trimmed
- ✅ Email validation with `filter_var()`
- ✅ Numeric validation for quantity
- ✅ Variant/size whitelist validation
- ✅ Prepared statements for database queries
- ✅ HTML escaping for output

**return.php:**
- ✅ Type casting for status_id
- ✅ HTML escaping for all output
- ✅ No database queries (read-only)

**callback.php:**
- ✅ Type casting for status_id
- ✅ Prepared statements (if database update implemented)
- ✅ No user input displayed

### SQL Injection Prevention

- ✅ All database queries use prepared statements
- ✅ Parameter binding with named placeholders
- ✅ No direct string concatenation in SQL

### XSS Prevention

- ✅ All user input escaped with `htmlspecialchars()`
- ✅ No raw user input in HTML output
- ✅ JSON encoding for API responses

### CSRF Protection

⚠️ **Not Currently Implemented**

**Recommendation:** Add CSRF tokens for form submissions in production.

### Sensitive Data

- ✅ Credentials stored as constants (consider environment variables for production)
- ✅ No sensitive data in logs (except for debugging)
- ✅ Error messages don't expose system details in production

---

## Testing

### Manual Testing Checklist

**Order Creation:**
- [ ] Form validation works correctly
- [ ] Variant selection updates preview
- [ ] Quantity calculation is correct
- [ ] Database order is created
- [ ] ToyyibPay API call succeeds
- [ ] Redirect to payment page works

**Payment Flow:**
- [ ] Can complete payment on ToyyibPay
- [ ] Return URL redirects correctly
- [ ] Status displays correctly on return.php
- [ ] Callback receives POST data
- [ ] Callback logs data correctly
- [ ] Database updates (if implemented)

**Error Handling:**
- [ ] Validation errors display correctly
- [ ] API errors are handled gracefully
- [ ] Database errors don't crash page
- [ ] Missing parameters handled correctly

### Test Scenarios

**1. Successful Payment:**
1. Fill order form
2. Submit order
3. Complete payment on ToyyibPay
4. Verify return.php shows success
5. Check callback.log for callback data
6. Verify database status updated (if implemented)

**2. Failed Payment:**
1. Fill order form
2. Submit order
3. Cancel or fail payment on ToyyibPay
4. Verify return.php shows failed status
5. Check callback.log for callback data

**3. Validation Errors:**
1. Submit form with empty fields
2. Verify all errors displayed
3. Verify form values preserved

**4. API Errors:**
1. Use invalid credentials
2. Verify error handling
3. Verify user-friendly error message

---

## Troubleshooting

### Common Issues

#### 1. Order not saving to database

**Symptoms:**
- Form submits but no order in database
- Error message about database

**Solutions:**
- Check database connection in `config/database.php`
- Verify database credentials are correct
- Check if table exists (auto-creation may have failed)
- Check error logs for database errors
- Verify database user has INSERT permissions

#### 2. ToyyibPay API errors

**Symptoms:**
- Error message: "ToyyibPay API Error: ..."
- No redirect to payment page

**Solutions:**
- Verify `STAGING_SECRET_KEY` is correct
- Verify `STAGING_CATEGORY_CODE` is correct
- Check API endpoint URL is correct
- Verify network connectivity
- Check error logs for API response
- Verify bill amount is in sen (RM × 100)

#### 3. Callback not received

**Symptoms:**
- Payment completed but no callback data
- callback.log file empty

**Solutions:**
- Verify `billCallbackUrl` is accessible from internet
- Check if callback URL is HTTPS (required in production)
- Verify callback.php returns HTTP 200
- Check server logs for incoming requests
- Verify callback URL is correct in order-baju.php
- Check firewall/security settings

#### 4. Return page shows wrong status

**Symptoms:**
- Payment successful but shows failed
- Status always shows "unknown"

**Solutions:**
- Check URL parameters are being passed correctly
- Verify `status_id` parameter is present
- Check if ToyyibPay is redirecting with correct parameters
- Verify return URL is correct in order-baju.php

#### 5. Button animation not working

**Symptoms:**
- Button doesn't animate
- No countdown/redirect

**Solutions:**
- Verify jQuery is loaded before orderbutton.js
- Check browser console for JavaScript errors
- Verify css/orderbutton.css is loaded
- Verify js/orderbutton.js is loaded
- Check if button HTML structure is correct

### Debugging Tips

**Enable Debug Mode:**
```php
define('DEBUG_MODE', true);
```

**Check Error Logs:**
- PHP error log: Check server error log location
- callback.log: Check `toyyibpay_callback.log` file
- Browser console: Check for JavaScript errors

**Test API Manually:**
```bash
curl -X POST https://dev.toyyibpay.com/index.php/api/createBill \
  -d "userSecretKey=YOUR_KEY&categoryCode=YOUR_CODE&..."
```

**Check Database:**
```sql
SELECT * FROM orders ORDER BY created_at DESC LIMIT 10;
```

**Monitor Network:**
- Use browser DevTools Network tab
- Check API request/response
- Verify redirects are working

---

## Production Checklist

Before deploying to production:

- [ ] Update API endpoint to production URL
- [ ] Update payment page URL to production
- [ ] Replace staging credentials with production credentials
- [ ] Set `DEBUG_MODE` to `false`
- [ ] Update `$baseUrl` to production domain
- [ ] Implement database update in callback.php
- [ ] Add IP whitelisting for callback.php
- [ ] Implement callback signature verification
- [ ] Set up log rotation for callback.log
- [ ] Test with real payment methods
- [ ] Set up monitoring and alerts
- [ ] Review and update security measures
- [ ] Test error handling thoroughly
- [ ] Verify HTTPS is enabled
- [ ] Test on multiple browsers/devices

---

## Support & Resources

- **ToyyibPay Documentation:** https://toyyibpay.com/developers
- **SANDBOX Environment:** https://dev.toyyibpay.com
- **Support:** Contact ToyyibPay support for API issues

---

## Version History

- **v1.0** (Current): Initial implementation
  - Order form with variant/size selection
  - ToyyibPay API integration
  - Callback handling
  - Return page with animations
  - Auto-table creation
  - Comprehensive error handling

---

## License

[Your License Here]

---

**Last Updated:** 2024-01-15  
**Maintained By:** [Your Team Name]

