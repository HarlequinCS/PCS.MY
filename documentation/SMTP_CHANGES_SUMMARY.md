# SMTP Email Implementation - Changes Summary

## Quick Reference: Exact Code Changes

---

## 1. Database Changes

### SQL Script: `DB Update/add_email_sent_column.sql`

```sql
ALTER TABLE `orders` 
ADD COLUMN `email_sent` TINYINT(1) NOT NULL DEFAULT 0 
COMMENT 'Email sent flag: 0 = not sent, 1 = sent' 
AFTER `status`;

ALTER TABLE `orders`
ADD INDEX `idx_email_sent` (`email_sent`);
```

**Action Required**: Run this SQL script in your database.

---

## 2. Configuration File

### New File: `config/smtp.php`

**Location**: `/config/smtp.php`

**Key Settings**:
```php
return [
    'host' => 'smtp.hostinger.com',
    'port' => 465,
    'encryption' => 'ssl',
    'username' => 'noreply@projectcutisem.com',  // YOUR DOMAIN EMAIL
    'password' => 'your-smtp-password',            // YOUR SMTP PASSWORD
    'from_email' => 'noreply@projectcutisem.com', // MUST MATCH username
    'from_name' => 'Projek Cuti Sem',
];
```

**Action Required**: 
1. Update `username` with your Hostinger domain email
2. Update `password` with your SMTP password
3. Ensure `from_email` matches `username`

---

## 3. Email Functions

### New File: `includes/email.php`

**Location**: `/includes/email.php`

**Functions**:
- `getSMTPConfig()` - Loads SMTP configuration
- `sendSMTPEmail()` - Sends email via SMTP
- `sendOrderConfirmationEmail()` - Sends order confirmation email to customer

**Dependencies**: Requires PHPMailer library

---

## 4. Callback Modification

### Modified File: `callback.php`

**Changes**: Added email sending logic after database update (around line 108)

**Key Logic**:
```php
// Check if email already sent (duplicate protection)
SELECT email_sent FROM orders 
WHERE billExternalReferenceNo = :ref_no AND status = 'paid' 
LIMIT 1

// Send email only if email_sent = 0
if ($emailCheck['email_sent'] == 0) {
    // Send email
    sendOrderConfirmationEmail($orderData, $orderItems);
    
    // Update flag after successful send
    UPDATE orders SET email_sent = 1 
    WHERE billExternalReferenceNo = :ref_no
}
```

**Integration Point**: Added after line 107 (after inventory update, before success log)

---

## 5. Installation Requirements

### PHPMailer Installation

**Option A: Composer (Recommended)**
```bash
composer require phpmailer/phpmailer
```

**Option B: Manual**
- Download from https://github.com/PHPMailer/PHPMailer
- Extract to `vendor/phpmailer/phpmailer/`

---

## Implementation Checklist

- [ ] Install PHPMailer (Composer or manual)
- [ ] Run SQL script to add `email_sent` column
- [ ] Configure `config/smtp.php` with Hostinger credentials
- [ ] Test email sending with test script
- [ ] Verify callback.php modifications are in place
- [ ] Test with actual payment callback
- [ ] Verify duplicate protection works (test callback twice)

---

## Testing

### Quick Test Script

Create `test_email.php`:

```php
<?php
define('DB_CONFIG_LOADED', true);
define('SMTP_CONFIG_LOADED', true);
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/email.php';

$result = sendSMTPEmail(
    'your-test@email.com',
    'Test Email',
    '<h1>Test</h1><p>Email test from ordering system</p>'
);

echo $result ? "Email sent!" : "Email failed. Check error logs.";
```

**Remember to delete this file after testing!**

---

## How Duplicate Protection Works

1. **First Callback** (status_id = 1):
   - Status updated to 'paid'
   - `email_sent` = 0 (default)
   - Email sent successfully
   - `email_sent` updated to 1

2. **Duplicate Callback** (same order, status_id = 1):
   - Status already 'paid'
   - `email_sent` = 1 (from first callback)
   - Email sending skipped
   - Log: "Email already sent, skipping duplicate send"

---

## Security Notes

- SMTP credentials in `config/smtp.php` (use environment variables in production)
- Email sending errors logged but don't break callback
- Duplicate protection via database flag (idempotent)
- FROM email must match SMTP username (Hostinger requirement)

---

## Files Created/Modified

**Created**:
- `config/smtp.php`
- `includes/email.php`
- `DB Update/add_email_sent_column.sql`
- `SMTP_IMPLEMENTATION_GUIDE.md`
- `SMTP_CHANGES_SUMMARY.md` (this file)

**Modified**:
- `callback.php` (added email sending logic with duplicate protection)

---

**Last Updated**: January 2025

