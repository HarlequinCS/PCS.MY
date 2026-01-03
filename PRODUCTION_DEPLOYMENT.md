# Production Deployment Checklist

## Pre-Deployment

### 1. Database Migration
- [ ] **CRITICAL**: Run database migration script:
  ```bash
  php update_database_orders_pickup_delivery.php
  ```
  This adds the following columns to the `orders` table:
  - `order_type` (ENUM: 'pickup', 'delivery')
  - `delivery_fee` (DECIMAL 10,2)
  - `final_amount` (DECIMAL 10,2)

### 2. Configuration Verification

#### checkout.php
- [x] `DEBUG_MODE` set to `false` ✅
- [ ] Verify ToyyibPay credentials:
  - `STAGING_SECRET_KEY` - Currently using staging key
  - `STAGING_CATEGORY_CODE` - Currently using staging code
  - **ACTION**: Update to production credentials if moving to production environment
- [ ] Verify `$baseUrl` matches production domain:
  - Current: `https://projectcutisem.com`
- [ ] Verify delivery fee: `DELIVERY_FEE = 8.00` (RM8)

#### Database Configuration
- [ ] Verify `config/database.php` has correct production database credentials
- [ ] Test database connection

### 3. Code Quality Checks
- [x] All `console.log` statements removed/commented ✅
- [x] `DEBUG_MODE` set to false ✅
- [x] Error messages are user-friendly (English) ✅
- [x] Server-side `error_log` statements remain (for debugging) ✅

### 4. File Permissions
- [ ] Verify file permissions are appropriate
- [ ] Ensure `toyyibpay_callback.log` is writable (if logging enabled)

### 5. Testing Checklist

#### Functionality Testing
- [ ] Test product selection (order-baju.php)
- [ ] Test add to cart functionality
- [ ] Test checkout page loads correctly
- [ ] Test pickup/delivery dropdown selection
- [ ] Test address fields show/hide based on order type
- [ ] Test promotion code validation
- [ ] Test order total calculation (with delivery fee)
- [ ] Test form submission with pickup option
- [ ] Test form submission with delivery option
- [ ] Test payment redirect to ToyyibPay
- [ ] Test payment callback (callback.php)
- [ ] Test return page (return.php)

#### Browser Testing
- [ ] Chrome/Edge
- [ ] Firefox
- [ ] Safari
- [ ] Mobile browsers (iOS Safari, Chrome Mobile)

#### Security Testing
- [ ] Input validation works correctly
- [ ] SQL injection prevention (PDO prepared statements) ✅
- [ ] XSS prevention (htmlspecialchars) ✅
- [ ] Server-side validation for delivery fee ✅

### 6. Production Environment Setup

#### Server Requirements
- PHP 7.4+ (recommended: PHP 8.0+)
- MySQL 5.7+ or MariaDB 10.3+
- HTTPS enabled (required for payment gateway)
- cURL extension enabled

#### URLs to Verify
- [ ] Callback URL: `https://projectcutisem.com/callback.php`
- [ ] Return URL: `https://projectcutisem.com/return.php`
- [ ] Base URL: `https://projectcutisem.com`

### 7. Monitoring & Logging

#### Log Files to Monitor
- PHP error logs
- `toyyibpay_callback.log` (if callback logging enabled)
- Web server access logs
- Database error logs

#### Post-Deployment Monitoring
- [ ] Monitor error logs for first 24 hours
- [ ] Verify orders are being created in database
- [ ] Verify payment callbacks are being received
- [ ] Check payment gateway dashboard for transactions

### 8. Documentation
- [x] Ordering system documentation updated ✅
- [ ] API credentials documented (securely)
- [ ] Deployment notes documented

## Post-Deployment

### Immediate Actions
1. Test a complete order flow (product → cart → checkout → payment)
2. Verify database records are created correctly
3. Monitor error logs
4. Verify email notifications (if applicable)

### First Week Monitoring
- Monitor daily for errors
- Verify all order types (pickup/delivery) work correctly
- Check promotion code usage
- Monitor payment success rates

## Rollback Plan

If issues are detected:
1. Restore previous database backup (if schema changes were made)
2. Restore previous code version
3. Verify previous version functionality
4. Document issues for fixing

## Notes

- **ToyyibPay Credentials**: Currently using staging credentials. Update to production credentials before going live if using production environment.
- **Database Migration**: Must be run before deployment. The script is idempotent (safe to run multiple times).
- **Error Logging**: Server-side `error_log` statements are kept for debugging. These do not expose information to users.

---

**Last Updated**: January 2025
**Version**: 1.0.0

