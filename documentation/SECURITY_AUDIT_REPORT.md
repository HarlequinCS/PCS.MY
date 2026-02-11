# Security Assessment / Audit Report
## Order-Baju.php & Related Components

**Date:** December 2024  
**Application:** Projek Cuti Sem (PCS) E-Commerce Order System  
**File Analyzed:** `order-baju.php` (1,479 lines)  
**Related Files:** `checkout.php`, `includes/db.php`, `config/database.php`

---

## Executive Summary

This security audit evaluates the `order-baju.php` file and related components for common web application vulnerabilities. The application demonstrates **strong security practices** in several areas, particularly SQL injection prevention and XSS mitigation. However, **critical security gaps** exist in CSRF protection, session security configuration, and credential management.

**Overall Security Rating: 7.5/10**

**Key Findings:**
- ✅ **Strong:** SQL Injection prevention, XSS protection, Input validation
- ⚠️ **Moderate:** Error handling, Business logic validation
- ❌ **Critical:** CSRF protection missing, Session security not configured, Hardcoded credentials

---

## 1. SQL Injection Prevention

### Status: ✅ **SECURE**

**Assessment:**
The application consistently uses PDO prepared statements with parameter binding throughout the codebase. All database queries properly separate SQL structure from user input.

**Evidence:**
- Lines 173, 527-535, 560-567, 788-799: All queries use prepared statements
- Named placeholders (`:parameter`) used for clarity
- Parameter binding prevents SQL injection even with malicious input

**Example (Line 788-799):**
```php
$inventoryStmt = $pdo->prepare("
    SELECT pi.quantity 
    FROM product_inventory pi
    JOIN product_variants pv ON pi.variant_id = pv.id
    WHERE pv.variant_key = :variant_key AND pi.size = :size
");
$inventoryStmt->execute([
    ':variant_key' => $variant,  // Safely bound
    ':size' => $size             // Safely bound
]);
```

**Recommendation:** ✅ No changes needed - maintain current practices

---

## 2. Cross-Site Scripting (XSS) Prevention

### Status: ✅ **SECURE**

**Assessment:**
All user-generated content and dynamic output is properly escaped using `htmlspecialchars()` with `ENT_QUOTES` flag and UTF-8 encoding.

**Evidence:**
- Lines 1006, 1092, 1112-1113, 1131-1148, 1174-1178, 1408-1427: Consistent XSS escaping
- Both single and double quotes are escaped
- Applied to product names, variant names, sizes, cart items, and all user-facing content

**Example (Line 1112-1113):**
```php
<div class="order-message order-message--<?php echo htmlspecialchars($messageType); ?>">
    <?php echo htmlspecialchars($message); ?>
</div>
```

**Recommendation:** ✅ No changes needed - maintain current practices

---

## 3. Cross-Site Request Forgery (CSRF) Protection

### Status: ❌ **CRITICAL VULNERABILITY**

**Assessment:**
The `order-baju.php` file **lacks CSRF protection** for state-changing operations (add to cart, remove from cart). While `checkout.php` includes CSRF protection (`includes/csrf.php`), the order page does not.

**Vulnerability:**
- POST requests to add/remove cart items (lines 741, 931) have no CSRF token validation
- Attackers could forge requests to manipulate user carts
- No origin/referer validation implemented

**Impact:**
- **High:** Attackers could add unwanted items to user carts
- **Medium:** Potential for cart manipulation attacks
- **Low:** Session-based cart limits some attack vectors

**Evidence:**
```php
// Line 741 - No CSRF token check
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_to_cart') {
    // Processes request without CSRF validation
}

// Line 931 - No CSRF token check
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'remove_from_cart') {
    // Processes request without CSRF validation
}
```

**Recommendation:**
1. **IMMEDIATE:** Implement CSRF token generation and validation
2. Add CSRF token to all forms and AJAX requests
3. Validate tokens on all state-changing operations
4. Use the existing `includes/csrf.php` pattern from checkout.php

**Implementation Example:**
```php
// At top of file
require_once __DIR__ . '/includes/csrf.php';

// In form
<input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">

// In POST handler
if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    die('Invalid CSRF token');
}
```

---

## 4. Input Validation & Sanitization

