# Laragon Setup for Clean URLs

## Steps to Enable Clean URLs in Laragon:

1. **Enable mod_rewrite:**
   - Open Laragon
   - Click Menu → Apache → mod_rewrite → Enable
   - Restart Laragon

2. **Verify .htaccess is working:**
   - The `.htaccess` file should be in the root directory (same level as `index.html`)
   - Make sure Apache allows .htaccess overrides

3. **Test the URLs:**
   - `http://localhost/pcs.my/` → should show index.html
   - `http://localhost/pcs.my/behind-the-product` → should show behind-the-product.html
   - `http://localhost/pcs.my/behind-the-product.html` → should redirect to `/behind-the-product`

4. **If still not working:**
   - Check Apache error logs in Laragon
   - Verify `AllowOverride All` is set in Apache config
   - Try accessing with full path: `http://localhost/pcs.my/behind-the-product.html` first

## Alternative: Use direct .html links for local development

If mod_rewrite doesn't work, you can temporarily use:
- `behind-the-product.html` instead of `/behind-the-product`
- This will work in production with the .htaccess file

