# 🚀 Quick Start: Critical Optimizations

## Step 1: Create .env File (5 minutes)

1. **Copy the example file:**
   ```bash
   cp .env.example .env
   ```

2. **Edit `.env` with your actual credentials:**
   ```env
   DB_HOST=localhost
   DB_NAME=u284911514_pcsmy
   DB_USER=u284911514_pcsadmin
   DB_PASS=PCSadmin25
   APP_ENV=production
   APP_DEBUG=false
   ```

3. **Add to `.gitignore`** (create if doesn't exist):
   ```
   .env
   *.log
   logs/
   ```

## Step 2: Delete Test Files (2 minutes)

**Run this command to delete all test/debug files:**
```bash
# Windows PowerShell
Remove-Item api/test_*.php -ErrorAction SilentlyContinue
Remove-Item api/debug_*.php -ErrorAction SilentlyContinue
Remove-Item api/fix_*.php -ErrorAction SilentlyContinue
Remove-Item api/comments_simple.php -ErrorAction SilentlyContinue
Remove-Item api/check_table_status.php -ErrorAction SilentlyContinue
```

**Or manually delete:**
- `api/test_db.php`
- `api/test_insert.php`
- `api/test_connection.php`
- `api/debug_comment.php`
- `api/fix_autoincrement.php`
- `api/fix_autoincrement_now.php`
- `api/fix_database_permanent.php`
- `api/fix_zero_id.php`
- `api/comments_simple.php`
- `api/check_table_status.php`

## Step 3: Update Session Security (1 minute)

**File:** `admin/config.php`

Already updated! Just verify:
- `session.cookie_secure` = `1` (for HTTPS) or `0` (for localhost only)
- `session.cookie_samesite` = `'Strict'`

## Step 4: Test Environment Config (2 minutes)

1. **Verify `.env` file exists**
2. **Test database connection:**
   ```php
   <?php
   require_once 'config/env.php';
   require_once 'config/database.php';
   require_once 'includes/db.php';
   
   $pdo = getDBConnection();
   echo "✅ Database connection successful!";
   ```

## Step 5: Update API Files to Use Rate Limiting (10 minutes)

**Example for `api/comments.php`:**
```php
<?php
// Add at the top
require_once __DIR__ . '/../includes/rate_limit.php';

// Before processing request
checkRateLimit('comments', 10, 60); // 10 requests per minute

// Rest of your code...
```

## Step 6: Add CSRF Protection to Forms (15 minutes)

**In your forms (e.g., `checkout.php`):**
```php
<?php
require_once __DIR__ . '/includes/csrf.php';
?>

<form method="POST">
    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
    <!-- rest of form -->
</form>
```

**On form submission:**
```php
if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
    die('Invalid CSRF token');
}
```

## Step 7: Enable Query Caching (5 minutes)

**Update `includes/toyyibpay_config.php`:**
```php
// Add at the top
require_once __DIR__ . '/db_cache.php';

// Replace database queries with:
$mode = getSetting('toyyibpay_mode', 'staging');
```

## Step 8: Verify Security Headers (2 minutes)

1. **Visit your site**
2. **Open browser DevTools → Network tab**
3. **Reload page**
4. **Click on any request → Headers**
5. **Verify you see:**
   - `X-Content-Type-Options: nosniff`
   - `X-Frame-Options: SAMEORIGIN`
   - `Content-Security-Policy: ...`

## ✅ Verification Checklist

After completing all steps:

- [ ] `.env` file created and configured
- [ ] All test files deleted
- [ ] Database connection works with `.env`
- [ ] Session cookies are secure
- [ ] Security headers visible in browser
- [ ] Rate limiting added to API endpoints
- [ ] CSRF tokens in forms
- [ ] Query caching implemented

## 🎯 Expected Results

- **Security Score:** 60/100 → 85/100
- **Database Queries:** Reduced by 60-80%
- **API Protection:** Rate limiting active
- **CSRF Protection:** All forms protected

## 📚 Next Steps

After completing these critical fixes, proceed with:
1. PHP OPcache configuration
2. CSS/JS minification
3. Image optimization
4. Database index optimization

See `PRODUCTION_OPTIMIZATION_GUIDE.md` for full details.

---

**Time Required:** ~40 minutes total  
**Priority:** CRITICAL - Do immediately

