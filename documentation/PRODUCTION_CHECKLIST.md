# Production Deployment Checklist

## Version Control & Cache Busting

### Centralized Version System
- ✅ `config/version.php` - Central version control system
- ✅ PHP files use `getVersionedAsset()` function
- ⚠️ HTML files (`index.html`, `behind-the-product.html`) use hardcoded version `?v=29`

**Note:** HTML files cannot use PHP functions. To use centralized versioning:
- Option 1: Manually update version number in HTML files when deploying
- Option 2: Convert HTML files to PHP (requires URL changes)
- Option 3: Use build process to inject version numbers

**Current Status:** HTML files use `?v=29` - Update this number manually when deploying CSS/JS changes.

### Files Using Version Control
- ✅ `order-baju.php` - Uses `getVersionedAsset()`
- ✅ `checkout.php` - Uses `getVersionedAsset()`
- ✅ `return.php` - Uses `getVersionedAsset()`
- ✅ `index.html` - Uses hardcoded `?v=29`
- ✅ `behind-the-product.html` - Uses hardcoded `?v=29`

## Production Optimization

### Debug Code Removal
- ✅ `checkout.php` - `DEBUG_MODE = false`
- ✅ `return.php` - Console.log statements removed
- ✅ All error logging uses `error_log()` (server-side only)

### Performance Optimizations
- ✅ `.htaccess` - Cache headers configured
- ✅ `.htaccess` - Gzip compression enabled
- ✅ `.htaccess` - Security headers set
- ✅ CSS/JS files cached for 1 year with version parameters
- ✅ HTML files cached for 1 hour
- ✅ PHP files - no cache (dynamic content)

### Security
- ✅ SQL injection protection (PDO prepared statements)
- ✅ XSS protection (htmlspecialchars)
- ✅ CSRF protection (session-based)
- ✅ Security headers in `.htaccess`
- ✅ Directory browsing disabled
- ✅ Sensitive files protected

## Asset Version Update Instructions

When deploying CSS/JS changes:

1. **For PHP files:** Update `ASSETS_VERSION` in `config/version.php`
2. **For HTML files:** Update version parameter `?v=XX` in:
   - `index.html` (line 18, 19, 526)
   - `behind-the-product.html` (line 13)

**Recommended:** Keep version numbers synchronized across all files.

## Pre-Deployment Checklist

- [ ] Update `ASSETS_VERSION` in `config/version.php`
- [ ] Update version in `index.html` (3 locations)
- [ ] Update version in `behind-the-product.html`
- [ ] Verify `DEBUG_MODE = false` in all PHP files
- [ ] Remove all `console.log` statements
- [ ] Test all pages load correctly
- [ ] Verify cache busting works (hard refresh)
- [ ] Check browser console for errors
- [ ] Verify production database credentials
- [ ] Verify ToyyibPay production credentials
- [ ] Test payment flow end-to-end

