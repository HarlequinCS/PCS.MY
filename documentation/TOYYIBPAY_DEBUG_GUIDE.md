# ToyyibPay Configuration Debug Guide

## Quick Debug

Run the debug script to see current configuration:
```
http://yourdomain.com/debug_toyyibpay_config.php
```

This will show:
- Current mode in database
- Endpoint being used
- Category code being used
- Whether values match expected configuration

## Correct Endpoints

### Staging Mode
- **Endpoint**: `https://dev.toyyibpay.com/index.php/api/createBill`
- **Category Code**: `g3ii2ms2`
- **Secret Key**: `dh1kldj2-j8db-ofm0-qauv-e037egz4oiui`

### Production Mode
- **Endpoint**: `https://toyyibpay.com/index.php/api/createBill` (NO "dev" in URL)
- **Category Code**: `lfhcsj8w`
- **Secret Key**: `58bq974i-wcu2-ypkz-4mbn-cvjuxtpil8kr`

## Common Issues

### Issue 1: Endpoint Still Shows "dev" in Production Mode

**Symptom**: When mode is set to production, endpoint still contains "dev.toyyibpay.com"

**Solution**:
1. Check database mode: Run debug script or check admin panel
2. Verify mode in database:
   ```sql
   SELECT setting_value FROM settings WHERE setting_key = 'toyyibpay_mode';
   ```
   Should return: `production` (not `staging`, not `PRODUCTION`, not `Production`)

3. Clear PHP opcache if using:
   - Restart PHP/Apache
   - Or clear opcache

### Issue 2: Category Code is Wrong

**Symptom**: Category code doesn't match expected value

**Expected Values**:
- Staging: `g3ii2ms2`
- Production: `lfhcsj8w`

**Solution**: 
- Credentials are hardcoded in `includes/toyyibpay_config.php`
- Verify the values match your ToyyibPay account

### Issue 3: Mode Changes But Endpoint Doesn't Update

**Symptom**: Toggle mode in admin panel but endpoint stays the same

**Solution**:
1. Check error logs for mode detection messages
2. Verify database was updated:
   ```sql
   SELECT * FROM settings WHERE setting_key = 'toyyibpay_mode';
   ```
3. Clear browser cache (hard refresh: Ctrl+F5)
4. Restart PHP/Apache if using opcache

## Debug Steps

1. **Run Debug Script**:
   ```
   http://yourdomain.com/debug_toyyibpay_config.php
   ```

2. **Check Error Logs**:
   - Look for: "ToyyibPay Mode Detection"
   - Look for: "ToyyibPay: Production mode active"
   - Look for: "ToyyibPay: Staging mode active"

3. **Check Database Directly**:
   ```sql
   SELECT setting_key, setting_value FROM settings 
   WHERE setting_key LIKE 'toyyibpay%';
   ```

4. **Test Checkout**:
   - Try checkout and check error logs
   - Look for: "ToyyibPay Config Used - Endpoint:"
   - Verify endpoint matches expected value

## Files to Check

1. `includes/toyyibpay_config.php` - Main configuration file
2. `checkout.php` - Uses `getToyyibPayConfig()`
3. `admin/settings.php` - Mode toggle interface
4. `admin/api/toggle_toyyibpay_mode.php` - Mode toggle API

## Version Control

All files are updated with version control:
- `config/version.php`: Updated to 1.0.1
- All changes logged in error logs

---

**Last Updated**: January 2025

