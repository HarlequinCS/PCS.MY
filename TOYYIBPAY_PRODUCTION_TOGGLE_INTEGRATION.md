# ToyyibPay Production Toggle Integration Guide

This guide shows you how to integrate the production/staging toggle feature into your existing checkout.php file.

---

## Files Created

1. **`databasefile/database_settings_table.sql`** - SQL script to create settings table
2. **`DB Update/add_toyyibpay_settings_table.php`** - Web-accessible PHP script to create table
3. **`includes/toyyibpay_config.php`** - Configuration helper function
4. **`admin/api/toggle_toyyibpay_mode.php`** - API endpoint for toggling mode
5. **`admin/settings.php`** - Admin settings page with toggle button

---

## Step 1: Create Settings Table

Run the SQL script or use the PHP script:

**Option A: SQL Script**
```sql
-- Run databasefile/database_settings_table.sql in your database
```

**Option B: PHP Script**
```
Access: http://yourdomain.com/DB Update/add_toyyibpay_settings_table.php
Password: PCS@dmin2025!
```

---

## Step 2: Update checkout.php

### 2.1: Replace ToyyibPay Constants Section

**Find this section** (around line 38-40):
```php
// ToyyibPay Staging Credentials
define('STAGING_SECRET_KEY', 'dh1kldj2-j8db-ofm0-qauv-e037egz4oiui');
define('STAGING_CATEGORY_CODE', 'g3ii2ms2');
```

**Replace with**:
```php
// ToyyibPay Configuration (staging credentials kept for backward compatibility)
define('STAGING_SECRET_KEY', 'dh1kldj2-j8db-ofm0-qauv-e037egz4oiui');
define('STAGING_CATEGORY_CODE', 'g3ii2ms2');

// Load ToyyibPay config helper (supports staging/production toggle)
require_once __DIR__ . '/includes/toyyibpay_config.php';
```

### 2.2: Update Bill Data Section

**Find this section** (around line 420-423):
```php
$billData = [
    // Required: ToyyibPay credentials
    'userSecretKey' => STAGING_SECRET_KEY,
    'categoryCode' => STAGING_CATEGORY_CODE,
```

**Replace with**:
```php
// Get ToyyibPay configuration (staging or production based on settings)
$toyyibpayConfig = getToyyibPayConfig();

$billData = [
    // Required: ToyyibPay credentials (from config helper)
    'userSecretKey' => $toyyibpayConfig['secret_key'],
    'categoryCode' => $toyyibpayConfig['category_code'],
```

### 2.3: Update API Endpoint

**Find this line** (around line 468):
```php
$ch = curl_init('https://dev.toyyibpay.com/index.php/api/createBill');
```

**Replace with**:
```php
// Use endpoint from config (staging or production)
$ch = curl_init($toyyibpayConfig['endpoint']);
```

---

## Step 3: Add Settings Link to Admin Sidebar

**File**: `admin/dashboard.php` or any admin page that includes the sidebar

**Find the sidebar navigation** (around line 62-65):
```php
<a href="/admin/promotions.php" class="nav-item">
    <span class="nav-icon">🎟️</span>
    <span>Promotions</span>
</a>
```

**Add after Promotions**:
```php
<a href="/admin/promotions.php" class="nav-item">
    <span class="nav-icon">🎟️</span>
    <span>Promotions</span>
</a>
<a href="/admin/settings.php" class="nav-item">
    <span class="nav-icon">⚙️</span>
    <span>Settings</span>
</a>
```

---

## Step 4: Update admin/settings.php Sidebar

The `admin/settings.php` file tries to include the sidebar from `dashboard.php`. If your sidebar is structured differently, you may need to adjust the include statement or copy the sidebar HTML directly.

**Current include** (line ~140):
```php
<?php include __DIR__ . '/dashboard.php'; // Include sidebar from dashboard ?>
```

**If this doesn't work**, replace with your actual sidebar structure or use:
```php
<!-- Copy sidebar HTML from dashboard.php here -->
```

---

## Step 5: Insert Production Credentials

1. Go to Admin Panel → Settings
2. Enter your production credentials:
   - Production Secret Key
   - Production Category Code
3. Click "Save Credentials"

---

## Step 6: Test the Toggle

1. Verify you're in staging mode (default)
2. Test a checkout with staging
3. Switch to production mode (with confirmation)
4. Test a checkout with production
5. Switch back to staging if needed

---

## Code Snippets for Manual Integration

### Snippet 1: Include Config Helper (in checkout.php)

Add at the top, after database includes:
```php
// Load ToyyibPay config helper (supports staging/production toggle)
require_once __DIR__ . '/includes/toyyibpay_config.php';
```

### Snippet 2: Get Config and Use in Bill Data

```php
// Get ToyyibPay configuration (staging or production based on settings)
$toyyibpayConfig = getToyyibPayConfig();

$billData = [
    'userSecretKey' => $toyyibpayConfig['secret_key'],
    'categoryCode' => $toyyibpayConfig['category_code'],
    // ... rest of bill data
];

// Use config endpoint for API call
$ch = curl_init($toyyibpayConfig['endpoint']);
```

---

## Security Notes

1. **Production credentials are stored in database** (encrypted at database level)
2. **Admin authentication required** to toggle mode
3. **Confirmation dialog** before switching to production
4. **Prepared statements** used for all database operations
5. **Input sanitization** on all user inputs

---

## Troubleshooting

### Settings table not found
- Run the database creation script
- Check database connection
- Verify table was created successfully

### Toggle button not working
- Check browser console for JavaScript errors
- Verify `/admin/api/toggle_toyyibpay_mode.php` is accessible
- Check admin session is active

### Production mode not working
- Verify production credentials are saved in database
- Check `getToyyibPayConfig()` function is working
- Verify endpoint URL is correct

---

**Last Updated**: January 2025

