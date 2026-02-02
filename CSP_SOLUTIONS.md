# Content Security Policy (CSP) Solutions for Form Submission

## Problem
Form submissions to `https://projectcutisem.com/checkout.php` are being blocked by CSP `form-action` directive.

## Solution Overview
CSP `form-action` does **NOT** support wildcard subdomains (e.g., `*.projectcutisem.com`). You must explicitly list each subdomain you want to allow.

---

## 1. Apache .htaccess Solution

### Current Implementation (Already Applied)
```apache
Header always set Content-Security-Policy "default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.jsdelivr.net https://fonts.googleapis.com https://static.cloudflareinsights.com https://cdn.cloudflare.com; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; img-src 'self' data: https:; connect-src 'self' https://projectcutisem.com https://static.cloudflareinsights.com; frame-ancestors 'self'; base-uri 'self'; form-action 'self' https://projectcutisem.com http://projectcutisem.com https://www.projectcutisem.com http://www.projectcutisem.com;"
```

### If You Need Additional Subdomains
Add them explicitly to the `form-action` directive. For example, if you have `admin.projectcutisem.com` or `api.projectcutisem.com`:

```apache
form-action 'self' https://projectcutisem.com http://projectcutisem.com https://www.projectcutisem.com http://www.projectcutisem.com https://admin.projectcutisem.com https://api.projectcutisem.com;
```

### Complete .htaccess CSP Header (Copy-Paste Ready)
```apache
<IfModule mod_headers.c>
    Header always set Content-Security-Policy "default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.jsdelivr.net https://fonts.googleapis.com https://static.cloudflareinsights.com https://cdn.cloudflare.com; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; img-src 'self' data: https:; connect-src 'self' https://projectcutisem.com https://static.cloudflareinsights.com; frame-ancestors 'self'; base-uri 'self'; form-action 'self' https://projectcutisem.com http://projectcutisem.com https://www.projectcutisem.com http://www.projectcutisem.com;"
</IfModule>
```

---

## 2. Nginx Server Config Solution

### Location: `/etc/nginx/sites-available/your-site` or server block

```nginx
server {
    listen 80;
    listen 443 ssl http2;
    server_name projectcutisem.com www.projectcutisem.com;
    
    # ... other config ...
    
    # Content Security Policy Header
    add_header Content-Security-Policy "default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.jsdelivr.net https://fonts.googleapis.com https://static.cloudflareinsights.com https://cdn.cloudflare.com; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; img-src 'self' data: https:; connect-src 'self' https://projectcutisem.com https://static.cloudflareinsights.com; frame-ancestors 'self'; base-uri 'self'; form-action 'self' https://projectcutisem.com http://projectcutisem.com https://www.projectcutisem.com http://www.projectcutisem.com;" always;
    
    # ... rest of config ...
}
```

### If You Need Additional Subdomains (Nginx)
```nginx
form-action 'self' https://projectcutisem.com http://projectcutisem.com https://www.projectcutisem.com http://www.projectcutisem.com https://admin.projectcutisem.com https://api.projectcutisem.com;
```

**Important:** Use `always` parameter to ensure the header is sent even for error responses.

---

## 3. HTML Meta Tag Solution (Less Secure - Use as Fallback)

### Location: `<head>` section of your HTML files

```html
<meta http-equiv="Content-Security-Policy" content="default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.jsdelivr.net https://fonts.googleapis.com https://static.cloudflareinsights.com https://cdn.cloudflare.com; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; img-src 'self' data: https:; connect-src 'self' https://projectcutisem.com https://static.cloudflareinsights.com; frame-ancestors 'self'; base-uri 'self'; form-action 'self' https://projectcutisem.com http://projectcutisem.com https://www.projectcutisem.com http://www.projectcutisem.com;">
```

### For checkout.php specifically:
Add this in the `<head>` section of `checkout.php`:

```html
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- CSP Meta Tag for Form Submission -->
    <meta http-equiv="Content-Security-Policy" content="form-action 'self' https://projectcutisem.com http://projectcutisem.com https://www.projectcutisem.com http://www.projectcutisem.com;">
    <!-- ... rest of head content ... -->
</head>
```

**Note:** Meta tag CSP is less secure than HTTP headers and should only be used if you cannot set headers via .htaccess or Nginx.

---

## Understanding the CSP form-action Directive

### What `'self'` means:
- Allows form submission to the **same origin** (same protocol, domain, and port)
- If you're on `https://projectcutisem.com/checkout.php`, `'self'` allows submission to `https://projectcutisem.com/*`
- Does **NOT** allow submission to subdomains automatically

### Why explicit domains are needed:
- `https://projectcutisem.com` - Main domain (HTTPS)
- `http://projectcutisem.com` - Main domain (HTTP, for development)
- `https://www.projectcutisem.com` - WWW subdomain (HTTPS)
- `http://www.projectcutisem.com` - WWW subdomain (HTTP)

### Adding more subdomains:
If you have other subdomains (e.g., `admin`, `api`, `staging`), add them explicitly:
```
form-action 'self' https://projectcutisem.com http://projectcutisem.com https://www.projectcutisem.com http://www.projectcutisem.com https://admin.projectcutisem.com https://api.projectcutisem.com https://staging.projectcutisem.com;
```

---

## Testing Your CSP

### 1. Check Current CSP
Open browser DevTools → Network tab → Select any request → Headers → Look for `Content-Security-Policy` header

### 2. Test Form Submission
1. Open `checkout.php` in browser
2. Fill out the form
3. Submit the form
4. Check browser console for CSP errors

### 3. Validate CSP Syntax
Use online CSP validators:
- https://csp-evaluator.withgoogle.com/
- https://cspvalidator.org/

---

## Troubleshooting

### Issue: Form still blocked after adding domains
**Solutions:**
1. **Clear browser cache** - CSP headers may be cached
2. **Hard refresh** - Press `Ctrl+Shift+R` (Windows) or `Cmd+Shift+R` (Mac)
3. **Check for multiple CSP headers** - Only one CSP header should be set
4. **Verify .htaccess is active** - Check Apache error logs
5. **Test in incognito mode** - Rules out cache issues

### Issue: Need to support dynamic subdomains
**Solution:** CSP cannot support wildcard subdomains. You have two options:
1. **List all subdomains explicitly** (recommended for security)
2. **Use a more permissive approach** (less secure):
   ```apache
   form-action https://projectcutisem.com http://projectcutisem.com https://*.projectcutisem.com;
   ```
   **Note:** This syntax may not work in all browsers. Explicit listing is preferred.

### Issue: CSP conflicts with other headers
**Solution:** Ensure only one CSP header is set. Check for:
- Multiple `Header set Content-Security-Policy` directives
- Meta tags with CSP
- Server-level CSP settings (e.g., in PHP)

---

## Security Best Practices

1. **Prefer HTTP headers over meta tags** - Headers are more secure and harder to bypass
2. **List specific subdomains** - Don't use overly permissive wildcards
3. **Test thoroughly** - Verify CSP doesn't break legitimate functionality
4. **Monitor CSP reports** - Set up CSP reporting to catch violations
5. **Keep CSP updated** - Add new subdomains as your site grows

---

## Additional Resources

- [MDN: Content-Security-Policy](https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/Content-Security-Policy)
- [CSP Evaluator](https://csp-evaluator.withgoogle.com/)
- [OWASP: Content Security Policy](https://owasp.org/www-project-secure-headers/)
