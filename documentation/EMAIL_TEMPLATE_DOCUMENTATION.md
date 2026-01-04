# Email Template Documentation

## Overview

This document describes the professional order confirmation email template implementation with variant name mapping.

---

## Files Structure

### 1. `includes/email_variant_mapping.php`
Variant name mapping functions that convert internal codes to customer-facing names.

**Functions:**
- `getVariantDisplayName($internalCode)` - Maps single variant code to display name
- `mapOrderItemsVariants($orderItems)` - Maps all items in an array to use display names

**Variant Mapping:**
```php
'hitamdepan' => 'Classic Black'
'hijaufront' => 'Forest Green'
'merahdepan' => 'Royal Red'
```

### 2. `includes/email_template_order_confirmation.php`
Professional HTML email template with table-based layout for maximum email client compatibility.

**Features:**
- Table-based layout (email-safe)
- Inline CSS only
- No JavaScript
- Mobile-responsive
- Compatible with Gmail, Outlook, iOS Mail

### 3. `includes/email.php` (Updated)
Main email sending function that uses the new template and variant mapping.

---

## Template Variables

The email template requires the following variables:

### Required Variables

| Variable | Type | Description |
|----------|------|-------------|
| `$customerName` | string | Customer's full name |
| `$orderReference` | string | Order reference number (billExternalReferenceNo) |
| `$orderDate` | string | Formatted order date (e.g., "15 Jan 2025, 02:30 PM") |
| `$orderType` | string | 'delivery' or 'pickup' |
| `$orderItems` | array | Array of items with variant_display_name, size, quantity, final_amount |
| `$subtotal` | float | Subtotal amount |
| `$totalDiscount` | float | Total discount (0 if none) |
| `$totalDeliveryFee` | float | Delivery fee (0 if pickup) |
| `$finalTotal` | float | Final total amount |

### Optional Variables

| Variable | Type | Description | Default |
|----------|------|-------------|---------|
| `$deliveryAddress` | array/null | Delivery address array (only for delivery orders) | null |
| `$logoUrl` | string | URL to brand logo (HTTPS) | 'https://projectcutisem.com/images/PCS%20logo.svg' |
| `$supportEmail` | string | Support contact email | 'info@projectcutisem.com' |

---

## Order Items Array Structure

Each item in `$orderItems` array should have:

```php
[
    'variant' => 'hitamdepan',              // Internal code (used for mapping)
    'variant_display_name' => 'Classic Black', // Display name (added by mapping function)
    'size' => 'M',
    'quantity' => 2,
    'amount' => 100.00,
    'discount_amount' => 0.00,
    'delivery_fee' => 10.00,
    'final_amount' => 110.00
]
```

**Note:** The `variant_display_name` is automatically added by `mapOrderItemsVariants()` function.

---

## Delivery Address Array Structure

For delivery orders, `$deliveryAddress` should contain:

```php
[
    'fullname' => 'Ahmad bin Abdullah',
    'name' => 'Ahmad bin Abdullah',
    'address1' => '123 Jalan Contoh',
    'address2' => 'Taman Contoh',        // Optional
    'address3' => '',                    // Optional
    'state' => 'Selangor',
    'district' => 'Petaling Jaya',
    'postcode' => '47300'
]
```

---

## Usage Example

```php
// Load email functions
require_once __DIR__ . '/includes/email.php';
require_once __DIR__ . '/includes/email_variant_mapping.php';

// Get order data from database
$orderData = [
    'fullname' => 'Ahmad bin Abdullah',
    'email' => 'customer@example.com',
    'billExternalReferenceNo' => 'BILL-1704067200-abc123',
    'created_at' => '2025-01-15 14:30:00',
    'order_type' => 'delivery',
    'address1' => '123 Jalan Contoh',
    'state' => 'Selangor',
    'district' => 'Petaling Jaya',
    'postcode' => '47300'
];

$orderItems = [
    [
        'variant' => 'hitamdepan',  // Internal code
        'size' => 'M',
        'quantity' => 2,
        'amount' => 100.00,
        'discount_amount' => 0.00,
        'delivery_fee' => 10.00,
        'final_amount' => 110.00
    ]
];

// Send email (variant mapping happens automatically)
sendOrderConfirmationEmail($orderData, $orderItems);
```

---

## Email Design Features

