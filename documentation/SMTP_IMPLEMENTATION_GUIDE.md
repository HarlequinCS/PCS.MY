# SMTP Email Implementation Guide

## Overview

This guide documents the implementation of SMTP email notifications for order confirmations using Hostinger SMTP, with duplicate email protection.

---

## Implementation Summary

### Files Created/Modified

1. **Database Migration**: `DB Update/add_email_sent_column.sql`
   - Adds `email_sent` column to `orders` table
   - Prevents duplicate email sending

2. **SMTP Configuration**: `config/smtp.php`
   - Hostinger SMTP settings
   - Supports environment variables for secure credential management

3. **Email Functions**: `includes/email.php`
   - PHPMailer-based email sending functions
   - Order confirmation email template

4. **Callback Handler**: `callback.php` (modified)
   - Integrated email sending logic
   - Duplicate protection using `email_sent` flag

---

## Installation Steps

### Step 1: Install PHPMailer

**Option A: Using Composer (Recommended)**

```bash
cd /path/to/your/project
composer require phpmailer/phpmailer
```

**Option B: Manual Installation**

1. Download PHPMailer from: https://github.com/PHPMailer/PHPMailer
2. Extract to `vendor/phpmailer/phpmailer/` directory
3. Ensure the directory structure matches: `vendor/phpmailer/phpmailer/src/PHPMailer.php`

### Step 2: Run Database Migration

Execute the SQL script to add the `email_sent` column:

```sql
-- Run this SQL in your database
ALTER TABLE `orders` 
ADD COLUMN `email_sent` TINYINT(1) NOT NULL DEFAULT 0 
COMMENT 'Email sent flag: 0 = not sent, 1 = sent' 
AFTER `status`;

ALTER TABLE `orders`
ADD INDEX `idx_email_sent` (`email_sent`);
```

Or use the provided SQL file: `DB Update/add_email_sent_column.sql`

### Step 3: Configure SMTP Settings

Edit `config/smtp.php` and set your Hostinger SMTP credentials:

```php
return [
    'host' => 'smtp.hostinger.com',
    'port' => 465,
    'encryption' => 'ssl',
    'username' => 'noreply@projectcutisem.com',  // Your domain email
    'password' => 'your-smtp-password',           // Your SMTP password
    'from_email' => 'noreply@projectcutisem.com', // Must match username
    'from_name' => 'Projek Cuti Sem',
];
```

**Important Notes:**
- `from_email` must match `username` for Hostinger SMTP
- Use your domain email address (not Gmail/Outlook)
- Get your SMTP password from Hostinger email account settings

### Step 4: Test Email Functionality

Create a test file `test_email.php`:

```php
<?php
define('DB_CONFIG_LOADED', true);
define('SMTP_CONFIG_LOADED', true);
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/email.php';

// Test email sending
$result = sendSMTPEmail(
    'your-test-email@example.com',
    'Test Email - Projek Cuti Sem',
    '<h1>Test Email</h1><p>This is a test email from your ordering system.</p>'
);

if ($result) {
    echo "Email sent successfully!";
} else {
    echo "Email failed. Check error logs.";
}
```

Access via browser: `https://yourdomain.com/test_email.php`

**Remember to delete this test file after testing!**

---

## How It Works

### Email Sending Flow

1. **Payment Callback Received** (`callback.php`)
   - ToyyibPay sends POST request with `status_id = 1` (payment successful)

2. **Database Update**
   - Order status updated to `'paid'`
   - Inventory decreased (if applicable)

3. **Duplicate Protection Check**
   - Query checks if `email_sent = 0` for this order reference
   - If `email_sent = 1`, skip email sending (already sent)

4. **Email Sending** (if `email_sent = 0`)
   - Fetch order data and items from database
   - Call `sendOrderConfirmationEmail()` function
   - Send email via Hostinger SMTP using PHPMailer

5. **Update Flag** (if email sent successfully)
   - Update `email_sent = 1` for all orders with this reference
   - Prevents duplicate emails on retry callbacks

### Duplicate Protection Logic