### Status: ✅ **SECURE**

**Assessment:**
Comprehensive input validation using whitelist approach, type casting, and range validation.

**Evidence:**
- **Variant Validation (Line 756):** Whitelist against `$variants` array
- **Size Validation (Line 763):** Whitelist against `$sizes` array
- **Quantity Validation (Line 770):** Type casting with `intval()` and range check (>= 1)
- **Null Coalescing (Lines 746-748):** Safe parameter retrieval

**Example (Lines 756-772):**
```php
if (empty($variant) || !isset($variants[$variant])) {
    $message = 'Please select a color';
    $messageType = 'error';
} elseif (empty($size) || !in_array($size, $sizes)) {
    $message = 'Please select a size';
    $messageType = 'error';
} elseif ($quantity < 1) {
    $message = 'Quantity must be at least 1';
    $messageType = 'error';
}
```

**Recommendation:** ✅ No changes needed - maintain current practices

---

## 5. Session Security

### Status: ⚠️ **NEEDS IMPROVEMENT**

**Assessment:**
Basic `session_start()` is used without explicit security configuration. Session security settings should be configured to prevent session hijacking and fixation attacks.

**Current State:**
- Line 142: Only `session_start()` called
- No session cookie security flags set
- No session regeneration on sensitive operations

**Missing Security Settings:**
- `session.cookie_httponly` - Prevent JavaScript access
- `session.cookie_secure` - HTTPS-only cookies
- `session.cookie_samesite` - CSRF protection
- `session.use_strict_mode` - Prevent session fixation

**Evidence from admin/config.php (Lines 11-14):**
```php
// Admin area has proper session security, but order-baju.php does not
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', 1);
ini_set('session.cookie_samesite', 'Strict');
```

**Recommendation:**
1. **HIGH PRIORITY:** Add session security configuration before `session_start()`
2. Implement session regeneration on cart operations
3. Set appropriate session timeout

**Implementation:**
```php
// Before session_start() at line 142
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', 1); // Set to 0 for localhost, 1 for production
ini_set('session.cookie_samesite', 'Strict');
ini_set('session.use_strict_mode', 1);
ini_set('session.gc_maxlifetime', 3600); // 1 hour
session_start();
```

---

## 6. Error Handling & Information Disclosure

### Status: ✅ **GOOD**

**Assessment:**
Error handling prevents information disclosure while maintaining functionality through graceful degradation.

**Evidence:**
- Lines 455-459, 603-629, 834-857: Try-catch blocks prevent error exposure
- Errors logged server-side via `error_log()`
- Generic error messages don't reveal system internals
- Fail-open strategy for inventory checks (allows order if check fails)

**Example (Lines 834-857):**
```php
} catch (PDOException $e) {
    error_log('Inventory check error: ' . $e->getMessage());
    // Fail open - allow order if inventory check fails
    // ... continues with order processing
}
```

**Recommendation:**
- ✅ Current approach is acceptable for e-commerce (fail-open prevents lost sales)
- Consider adding monitoring/alerting for repeated inventory check failures

---

## 7. Business Logic Security

### Status: ✅ **SECURE**

**Assessment:**
Server-side price calculation and inventory validation prevent client-side manipulation.

**Evidence:**
- **Price Calculation (Lines 814-816, 838-840, 861-863):** Server-side only
- **Inventory Verification (Lines 778-833):** Validates stock before cart addition
- **Quantity Limits (Line 804):** Enforces available quantity
- **Size Addon Pricing (Lines 635-643):** Calculated server-side

**Example (Lines 814-816):**
```php
$sizeAddon = getSizeAddonPrice($size);
$itemPrice = PRICE_PER_SHIRT + $sizeAddon;
$itemTotal = $itemPrice * $quantity;
```

**Recommendation:** ✅ No changes needed - maintain current practices

---

## 8. Database Credential Management

### Status: ❌ **CRITICAL VULNERABILITY**

**Assessment:**
Database credentials are hardcoded as fallback values in `config/database.php`, creating a security risk if the file is exposed.