### Visual Design
- **Clean & Modern**: Professional transactional email design
- **Brand Logo**: PCS logo at the top
- **Color Palette**: Neutral, trustworthy colors (#1a1a1a, #333333, #666666)
- **Typography**: System fonts for best compatibility

### Layout
- **Table-based**: Maximum email client compatibility
- **600px width**: Optimal for desktop and mobile
- **Responsive**: Adapts to mobile screens
- **Centered**: Professional appearance

### Content Sections
1. **Logo Header**: Brand logo
2. **Header**: "Order Confirmed" title and greeting
3. **Order Summary Box**: 
   - Order Reference
   - Payment Status (Paid)
   - Order Date
   - Order Type
4. **Items List**: Product variant (display name), size, quantity, price
5. **Totals**: Subtotal, discount, delivery fee, total
6. **Delivery Address**: (Only for delivery orders)
7. **Reassurance Message**: Order processing confirmation
8. **Footer**: Brand tagline, support email, copyright

---

## Email Client Compatibility

### Tested & Compatible
- ✅ Gmail (Web, iOS, Android)
- ✅ Outlook (Desktop, Web)
- ✅ Apple Mail (iOS, macOS)
- ✅ Yahoo Mail
- ✅ Thunderbird

### Email-Safe Features Used
- Table-based layout
- Inline CSS only
- No JavaScript
- No external CSS files
- Standard HTML elements
- Safe CSS properties

---

## Variant Mapping Implementation

### How It Works

1. **Internal Storage**: Database stores internal codes (e.g., 'hitamdepan')
2. **Mapping Layer**: `mapOrderItemsVariants()` function converts codes to display names
3. **Email Display**: Template shows customer-friendly names (e.g., 'Classic Black')

### Adding New Variants

To add new variants, update `includes/email_variant_mapping.php`:

```php
function getVariantDisplayName($internalCode) {
    $variantMapping = [
        'hitamdepan' => 'Classic Black',
        'hijaufront' => 'Forest Green',
        'merahdepan' => 'Royal Red',
        'newvariant' => 'New Variant Name'  // Add here
    ];
    
    return $variantMapping[$internalCode] ?? $internalCode;
}
```

---

## Customization

### Changing Logo

Update the `$logoUrl` variable in `sendOrderConfirmationEmail()` function:

```php
$logoUrl = 'https://yourdomain.com/path/to/logo.svg';
```

**Requirements:**
- Must be HTTPS URL
- Publicly accessible
- Recommended format: SVG or PNG
- Recommended size: 120px width

### Changing Support Email

Update the `$supportEmail` variable:

```php
$supportEmail = 'support@yourdomain.com';
```

Or set it in `config/smtp.php` and load from there.

### Changing Brand Tagline

Edit `includes/email_template_order_confirmation.php`, find the footer section:

```php
<p style="margin: 0; font-size: 13px; color: #666666; line-height: 1.6;">
    Your new everyday outfit for class and beyond
</p>
```

---

## Testing

### Preview Email

Use `preview_email.php` to preview the email template in browser:

```
http://localhost/pcs.my/preview_email.php
```

### Test Email Sending

Create a test script:

```php
<?php
define('DB_CONFIG_LOADED', true);
define('SMTP_CONFIG_LOADED', true);
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/email.php';

$orderData = [
    'fullname' => 'Test Customer',
    'email' => 'your-test-email@example.com',
    'billExternalReferenceNo' => 'TEST-ORDER-001',
    'created_at' => date('Y-m-d H:i:s'),
    'order_type' => 'delivery',
    'address1' => '123 Test Street',
    'state' => 'Selangor',
    'district' => 'Petaling Jaya',
    'postcode' => '47300'
];

$orderItems = [
    [
        'variant' => 'hitamdepan',
        'size' => 'M',
        'quantity' => 1,
        'amount' => 50.00,
        'discount_amount' => 0.00,
        'delivery_fee' => 10.00,
        'final_amount' => 60.00
    ]
];

sendOrderConfirmationEmail($orderData, $orderItems);
echo "Test email sent!";
```

---

## Security Notes

1. **XSS Protection**: All user data is escaped using `htmlspecialchars()`
2. **Email Validation**: Email addresses are validated before sending
3. **HTTPS Logo**: Logo URL must use HTTPS
4. **No User Input in Template**: Template variables are sanitized

---

## Troubleshooting

### Variant Names Not Showing

- Check that `email_variant_mapping.php` is included
- Verify variant codes match mapping array keys
- Check error logs for mapping errors

### Logo Not Displaying

- Verify logo URL is accessible (HTTPS)
- Check URL encoding (spaces should be %20)
- Test URL in browser directly

### Email Layout Broken

- Check email client (some strip CSS)
- Verify table structure is intact
- Test in multiple email clients

---

**Last Updated**: January 2025  
**Version**: 2.0.0  
**Template**: Professional Order Confirmation Email

