# Session Flush Script Documentation

## Overview

The `admin/flush_sessions.php` script is a private, password-protected tool for managing PHP sessions. It allows administrators to clear session data, which is useful for:

- Clearing old cart sessions that may contain outdated variant names
- Resetting all user sessions
- Viewing session statistics
- Clearing cart data from all sessions

## Security Features

1. **Password Protection**: Requires a password to access (default: `PCS@dmin2025!`)
2. **IP Restriction**: Optional IP whitelist (commented out by default)
3. **Session Authentication**: Maintains authentication state during the session
4. **Secure Location**: Located in the `/admin/` directory

## Configuration

### Changing the Password

Edit `admin/flush_sessions.php` and change the password constant:

```php
define('FLUSH_SESSION_PASSWORD', 'YOUR_STRONG_PASSWORD_HERE');
```

**Important**: Use a strong, unique password. Do not use the default password in production.

### IP Restriction (Optional)

To restrict access by IP address, uncomment and modify the IP check:

```php
$allowedIPs = ['127.0.0.1', '::1', 'YOUR_SERVER_IP'];
if (!in_array($_SERVER['REMOTE_ADDR'], $allowedIPs)) {
    die('Access denied: IP not allowed');
}
```

## Usage

### Access the Script

Navigate to: `https://yourdomain.com/admin/flush_sessions.php`

### Available Actions

1. **Show Session Statistics**
   - Displays total number of sessions
   - Shows how many sessions contain cart data
   - Safe to use, no data is modified

2. **Clear Cart Data from All Sessions**
   - Removes cart data from all sessions
   - Clears: `$_SESSION['cart']`, `$_SESSION['order_type']`, `$_SESSION['promo']`
   - Users will need to add items to cart again
   - Sessions remain active, only cart data is cleared

3. **Flush All Sessions (Dangerous)**
   - Completely destroys all session data
   - All users will be logged out
   - All cart data will be lost
   - Use with extreme caution

## How It Works

### Show Statistics
- Scans the session save directory
- Counts total session files
- Checks which sessions contain cart data
- Displays summary information

### Clear Cart Data
- Scans all session files
- For each session containing cart data:
  - Starts the session
  - Removes cart-related session variables
  - Saves the session
- Reports how many sessions were modified

### Flush All Sessions
- Clears current session data
- Deletes session cookie
- Destroys current session
- Note: This only affects the current session file; other session files may still exist on the server

## Session Storage Location

The script automatically detects the session save path:
- Uses `session_save_path()` if configured
- Falls back to `sys_get_temp_dir()` if not set
- Typically: `/tmp/` on Linux or `C:\Windows\Temp\` on Windows

## Security Best Practices

1. **Change Default Password**: Never use the default password in production
2. **Use Strong Password**: Use a complex password with mixed case, numbers, and symbols
3. **Restrict Access**: Consider enabling IP restriction for additional security
4. **HTTPS Only**: Only access this script over HTTPS
5. **Limit Access**: Only share the password with trusted administrators
6. **Monitor Usage**: Check server logs for access attempts
7. **Regular Updates**: Change the password periodically

## Troubleshooting

### Script Not Working

1. **Check File Permissions**: Ensure PHP can read/write session files
2. **Check Session Path**: Verify session save path is accessible
3. **Check PHP Error Logs**: Look for permission errors

### Sessions Not Clearing

1. **Session Path**: The script may not have access to the session directory
2. **File Permissions**: PHP process may not have write permissions
3. **Session Handler**: Custom session handlers may not use file-based storage

### Password Not Working

1. **Check Password**: Ensure the password matches exactly (case-sensitive)
2. **Clear Browser Cache**: Try in an incognito/private window
3. **Check PHP Errors**: Look for syntax errors in the script

## Important Notes

- ⚠️ **Production Use**: Always change the default password before using in production
- ⚠️ **Backup**: Consider backing up session data before flushing (if needed)
- ⚠️ **User Impact**: Flushing sessions will log out users and clear their cart data
- ⚠️ **Testing**: Test in a development environment first

## Alternative: Direct Session File Deletion

For more aggressive session clearing, you can manually delete session files from the server:

```bash
# Find session directory
php -r "echo session_save_path();"

# Delete all session files (BE CAREFUL!)
rm /path/to/sessions/sess_*
```

However, the script provides a safer, more controlled way to manage sessions.

## Related Files

- `admin/flush_sessions.php` - The session flush script
- Session configuration in `php.ini` or `.htaccess`
- Session handling in other PHP files (uses `session_start()`)

## Support

For issues or questions:
1. Check PHP error logs
2. Verify file permissions
3. Test with a simple session script first
4. Consult server administrator if permission issues persist

