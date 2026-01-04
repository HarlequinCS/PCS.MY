# Timezone Standardization Guide

## Overview

This system has been fully standardized to use **Asia/Kuala_Lumpur (UTC+8)** as the single source of truth for all datetime operations.

## Standard Architecture

### Database Layer (UTC)
- **Storage**: All datetime values stored in UTC
- **MySQL Session**: Set to UTC (`SET time_zone = '+00:00'`)
- **Rationale**: UTC is timezone-agnostic, prevents DST issues, industry best practice

### Application Layer (Asia/Kuala_Lumpur)
- **Business Logic**: All datetime operations use Asia/Kuala_Lumpur
- **User Input**: Interpreted as Asia/Kuala_Lumpur
- **Display**: Converted from UTC to Asia/Kuala_Lumpur for display

### Frontend Layer (Asia/Kuala_Lumpur)
- **Display**: Always shows Asia/Kuala_Lumpur time
- **Input**: datetime-local inputs are in Asia/Kuala_Lumpur

## Implementation Details

### Configuration

**File**: `config/timezone.php`

This file provides:
- Timezone constants (`APP_TIMEZONE`, `DB_TIMEZONE`)
- Helper functions for timezone conversion
- Automatic PHP timezone setting

### Key Functions

```php
// Get current time in app timezone
getCurrentDateTime() // Returns: '2024-01-15 14:30:00' (Asia/Kuala_Lumpur)

// Get current time in UTC for database
getCurrentDateTimeUTC() // Returns: '2024-01-15 06:30:00' (UTC)

// Convert from database (UTC) to app timezone
dbToAppTime($utcDateTime) // UTC → Asia/Kuala_Lumpur

// Convert from app timezone to database (UTC)
appToDbTime($appDateTime) // Asia/Kuala_Lumpur → UTC

// Format datetime for display
formatDateTime($datetime, $format = 'Y-m-d H:i')
```

### Database Connection

**File**: `includes/db.php`

The database connection automatically sets MySQL session timezone to UTC:

```php
$pdo->exec("SET time_zone = '+00:00'");
```

This ensures:
- `NOW()` returns UTC
- `CURRENT_TIMESTAMP` uses UTC
- All datetime comparisons are in UTC

## Migration Process

### Web Interface (Recommended)

The easiest way to run migration is through the admin panel:

1. **Access**: Log in to admin panel → Navigate to "Timezone Migration"
2. **Analysis**: Click "Run Analysis" to see current state
3. **Dry Run**: Click "Dry Run (Test)" to see what would change
4. **Live Migration**: Click "Proceed to Live Migration" → Confirm → Execute

The web interface provides:
- ✅ Admin authentication (secure)
- ✅ User-friendly output display
- ✅ Confirmation step before live migration
- ✅ Real-time progress display

### Command Line Alternative

If you prefer command line:

**Step 1: Analysis**
```bash
php DB\ Update/analyze_timezone_state.php
```

**Step 2: Dry Run**
```bash
php DB\ Update/migrate_timezone_data.php
```

**Step 3: Live Migration**
```bash
php DB\ Update/migrate_timezone_data.php --live
```

**IMPORTANT**: 
- Backup database before running
- Run during low-traffic period
- Monitor for errors

### Step 4: Verification

After migration:
1. Check promotion codes are validating correctly
2. Verify order timestamps display correctly
3. Test datetime inputs in admin panel
4. Check logs for timezone-related errors

## Critical Components Updated

### Promotion System

**File**: `includes/promotion.php`

- Timezone-aware validation
- Converts UTC (database) to Asia/Kuala_Lumpur for comparison
- Handles both migrated (UTC) and legacy (app timezone) data

### Admin Panel

**Files**: 
- `admin/promotions.php`
- `admin/orders.php`

- All datetime inputs converted from app timezone to UTC for storage
- All datetime displays converted from UTC to app timezone
- Form inputs use datetime-local (browser timezone, treated as Asia/Kuala_Lumpur)

### Checkout System

**File**: `checkout.php`

- Order creation timestamps stored in UTC
- Uses `getCurrentDateTimeUTC()` for database inserts

### Payment Callbacks

**File**: `callback.php`

- Log timestamps in app timezone for readability
- Database operations use UTC

## Data Migration Strategy

### Assumption

Existing datetime data is assumed to be in **Asia/Kuala_Lumpur** (server local time).

### Conversion Process

1. Read existing datetime value
2. Parse as Asia/Kuala_Lumpur
3. Convert to UTC
4. Store back in database

### Tables Migrated

- `orders` (created_at, updated_at)
- `promotions` (valid_from, valid_until, created_at, updated_at)
- `comment` (created_at)
- `visitors` (visit_date)
- `email_notifications` (created_at, updated_at)

## Best Practices

### When Writing Datetime to Database

```php
// ✅ CORRECT: Convert to UTC before storing
$stmt->execute([
    ':created_at' => getCurrentDateTimeUTC()
]);

// ❌ WRONG: Using NOW() or CURRENT_TIMESTAMP directly
// (These now return UTC, but be explicit)
```

### When Reading Datetime from Database

```php
// ✅ CORRECT: Convert from UTC to app timezone for display
$displayTime = formatDateTime(dbToAppTime($row['created_at']));

// ❌ WRONG: Displaying UTC directly
echo $row['created_at']; // Shows UTC, not user-friendly
```

### When Comparing Datetimes

```php
// ✅ CORRECT: Compare in same timezone (UTC)
$currentUTC = getCurrentDateTimeUTC();
if ($currentUTC >= $validFromUTC && $currentUTC <= $validUntilUTC) {
    // Valid
}

// ❌ WRONG: Mixing timezones
if (date('Y-m-d H:i:s') >= $dbValue) { // Wrong timezone!
```

## Troubleshooting

### Promotion Codes Not Validating

**Symptom**: Valid promotion codes show as expired or not yet valid

**Check**:
1. Verify migration completed successfully
2. Check promotion dates in database (should be UTC)
3. Review error logs for timezone conversion issues
4. Test with `analyze_timezone_state.php`

### Timestamps Displaying Incorrectly

**Symptom**: Order dates show wrong time

**Check**:
1. Verify `dbToAppTime()` is being used for display
2. Check database values are in UTC
3. Verify timezone.php is loaded

### Database Connection Issues

**Symptom**: MySQL timezone setting fails

**Check**:
1. MySQL user has permission to set session variables
2. MySQL version supports timezone setting
3. Check error logs for specific error

## Rollback Procedure

If migration causes issues:

1. **Restore from backup** (if available)
2. **Revert code changes**:
   - Remove timezone.php includes
   - Restore original db.php
   - Restore original promotion.php
3. **Reset MySQL timezone**:
   ```sql
   SET time_zone = @@global.time_zone;
   ```

## Testing Checklist

- [ ] Analysis script runs without errors
- [ ] Dry run migration shows expected changes
- [ ] Live migration completes successfully
- [ ] Promotion codes validate correctly
- [ ] Order timestamps display correctly
- [ ] Admin panel datetime inputs work
- [ ] New orders created with correct timestamps
- [ ] Payment callbacks log correctly
- [ ] No timezone-related errors in logs

## Support

For issues or questions:
1. Check error logs
2. Run analysis script
3. Review this documentation
4. Check code comments in timezone.php

## Version History

- **2024-01-XX**: Initial timezone standardization implementation
- All datetime operations now use Asia/Kuala_Lumpur
- Database stores UTC, application converts for display

