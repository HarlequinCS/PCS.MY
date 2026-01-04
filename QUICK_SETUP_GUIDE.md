# Quick Setup Guide - Buy Now Button

## 🚀 Quick Start

The Buy Now button needs to be configured in the database before it will work. Here's how to set it up:

### Option 1: Use Setup Script (Easiest)

1. **Open your browser and go to:**
   ```
   http://localhost/pcs.my/setup_button_settings.php
   ```
   (Or your production URL)

2. **Configure the settings:**
   - Set **Button Mode** to `launch` (to enable redirects)
   - Set **Button Link** to `/order-baju.php` (or your target page)
   - Click **Save Settings**

3. **Test the button:**
   - Go to your homepage
   - Click the "BUY NOW" button
   - It should redirect to the configured link

### Option 2: Use Admin Panel

1. **Login to Admin Panel:**
   ```
   http://localhost/pcs.my/admin/
   ```

2. **Go to Button Settings:**
   - Click "Button Settings" in the sidebar
   - Or go directly to: `/admin/button_settings.php`

3. **Configure:**
   - Toggle **Button Mode** to "Launch" (if not already)
   - Enter **Button Link** in the "Target Page/Link" field
   - Click **Save Link**

### Option 3: Manual Database Setup

Run this SQL in your database:

```sql
-- Ensure settings table exists
CREATE TABLE IF NOT EXISTS `settings` (
  `setting_key` VARCHAR(100) NOT NULL PRIMARY KEY,
  `setting_value` TEXT NOT NULL,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Set button mode to 'launch' (enables redirects)
INSERT INTO settings (setting_key, setting_value) 
VALUES ('button_mode', 'launch')
ON DUPLICATE KEY UPDATE setting_value = 'launch';

-- Set button link (default: /order-baju.php)
INSERT INTO settings (setting_key, setting_value) 
VALUES ('button_link', '/order-baju.php')
ON DUPLICATE KEY UPDATE setting_value = '/order-baju.php';
```

---

## 🔍 Troubleshooting

### Button Still Shows Modal Instead of Redirecting?

**Check 1: Button Mode**
- Go to `/admin/button_settings.php`
- Make sure mode is set to **"Launch"** (not "Coming Soon")
- If it says "Coming Soon", click "Switch to Launch"

**Check 2: Browser Console**
- Open browser console (F12)
- Look for errors or messages
- You should see: `Buy Now Button Mode: { buttonMode: 'launch', ... }`

**Check 3: API Response**
- Open browser console (F12)
- Go to Network tab
- Refresh the page
- Look for `/api/get_button_mode.php`
- Check the response - it should show:
  ```json
  {
    "success": true,
    "button_mode": "launch",
    "shouldRedirect": true,
    "button_link": "/order-baju.php"
  }
  ```

**Check 4: Database**
- Verify settings exist in database:
  ```sql
  SELECT * FROM settings WHERE setting_key IN ('button_mode', 'button_link');
  ```
- Should return 2 rows with values:
  - `button_mode` = `launch`
  - `button_link` = `/order-baju.php`

---

## ✅ Verification Checklist

After setup, verify:

- [ ] Settings table exists in database
- [ ] `button_mode` = `'launch'` in database
- [ ] `button_link` = `'/order-baju.php'` (or your target) in database
- [ ] API `/api/get_button_mode.php` returns correct values
- [ ] Browser console shows no errors
- [ ] Button redirects when clicked (not showing modal)

---

## 🎯 Common Issues

### Issue: Button shows modal instead of redirecting
**Solution:** Set `button_mode` to `'launch'` in database or admin panel

### Issue: Button redirects to wrong page
**Solution:** Update `button_link` in database or admin panel

### Issue: API returns error
**Solution:** Check database connection in `config/database.php`

### Issue: Multiple buttons, only one works
**Solution:** All buttons with `.js-buy-now-btn` class should work. Check browser console for errors.

---

## 📞 Need Help?

1. Check browser console for JavaScript errors
2. Check server error logs for PHP errors
3. Verify database connection works
4. Test API endpoint directly: `/api/get_button_mode.php`
5. Use setup script: `/setup_button_settings.php`

---

**Quick Fix:** Run the setup script at `/setup_button_settings.php` - it will configure everything automatically!

