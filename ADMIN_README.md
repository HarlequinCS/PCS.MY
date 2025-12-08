# Admin Panel Guide - Projek Cuti Sem

## Access Admin Panel

**URL:** `https://projectcutisem.com/admin/`

## Default Login Credentials

- **Username:** `admin`
- **Password:** `PCSadmin2024!`

⚠️ **IMPORTANT:** Change the password immediately after first login!

## How to Change Admin Password

Edit the file: `admin/config.php`

Change these lines:
```php
define('ADMIN_USERNAME', 'admin');
define('ADMIN_PASSWORD', 'PCSadmin2024!'); // Change this!
```

## Database Setup

### Current Table Structure
Your `comment` table has:
- `comment_id` (int, primary key)
- `comment` (longtext)
- `created_at` (datetime)
- `status` (varchar(20), default: 'approved')

### Recommended Update

Run the SQL in `database_update.sql` to:
1. Add `ip_address` column (optional, for tracking)
2. Change default status to 'pending' (for moderation)
3. Add indexes for better performance

**SQL File:** `database_update.sql`

## How It Works

### Comment Submission Flow

1. **User submits comment** → Status set to `pending`
2. **Comment appears in Admin Panel** → Under "Pending Review" tab
3. **Admin approves/rejects** → Status changes to `approved` or `rejected`
4. **Approved comments display** → Only on `index.html`

### Admin Panel Features

- ✅ View pending comments
- ✅ Approve comments (makes them visible on website)
- ✅ Reject comments (hides them from website)
- ✅ View approved comments
- ✅ View all comments
- ✅ Real-time statistics (pending, approved, total)
- ✅ Auto-refresh every 30 seconds
- ✅ Professional, responsive design

## File Structure

```
admin/
├── index.php              # Login page
├── dashboard.php          # Main admin panel
├── config.php             # Admin credentials & session
├── css/
│   └── admin.css          # Admin panel styles
├── js/
│   └── admin.js           # Admin panel functionality
└── api/
    ├── get_comments.php   # Get comments API
    └── approve_comment.php # Approve/reject API
```

## Security Notes

1. **Change default password** immediately
2. **Use HTTPS** in production (update `config.php` line 9: `cookie_secure => 1`)
3. **Protect admin directory** with `.htaccess` if needed
4. **Regular backups** of your database
5. **Monitor** admin access logs

## Troubleshooting

### Can't login?
- Check username/password in `admin/config.php`
- Clear browser cookies
- Check PHP session settings

### Comments not showing in admin?
- Check database connection in `config/database.php`
- Verify table structure matches
- Check browser console for errors

### Can't approve comments?
- Check file permissions on `admin/api/approve_comment.php`
- Verify database user has UPDATE permissions
- Check PHP error logs

## Support

For issues, check:
1. Browser console (F12)
2. PHP error logs
3. Database connection
4. File permissions