**Evidence (config/database.php, Lines 24-27):**
```php
return [
    'host' => (defined('DB_HOST') && !empty(DB_HOST)) ? DB_HOST : 'localhost',
    'database' => (defined('DB_NAME') && !empty(DB_NAME)) ? DB_NAME : 'u284911514_pcsmy',
    'username' => (defined('DB_USER') && !empty(DB_USER)) ? DB_USER : 'u284911514_pcsadmin',
    'password' => (defined('DB_PASS') && !empty(DB_PASS)) ? DB_PASS : 'PCSadmin25', // ⚠️ HARDCODED
];
```

**Vulnerability:**
- Database credentials visible in source code
- If repository is public or file is exposed, credentials are compromised
- No encryption of credentials

**Impact:**
- **Critical:** Full database access if credentials are exposed
- **High:** Potential data breach
- **High:** Unauthorized access to customer data and orders

**Recommendation:**
1. **IMMEDIATE:** Remove hardcoded credentials
2. **IMMEDIATE:** Ensure `.env` file exists and is properly configured
3. **IMMEDIATE:** Add `.env` to `.gitignore` (verify it's excluded)
4. **HIGH:** Use environment variables exclusively in production
5. **MEDIUM:** Consider using a secrets management service

**Implementation:**
```php
// Remove hardcoded fallbacks, require environment variables
if (!defined('DB_HOST') || empty(DB_HOST)) {
    throw new Exception('DB_HOST environment variable not set');
}
// Repeat for all database credentials
```

---

## 9. Rate Limiting

### Status: ⚠️ **MISSING**

**Assessment:**
No rate limiting implemented for cart operations or form submissions. This allows potential abuse through automated requests.

**Evidence:**
- No rate limiting on `add_to_cart` endpoint (Line 741)
- No rate limiting on `remove_from_cart` endpoint (Line 931)
- No protection against cart manipulation spam

**Note:** Other parts of the application (e.g., `api/comments.php` line 19) implement rate limiting using `includes/rate_limit.php`.

**Impact:**
- **Medium:** Potential for DoS through rapid cart operations
- **Low:** Cart manipulation spam (mitigated by session-based storage)

**Recommendation:**
1. **MEDIUM PRIORITY:** Implement rate limiting for cart operations
2. Use existing `includes/rate_limit.php` pattern
3. Set reasonable limits (e.g., 20 cart operations per minute per IP)

**Implementation:**
```php
require_once __DIR__ . '/includes/rate_limit.php';
checkRateLimit('cart_operations', 20, 60); // 20 requests per minute
```

---

## 10. HTTPS Enforcement

### Status: ⚠️ **NOT ENFORCED IN CODE**

**Assessment:**
No explicit HTTPS enforcement in the application code. Relies on server configuration.

**Evidence:**
- No redirect from HTTP to HTTPS
- No HSTS headers set
- Session cookie secure flag not set (see Session Security section)

**Recommendation:**
1. **HIGH PRIORITY:** Add HTTPS redirect at application level
2. Set HSTS headers
3. Configure session cookie secure flag (see Session Security)

**Implementation:**
```php
// At top of file, before any output
if (!isset($_SERVER['HTTPS']) || $_SERVER['HTTPS'] !== 'on') {
    $redirectUrl = 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
    header("Location: $redirectUrl", true, 301);
    exit;
}

// Set security headers
header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
```

---

## 11. AJAX Endpoint Security

### Status: ✅ **GOOD**

**Assessment:**
AJAX endpoints have basic security measures but lack CSRF protection (covered in section 3).

**Evidence:**
- Request method validation (Line 931)
- Action parameter verification (Line 931)
- Input validation (Line 935)
- Proper JSON response headers (Line 975)

**Example (Lines 931-983):**
```php
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'remove_from_cart') {
    $itemId = $_POST['item_id'] ?? '';
    if (!empty($itemId) && isset($_SESSION['cart'])) {
        // Safe array filtering with strict comparison
        $_SESSION['cart'] = array_filter($_SESSION['cart'], function($item) use ($itemId) {
            return $item['id'] !== $itemId;  // Strict comparison
        });
        // ... JSON response
    }
}
```

**Recommendation:**
- Add CSRF token validation (see section 3)
- Consider adding rate limiting (see section 9)

---

## 12. Type Safety & Data Integrity

### Status: ✅ **SECURE**

**Assessment:**
Proper type casting and validation prevents type confusion attacks.

**Evidence:**
- `intval()` for quantities (Line 748)
- `floatval()` for prices (Lines 127, 132)
- Strict comparison operators (`!==` at Line 955)
- Type checking before operations (Lines 123-124)

**Recommendation:** ✅ No changes needed

---

## 13. File Access & Path Traversal

### Status: ✅ **NOT APPLICABLE**

**Assessment:**
No file upload or file access functionality in this file. Image paths are from database or predefined constants.

**Evidence:**
- Image paths come from database (Line 549) or hardcoded arrays (Lines 588, 612)
- No user-controlled file paths

**Recommendation:** ✅ No changes needed

---

## 14. Authentication & Authorization

### Status: ✅ **NOT APPLICABLE**

**Assessment:**
This is a public-facing order page. No authentication required. Authorization handled at checkout level.

**Recommendation:** ✅ No changes needed

---

## Summary of Recommendations

### Critical (Fix Immediately)
1. ❌ **CSRF Protection** - Add CSRF tokens to all state-changing operations
2. ❌ **Database Credentials** - Remove hardcoded credentials, use environment variables only
3. ⚠️ **Session Security** - Configure session cookie security flags

### High Priority
4. ⚠️ **HTTPS Enforcement** - Add HTTPS redirect and security headers
5. ⚠️ **Rate Limiting** - Implement rate limiting for cart operations

### Medium Priority
6. ✅ **Error Monitoring** - Add alerting for repeated failures
7. ✅ **Documentation** - Update security documentation with implemented fixes

---

## Security Checklist

| Security Measure | Status | Notes |
|-----------------|--------|-------|
| SQL Injection Prevention | ✅ Secure | PDO prepared statements used consistently |
| XSS Prevention | ✅ Secure | htmlspecialchars() with ENT_QUOTES |
| CSRF Protection | ❌ Missing | No CSRF tokens on state-changing operations |
| Input Validation | ✅ Secure | Whitelist validation, type casting |
| Session Security | ⚠️ Needs Work | No security flags configured |
| Error Handling | ✅ Good | Errors logged, not exposed |
| Business Logic | ✅ Secure | Server-side price calculation |
| Credential Management | ❌ Critical | Hardcoded credentials present |
| Rate Limiting | ⚠️ Missing | No rate limiting on cart operations |
| HTTPS Enforcement | ⚠️ Not Enforced | Relies on server config |
| Type Safety | ✅ Secure | Proper type casting |
| File Security | ✅ N/A | No file operations |

---

## Testing Recommendations

1. **CSRF Testing:**
   - Create external form that submits to add_to_cart endpoint
   - Verify request is rejected without valid CSRF token

2. **SQL Injection Testing:**
   - Attempt SQL injection in variant/size parameters
   - Verify prepared statements prevent injection

3. **XSS Testing:**
   - Submit `<script>alert('XSS')</script>` in all input fields
   - Verify output is properly escaped

4. **Session Security Testing:**
   - Verify session cookies have HttpOnly, Secure, SameSite flags
   - Test session fixation attacks

5. **Rate Limiting Testing:**
   - Send rapid requests to cart endpoints
   - Verify rate limiting triggers

---

## Compliance Notes

- **OWASP Top 10 2021:** Addresses A03:2021 (Injection), A07:2021 (XSS), but missing A01:2021 (Broken Access Control - CSRF)
- **PCI DSS:** If handling payment data, ensure HTTPS and secure session management
- **GDPR:** Ensure customer data protection (covered by database security)

---

## Conclusion

The `order-baju.php` file demonstrates **strong security practices** in SQL injection prevention, XSS mitigation, and input validation. However, **critical vulnerabilities** exist in CSRF protection and credential management that require immediate attention.

**Priority Actions:**
1. Implement CSRF protection (Critical)
2. Remove hardcoded database credentials (Critical)
3. Configure session security settings (High)
4. Add HTTPS enforcement (High)
5. Implement rate limiting (Medium)

With these fixes implemented, the application security rating would improve from **7.5/10** to **9/10**.

---

**Report Generated:** December 2024  
**Next Review:** After implementing critical fixes