```php
// Check if email already sent
SELECT email_sent FROM orders 
WHERE billExternalReferenceNo = :ref_no AND status = 'paid' 
LIMIT 1

// Only send if email_sent = 0
if ($emailCheck['email_sent'] == 0) {
    // Send email
    sendOrderConfirmationEmail($orderData, $orderItems);
    
    // Update flag after successful send
    UPDATE orders SET email_sent = 1 
    WHERE billExternalReferenceNo = :ref_no
}
```

---

## Environment Variables (Optional but Recommended)

For production, use environment variables instead of hardcoded credentials:

### Set Environment Variables

**Linux/Apache (.htaccess or server config):**
```apache
SetEnv SMTP_HOST smtp.hostinger.com
SetEnv SMTP_PORT 465
SetEnv SMTP_ENCRYPTION ssl
SetEnv SMTP_USERNAME noreply@projectcutisem.com
SetEnv SMTP_PASSWORD your-secure-password
SetEnv SMTP_FROM_EMAIL noreply@projectcutisem.com
SetEnv SMTP_FROM_NAME "Projek Cuti Sem"
```

**PHP-FPM (php.ini or pool config):**
```ini
env[SMTP_HOST] = smtp.hostinger.com
env[SMTP_PORT] = 465
env[SMTP_ENCRYPTION] = ssl
env[SMTP_USERNAME] = noreply@projectcutisem.com
env[SMTP_PASSWORD] = your-secure-password
```

The `config/smtp.php` file automatically reads from environment variables if available.

---

## Security Best Practices

1. **Never commit SMTP credentials to version control**
   - Add `config/smtp.php` to `.gitignore` (or use environment variables)

2. **Use environment variables in production**
   - More secure than config files
   - Easier credential rotation

3. **Restrict file permissions**
   - Config files: 600 (owner read/write only)
   - PHP files: 644 (readable by web server)

4. **Monitor email logs**
   - Check PHP error logs for email sending failures
   - Monitor for suspicious activity

---

## Troubleshooting

### Email Not Sending

1. **Check PHPMailer Installation**
   - Verify `vendor/autoload.php` exists (Composer)
   - Or verify manual installation path

2. **Verify SMTP Credentials**
   - Test with `test_email.php`
   - Check Hostinger email account settings
   - Verify `from_email` matches `username`

3. **Check PHP Error Logs**
   - Look for SMTP connection errors
   - Check for authentication failures
   - Verify port 465 and SSL encryption

4. **Firewall/Server Issues**
   - Ensure port 465 (SSL) is not blocked
   - Check server outbound connection rules

### Duplicate Emails Being Sent

1. **Verify Database Migration**
   - Check `email_sent` column exists
   - Verify default value is `0`

2. **Check Query Logic**
   - Ensure `email_sent` is checked before sending
   - Verify flag is updated after successful send

3. **Race Conditions**
   - Multiple callbacks at exact same time (rare)
   - Current implementation handles this via database check

### Email Going to Spam

1. **SPF/DKIM Records**
   - Configure SPF record for your domain
   - Set up DKIM signing (if available)

2. **Email Content**
   - Avoid spam trigger words
   - Use proper HTML email template
   - Include plain text version

3. **From Address**
   - Use verified domain email
   - Match sender domain with SMTP domain

---

## Testing Checklist

- [ ] PHPMailer installed correctly
- [ ] Database migration executed (`email_sent` column added)
- [ ] SMTP credentials configured in `config/smtp.php`
- [ ] Test email sent successfully using `test_email.php`
- [ ] Test order payment callback triggers email
- [ ] Verify duplicate protection (callback received twice)
- [ ] Check email received in inbox (not spam)
- [ ] Verify `email_sent` flag updated to `1` after sending
- [ ] Test error handling (invalid credentials, etc.)

---

## Code Changes Summary

### callback.php Modifications

**Added after database update (around line 107):**
- Email sending logic with duplicate protection
- Check `email_sent` flag before sending
- Update `email_sent = 1` after successful send
- Error handling (email failures don't break callback)

### Database Changes

**Added column to `orders` table:**
- `email_sent` TINYINT(1) NOT NULL DEFAULT 0
- Index for performance: `idx_email_sent`

---

## Support

For issues:
1. Check PHP error logs
2. Verify SMTP credentials
3. Test with `test_email.php`
4. Check Hostinger email account settings
5. Review callback logs (`toyyibpay_callback.log`)

---

**Last Updated**: January 2025  
**Version**: 1.0.0

