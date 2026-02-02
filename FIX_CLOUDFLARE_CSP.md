# How to Fix CSP in Cloudflare

## Quick Fix: Cloudflare Transform Rules

### Step 1: Login to Cloudflare Dashboard
1. Go to https://dash.cloudflare.com
2. Select your domain: `projectcutisem.com`

### Step 2: Create Transform Rule
1. Navigate to: **Rules** → **Transform Rules** → **Modify Response Header**
2. Click **Create rule**

### Step 3: Configure Rule

**Rule Name:** `Fix CSP for checkout.php`

**When incoming requests match:**
- **Field:** URI Path
- **Operator:** equals
- **Value:** `/checkout.php`

**Then modify response header:**
- **Action:** Set static
- **Header name:** `Content-Security-Policy`
- **Value:** 
```
default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.jsdelivr.net https://fonts.googleapis.com https://static.cloudflareinsights.com https://cdn.cloudflare.com; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; img-src 'self' data: https:; connect-src 'self' https://projectcutisem.com https://static.cloudflareinsights.com; frame-ancestors 'self'; base-uri 'self'; form-action 'self' https://projectcutisem.com/checkout.php http://projectcutisem.com/checkout.php;
```

### Step 4: Deploy
1. Click **Deploy**
2. Wait 30 seconds for propagation
3. Test checkout form

---

## Alternative: Bypass Cloudflare for checkout.php

### Step 1: Create Page Rule
1. Navigate to: **Rules** → **Page Rules**
2. Click **Create Page Rule**

### Step 2: Configure Rule

**URL Pattern:** `*projectcutisem.com/checkout.php*`

**Settings:**
- ✅ **Cache Level:** Bypass
- ✅ **Security Level:** Medium
- ✅ **Disable Apps:** On
- ✅ **Disable Performance:** On

### Step 3: Save
1. Click **Save and Deploy**
2. This bypasses Cloudflare for checkout.php
3. Server headers will work directly

---

## Check Existing Rules

### Check for Conflicting Rules
1. Go to **Rules** → **Page Rules**
2. Look for rules matching `checkout.php`
3. Check if any modify CSP headers
4. Disable or modify conflicting rules

### Check Transform Rules
1. Go to **Rules** → **Transform Rules**
2. Check for any rules modifying `Content-Security-Policy` header
3. Review and update as needed

---

## Verify Fix

1. Clear browser cache
2. Load `checkout.php` in browser
3. Open DevTools → Network → checkout.php → Headers
4. Verify CSP header shows: `form-action 'self' https://projectcutisem.com/checkout.php`
5. Test form submission

---

## If Cloudflare is NOT the Issue

If you're not using Cloudflare or rules don't help:

1. **Check Hosting Control Panel:**
   - cPanel → Security → Security Headers
   - Plesk → Security → Security Headers
   - Look for CSP configuration

2. **Check Apache Config:**
   - Server-level `.htaccess` or `httpd.conf`
   - May override directory-level `.htaccess`

3. **Contact Hosting Provider:**
   - Ask about server-level CSP headers
   - Request to disable or modify CSP for your domain
