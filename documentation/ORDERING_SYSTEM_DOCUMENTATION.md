# Ordering System Documentation

**Projek Cuti Sem E-Commerce Ordering System**

This document provides comprehensive documentation of the ordering system implementation, including architecture, workflow, database schema, payment integration, email notifications, and configuration.

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
9. [Email Notification System (SMTP)](#email-notification-system-smtp)
10. [Configuration](#configuration)
11. [Frontend Components](#frontend-components)
12. [Security Considerations](#security-considerations)
13. [Error Handling](#error-handling)
14. [Deployment Notes](#deployment-notes)

---

## Overview

The ordering system is a complete e-commerce solution built with PHP, implementing a session-based shopping cart, full address collection, promotion code discounts, payment processing through ToyyibPay (Malaysian payment gateway), and email notifications via SMTP.

### Key Features

- **Session-based Shopping Cart**: Stores cart items in PHP sessions
- **Product Variants**: Support for multiple color variants (Classic Black, Forest Green, Royal Red)
- **Size Selection**: S, M, L, XL, XXL sizes
- **Quantity Management**: Add multiple quantities of items
- **Full Address Collection**: Complete Malaysian address form with state/district/postcode
- **Order Types**: Pickup or Delivery options
- **Delivery Fee Calculation**: Automatic delivery fee calculation
- **Promotion Code System**: Apply discount codes with validation
- **Payment Integration**: ToyyibPay payment gateway integration
- **Email Notifications**: Order confirmation emails via SMTP (Gmail, Outlook, etc.)
- **Order Tracking**: Database storage with status tracking
- **Auto-redirect**: Automatic redirect after successful payment

---

## System Architecture

### Technology Stack

- **Backend**: PHP 7.4+
- **Database**: MySQL (PDO)
- **Payment Gateway**: ToyyibPay (Staging/Production)
- **Email**: SMTP (PHPMailer recommended)
- **Frontend**: HTML5, CSS3, JavaScript (Vanilla)
- **Session Management**: PHP Sessions

### Architecture Pattern

The system follows a traditional PHP MVC-like structure:

```
User Request → PHP Page → Database/API → Email Notification → Response
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
   │   ├── Order type (Pickup/Delivery)
   │   └── Complete address (3 lines, state, district, postcode)
   ├── Apply promotion code (optional)
   ├── Calculate final amount (including delivery fee)
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
   ├── Update order status in database (status: 'paid')
   ├── Decrease inventory (if in stock)
   ├── Send order confirmation email to customer
   ├── Send order notification email to admin
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
│   ├── version.php         # Asset versioning configuration
│   └── smtp.php            # SMTP email configuration (to be created)
├── includes/
│   ├── db.php              # Database connection helper
│   ├── promotion.php       # Promotion code functions
│   └── email.php           # Email sending functions (to be created)
└── css/
    ├── order-baju.css      # Product page styles
    ├── checkout.css        # Checkout page styles
    └── return.css          # Return page styles
```

### Key Components

- **order-baju.php**: Product selection, variant/size/quantity selection, cart management
- **checkout.php**: Customer information collection, promotion code validation, payment processing
- **callback.php**: Server-to-server payment confirmation (critical for payment validation) + Email notifications
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
    `amount` DECIMAL(10,2) NOT NULL COMMENT 'Item amount (after proportional discount)',
    `promo_code` VARCHAR(50) NULL COMMENT 'Promotion code applied',
    `discount_amount` DECIMAL(10,2) NULL DEFAULT 0.00 COMMENT 'Discount amount applied (proportional)',
    `order_type` ENUM('pickup', 'delivery') DEFAULT 'delivery' COMMENT 'Order type: pickup or delivery',
    `delivery_fee` DECIMAL(10,2) DEFAULT 0.00 COMMENT 'Delivery fee (proportional per item)',
    `final_amount` DECIMAL(10,2) NOT NULL COMMENT 'Final amount (item amount + delivery fee)',
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
- **email**: Customer email (required for email notifications)
- **phone**: Customer phone number (required)
- **address1, address2, address3**: Multi-line address (address1 required for delivery)
- **state**: Malaysian state (required for delivery)
- **district**: District/city (required for delivery)
- **postcode**: 5-digit postcode (required for delivery)
- **variant**: Product variant key (hitamdepan, hijaufront, merahdepan)
- **size**: Size selection (S, M, L, XL, XXL)
- **quantity**: Number of items
- **amount**: Item amount after proportional discount
- **promo_code**: Applied promotion code (if any)
- **discount_amount**: Proportional discount amount in RM
- **order_type**: 'pickup' or 'delivery'
- **delivery_fee**: Proportional delivery fee per item
- **final_amount**: Final amount per item (amount + delivery_fee)
- **billExternalReferenceNo**: Unique reference for ToyyibPay (format: BILL-timestamp-random)
- **status**: Order status ('pending', 'paid', 'failed')
- **created_at**: Order creation timestamp

**Note**: Multiple cart items create multiple order records with the same `billExternalReferenceNo`.

---

## Session Management

### Cart Structure

Cart items are stored in `$_SESSION['cart']` as an array:

```php
$_SESSION['cart'] = [
    [
        'variant' => 'hitamdepan',
        'variant_name' => 'Classic Black',
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

- Sessions stored server-side
- No sensitive payment data in sessions
- Cart cleared after successful payment
- Session timeout handled by PHP

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
   - Creates order record(s) in database (status: 'pending')
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
   - Updates order status in database (status: 'paid')
   - Decreases inventory (if in stock)
   - Sends email notifications (customer + admin)
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
- Send email notifications
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
   - Increments usage count after order creation

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

The system automatically increments `used_count` when a promotion code is used during order creation in `checkout.php`.

---

## Email Notification System (SMTP)

### Overview

The ordering system supports email notifications via SMTP for:
- **Order Confirmation Emails**: Sent to customers after successful payment
- **Order Notification Emails**: Sent to admin/store owner for new orders

### Implementation Guide

#### Step 1: Install PHPMailer

PHPMailer is the recommended library for sending emails via SMTP in PHP.

**Using Composer** (Recommended):
```bash
composer require phpmailer/phpmailer
```

**Manual Installation**:
1. Download PHPMailer from https://github.com/PHPMailer/PHPMailer
2. Extract to `vendor/phpmailer/phpmailer/` directory
3. Include the autoload file in your PHP scripts

#### Step 2: Create SMTP Configuration File

Create `config/smtp.php`:

```php
<?php
/**
 * SMTP Email Configuration
 * Secure SMTP settings for email notifications
 */

// Prevent direct access
if (!defined('SMTP_CONFIG_LOADED')) {
    define('SMTP_CONFIG_LOADED', true);
}

return [
    // SMTP Server Settings
    'host' => 'smtp.gmail.com',              // Gmail SMTP server
    'port' => 587,                            // TLS port (465 for SSL)
    'encryption' => 'tls',                    // 'tls' or 'ssl'
    'username' => 'your-email@gmail.com',     // SMTP username (your email)
    'password' => 'your-app-password',        // SMTP password (app password for Gmail)
    
    // Email Settings
    'from_email' => 'noreply@projectcutisem.com',
    'from_name' => 'Projek Cuti Sem',
    'admin_email' => 'admin@projectcutisem.com',  // Admin email for order notifications
    
    // Gmail App Password Setup:
    // 1. Go to Google Account settings
    // 2. Enable 2-Step Verification
    // 3. Go to App passwords
    // 4. Generate a new app password for "Mail"
    // 5. Use the 16-character password here
    
    // Alternative: Outlook/Hotmail Settings
    // 'host' => 'smtp-mail.outlook.com',
    // 'port' => 587,
    // 'encryption' => 'tls',
    
    // Alternative: Custom SMTP Server
    // 'host' => 'mail.yourdomain.com',
    // 'port' => 587,
    // 'encryption' => 'tls',
];
```

#### Step 3: Create Email Helper Functions

Create `includes/email.php`:

```php
<?php
/**
 * Email Helper Functions
 * Functions for sending order confirmation and notification emails
 */

require_once __DIR__ . '/../vendor/autoload.php'; // PHPMailer autoload
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/**
 * Get SMTP configuration
 */
function getSMTPConfig() {
    static $config = null;
    if ($config === null) {
        $configFile = __DIR__ . '/../config/smtp.php';
        if (file_exists($configFile)) {
            $config = require $configFile;
        } else {
            throw new Exception('SMTP configuration file not found');
        }
    }
    return $config;
}

/**
 * Send email using SMTP
 * 
 * @param string $to Recipient email address
 * @param string $subject Email subject
 * @param string $body Email body (HTML)
 * @param string $toName Recipient name (optional)
 * @return bool True on success, false on failure
 */
function sendSMTPEmail($to, $subject, $body, $toName = '') {
    try {
        $config = getSMTPConfig();
        
        $mail = new PHPMailer(true);
        
        // Server settings
        $mail->isSMTP();
        $mail->Host = $config['host'];
        $mail->SMTPAuth = true;
        $mail->Username = $config['username'];
        $mail->Password = $config['password'];
        $mail->SMTPSecure = $config['encryption'];
        $mail->Port = $config['port'];
        $mail->CharSet = 'UTF-8';
        
        // Recipients
        $mail->setFrom($config['from_email'], $config['from_name']);
        $mail->addAddress($to, $toName);
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $body;
        $mail->AltBody = strip_tags($body); // Plain text version
        
        // Send email
        $mail->send();
        return true;
        
    } catch (Exception $e) {
        error_log("Email sending failed: " . $mail->ErrorInfo);
        return false;
    }
}

/**
 * Send order confirmation email to customer
 * 
 * @param array $orderData Order data from database
 * @param array $orderItems Array of order items (all items with same billExternalReferenceNo)
 * @return bool True on success, false on failure
 */
function sendOrderConfirmationEmail($orderData, $orderItems) {
    $config = getSMTPConfig();
    
    // Calculate totals
    $subtotal = 0;
    $totalDiscount = 0;
    $totalDeliveryFee = 0;
    $finalTotal = 0;
    
    foreach ($orderItems as $item) {
        $subtotal += $item['amount'] + ($item['discount_amount'] ?? 0);
        $totalDiscount += $item['discount_amount'] ?? 0;
        $totalDeliveryFee += $item['delivery_fee'] ?? 0;
        $finalTotal += $item['final_amount'] ?? 0;
    }
    
    // Build email subject
    $subject = "Order Confirmation - #{$orderData['billExternalReferenceNo']} - Projek Cuti Sem";
    
    // Build email body (HTML)
    ob_start();
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background-color: #1a1a1a; color: white; padding: 20px; text-align: center; }
            .content { background-color: #f9f9f9; padding: 20px; }
            .order-details { background-color: white; padding: 15px; margin: 15px 0; border-left: 4px solid #1a1a1a; }
            .item-row { padding: 8px 0; border-bottom: 1px solid #eee; }
            .item-row:last-child { border-bottom: none; }
            .total-row { font-weight: bold; padding: 10px 0; border-top: 2px solid #333; }
            .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1>Order Confirmation</h1>
                <p>Thank you for your order!</p>
            </div>
            <div class="content">
                <p>Dear <?php echo htmlspecialchars($orderData['fullname']); ?>,</p>
                
                <p>Your order has been confirmed and payment has been received. We're preparing your items for <?php echo $orderData['order_type'] === 'pickup' ? 'pickup' : 'delivery'; ?>.</p>
                
                <div class="order-details">
                    <h2>Order Details</h2>
                    <p><strong>Order Reference:</strong> <?php echo htmlspecialchars($orderData['billExternalReferenceNo']); ?></p>
                    <p><strong>Order Date:</strong> <?php echo date('d M Y, h:i A', strtotime($orderData['created_at'])); ?></p>
                    <p><strong>Order Type:</strong> <?php echo ucfirst($orderData['order_type']); ?></p>
                    
                    <h3>Items Ordered</h3>
                    <?php foreach ($orderItems as $item): ?>
                    <div class="item-row">
                        <strong><?php echo htmlspecialchars($item['variant']); ?></strong> - 
                        Size: <?php echo htmlspecialchars($item['size']); ?> - 
                        Quantity: <?php echo $item['quantity']; ?> - 
                        RM <?php echo number_format($item['final_amount'], 2); ?>
                    </div>
                    <?php endforeach; ?>
                    
                    <div class="total-row">
                        <p>Subtotal: RM <?php echo number_format($subtotal, 2); ?></p>
                        <?php if ($totalDiscount > 0): ?>
                        <p>Discount: -RM <?php echo number_format($totalDiscount, 2); ?></p>
                        <?php endif; ?>
                        <?php if ($orderData['order_type'] === 'delivery' && $totalDeliveryFee > 0): ?>
                        <p>Delivery Fee: RM <?php echo number_format($totalDeliveryFee, 2); ?></p>
                        <?php endif; ?>
                        <p>Total: RM <?php echo number_format($finalTotal, 2); ?></p>
                    </div>
                </div>
                
                <?php if ($orderData['order_type'] === 'delivery'): ?>
                <div class="order-details">
                    <h3>Delivery Address</h3>
                    <p>
                        <?php echo htmlspecialchars($orderData['fullname']); ?><br>
                        <?php echo htmlspecialchars($orderData['address1']); ?><br>
                        <?php if ($orderData['address2']): ?>
                            <?php echo htmlspecialchars($orderData['address2']); ?><br>
                        <?php endif; ?>
                        <?php if ($orderData['address3']): ?>
                            <?php echo htmlspecialchars($orderData['address3']); ?><br>
                        <?php endif; ?>
                        <?php echo htmlspecialchars($orderData['postcode']); ?> <?php echo htmlspecialchars($orderData['district']); ?><br>
                        <?php echo htmlspecialchars($orderData['state']); ?><br>
                        Malaysia
                    </p>
                </div>
                <?php endif; ?>
                
                <p>We will notify you once your order is ready for <?php echo $orderData['order_type'] === 'pickup' ? 'pickup' : 'shipment'; ?>.</p>
                
                <p>If you have any questions, please contact us at <?php echo $config['admin_email']; ?>.</p>
                
                <p>Thank you for choosing Projek Cuti Sem!</p>
            </div>
            <div class="footer">
                <p>&copy; <?php echo date('Y'); ?> Projek Cuti Sem. All rights reserved.</p>
            </div>
        </div>
    </body>
    </html>
    <?php
    $body = ob_get_clean();
    
    // Send email
    return sendSMTPEmail(
        $orderData['email'],
        $subject,
        $body,
        $orderData['fullname']
    );
}

/**
 * Send order notification email to admin
 * 
 * @param array $orderData Order data from database
 * @param array $orderItems Array of order items
 * @return bool True on success, false on failure
 */
function sendAdminNotificationEmail($orderData, $orderItems) {
    $config = getSMTPConfig();
    
    // Calculate totals
    $subtotal = 0;
    $totalDiscount = 0;
    $totalDeliveryFee = 0;
    $finalTotal = 0;
    
    foreach ($orderItems as $item) {
        $subtotal += $item['amount'] + ($item['discount_amount'] ?? 0);
        $totalDiscount += $item['discount_amount'] ?? 0;
        $totalDeliveryFee += $item['delivery_fee'] ?? 0;
        $finalTotal += $item['final_amount'] ?? 0;
    }
    
    $subject = "New Order Received - #{$orderData['billExternalReferenceNo']}";
    
    ob_start();
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background-color: #dc3545; color: white; padding: 20px; text-align: center; }
            .content { background-color: #f9f9f9; padding: 20px; }
            .order-details { background-color: white; padding: 15px; margin: 15px 0; border-left: 4px solid #dc3545; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1>New Order Received</h1>
            </div>
            <div class="content">
                <p>A new order has been received and paid:</p>
                
                <div class="order-details">
                    <h2>Order #<?php echo htmlspecialchars($orderData['billExternalReferenceNo']); ?></h2>
                    <p><strong>Customer:</strong> <?php echo htmlspecialchars($orderData['fullname']); ?></p>
                    <p><strong>Email:</strong> <?php echo htmlspecialchars($orderData['email']); ?></p>
                    <p><strong>Phone:</strong> <?php echo htmlspecialchars($orderData['phone']); ?></p>
                    <p><strong>Order Type:</strong> <?php echo ucfirst($orderData['order_type']); ?></p>
                    <p><strong>Total Amount:</strong> RM <?php echo number_format($finalTotal, 2); ?></p>
                    <p><strong>Date:</strong> <?php echo date('d M Y, h:i A', strtotime($orderData['created_at'])); ?></p>
                </div>
                
                <p>Please process this order in the admin panel.</p>
            </div>
        </div>
    </body>
    </html>
    <?php
    $body = ob_get_clean();
    
    return sendSMTPEmail(
        $config['admin_email'],
        $subject,
        $body
    );
}
```

#### Step 4: Integrate Email Notifications in callback.php

Update `callback.php` to send emails after successful payment:

```php
<?php
// ... existing code ...

// After updating order status to 'paid' (around line 67)
if ($statusId == 1) {
    try {
        // ... existing database update code ...
        
        // Include email functions
        require_once __DIR__ . '/includes/email.php';
        require_once __DIR__ . '/includes/db.php';
        
        // Get order data (first order item contains customer info)
        $orderDataStmt = $pdo->prepare("
            SELECT DISTINCT 
                fullname, email, phone, address1, address2, address3,
                state, district, postcode, order_type, billExternalReferenceNo, created_at
            FROM orders 
            WHERE billExternalReferenceNo = :ref_no
            LIMIT 1
        ");
        $orderDataStmt->execute([':ref_no' => $orderId]);
        $orderData = $orderDataStmt->fetch();
        
        // Get all order items
        $itemsStmt = $pdo->prepare("
            SELECT variant, size, quantity, amount, discount_amount, 
                   delivery_fee, final_amount
            FROM orders 
            WHERE billExternalReferenceNo = :ref_no
        ");
        $itemsStmt->execute([':ref_no' => $orderId]);
        $orderItems = $itemsStmt->fetchAll();
        
        // Send customer confirmation email
        if ($orderData && !empty($orderData['email'])) {
            sendOrderConfirmationEmail($orderData, $orderItems);
        }
        
        // Send admin notification email
        sendAdminNotificationEmail($orderData, $orderItems);
        
    } catch (Exception $e) {
        error_log("Email sending error: " . $e->getMessage());
        // Don't fail the callback if email fails
    }
}
```

### SMTP Configuration Options

#### Gmail SMTP

```php
'host' => 'smtp.gmail.com',
'port' => 587,
'encryption' => 'tls',
'username' => 'your-email@gmail.com',
'password' => 'your-16-character-app-password',
```

**Gmail Setup Steps**:
1. Enable 2-Step Verification in Google Account
2. Go to App passwords (https://myaccount.google.com/apppasswords)
3. Select "Mail" and "Other (Custom name)"
4. Generate password and use the 16-character password

#### Outlook/Hotmail SMTP

```php
'host' => 'smtp-mail.outlook.com',
'port' => 587,
'encryption' => 'tls',
'username' => 'your-email@outlook.com',
'password' => 'your-password',
```

#### Custom SMTP Server

```php
'host' => 'mail.yourdomain.com',
'port' => 587,  // or 465 for SSL
'encryption' => 'tls',  // or 'ssl' for port 465
'username' => 'noreply@yourdomain.com',
'password' => 'your-password',
```

### Testing Email Functionality

Create a test script `test_email.php`:

```php
<?php
require_once __DIR__ . '/includes/email.php';

// Test basic email sending
$result = sendSMTPEmail(
    'test@example.com',
    'Test Email',
    '<h1>Test Email</h1><p>This is a test email from Projek Cuti Sem.</p>'
);

if ($result) {
    echo "Email sent successfully!";
} else {
    echo "Email failed to send. Check error logs.";
}
```

### Email Template Customization

Email templates can be customized by modifying the HTML in `sendOrderConfirmationEmail()` and `sendAdminNotificationEmail()` functions in `includes/email.php`.

**Template Variables Available**:
- `$orderData`: Customer information and order metadata
- `$orderItems`: Array of ordered items
- `$subtotal`, `$totalDiscount`, `$totalDeliveryFee`, `$finalTotal`: Calculated totals

### Troubleshooting Email Issues

1. **Email not sending**:
   - Check SMTP credentials in `config/smtp.php`
   - Verify PHPMailer is installed correctly
   - Check PHP error logs for detailed error messages
   - Test SMTP connection using `test_email.php`

2. **Gmail authentication errors**:
   - Ensure 2-Step Verification is enabled
   - Use App Password (not regular password)
   - Check that "Less secure app access" is not required (use App Password instead)

3. **Emails going to spam**:
   - Use a verified sender email address
   - Configure SPF, DKIM records for your domain
   - Avoid spam trigger words in subject/body
   - Use proper email templates (not plain text)

4. **Port/encryption errors**:
   - Port 587 = TLS encryption
   - Port 465 = SSL encryption
   - Ensure firewall allows outbound SMTP connections

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
```

### Database Configuration

Located in `config/database.php`:

```php
return [
    'host' => 'localhost',
    'database' => 'u284911514_pcsmy',
    'username' => 'u284911514_pcsadmin',
    'password' => 'PCSadmin25',
    'charset' => 'utf8mb4',
    'options' => [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]
];
```

### SMTP Configuration

Located in `config/smtp.php` (to be created):

See [Email Notification System (SMTP)](#email-notification-system-smtp) section for configuration details.

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
- Order type selection (Pickup/Delivery)
- Delivery fee calculation
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

### Email Security

- SMTP credentials stored in secure config file
- Passwords should use App Passwords (Gmail) or strong passwords
- Email templates use `htmlspecialchars()` to prevent XSS
- Email sending failures logged but don't expose credentials

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
   - Email sending errors
   - Logged to error log

### Error Logging

Errors are logged using PHP's `error_log()` function:
- Database errors
- Payment API errors
- Callback processing errors
- Email sending errors
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
- [ ] Configure SMTP settings in `config/smtp.php`
- [ ] Install PHPMailer (via Composer or manual)
- [ ] Test email functionality
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

3. **SMTP/Email**:
   - Configure SMTP settings in `config/smtp.php`
   - Use production email address (not personal Gmail for high volume)
   - Set up App Password for Gmail (if using)
   - Test email sending functionality
   - Configure SPF/DKIM records for domain emails

4. **Base URL**:
   - Update `$baseUrl` in `checkout.php`
   - Verify all redirect URLs are correct

5. **Error Handling**:
   - Set `DEBUG_MODE = false`
   - Configure error logging
   - Set up monitoring for callback failures
   - Monitor email sending failures

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

2. **Monitor Email Logs**:
   - Check PHP error logs for email sending failures
   - Verify emails are being sent successfully
   - Test email functionality periodically

3. **Database Maintenance**:
   - Monitor order table size
   - Archive old orders (if needed)
   - Backup database regularly

4. **Error Log Monitoring**:
   - Check PHP error logs
   - Monitor for failed payments
   - Review callback failures
   - Review email sending errors

### Adding New Promotion Codes

1. Insert code into `promotions` table via admin panel or SQL
2. Set appropriate limits and validity dates
3. Test code validation
4. Update documentation if needed

### Adding New Product Variants

1. Add variant to `product_variants` table
2. Add variant image to `images/` directory
3. Update `$variants` array in `order-baju.php` and `checkout.php`
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
   - Verify code exists in `promotions` table
   - Check code is active
   - Verify minimum purchase and usage limits
   - Check AJAX request is working
   - Verify server-side validation

5. **Emails Not Sending**:
   - Verify SMTP configuration in `config/smtp.php`
   - Check PHPMailer is installed correctly
   - Test SMTP connection using `test_email.php`
   - Check PHP error logs for detailed error messages
   - Verify SMTP credentials (use App Password for Gmail)
   - Check firewall allows outbound SMTP connections

6. **Emails Going to Spam**:
   - Use verified sender email address
   - Configure SPF/DKIM records for domain
   - Avoid spam trigger words
   - Use proper HTML email templates

---

## Version History

- **v1.0.0** (Current): Initial implementation
  - Session-based cart
  - Full address collection
  - Order types (Pickup/Delivery)
  - Delivery fee calculation
  - Promotion code system
  - ToyyibPay integration
  - Auto-redirect after payment
  - SMTP email notifications (to be implemented)

---

## Support & Contact

For issues or questions:
- Check error logs
- Review callback logs
- Check email sending logs
- Contact system administrator
- Refer to ToyyibPay documentation
- Refer to PHPMailer documentation

---

**Last Updated**: January 2025  
**Version**: 1.0.0  
**Documentation**: Ordering System with SMTP Email Integration
