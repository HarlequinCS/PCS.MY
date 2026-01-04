# Timezone Standardization - Implementation Summary

## Executive Summary

The entire system has been standardized to use **Asia/Kuala_Lumpur (UTC+8)** as the single source of truth for all datetime operations. The implementation follows industry best practices:

- **Database**: Stores all datetime values in UTC
- **Application**: Uses Asia/Kuala_Lumpur for business logic and display
- **Frontend**: Displays Asia/Kuala_Lumpur time to users

## Architecture Decision

### Why UTC in Database?

1. **Timezone-agnostic**: UTC doesn't observe DST, preventing edge cases
2. **Industry standard**: Most production systems use UTC for storage
3. **Scalability**: Easy to support multiple timezones in future
4. **Consistency**: All datetime comparisons are in same timezone

### Why Asia/Kuala_Lumpur in Application?

1. **Business requirement**: System serves Malaysia market
2. **User experience**: Users expect to see local time
3. **Business logic**: Promotions, orders, etc. operate in Malaysia time
4. **Legal compliance**: Timestamps for payments/orders must be in local time

## Files Created

### Core Configuration
- **`config/timezone.php`** - Central timezone configuration and helper functions
  - Defines APP_TIMEZONE (Asia/Kuala_Lumpur) and DB_TIMEZONE (UTC)
  - Provides conversion functions: `dbToAppTime()`, `appToDbTime()`
  - Provides utility functions: `getCurrentDateTime()`, `getCurrentDateTimeUTC()`

### Migration Scripts
- **`DB Update/analyze_timezone_state.php`** - Analysis tool
  - Shows current PHP and MySQL timezone state
  - Lists all datetime columns
  - Provides recommendations

- **`DB Update/migrate_timezone_data.php`** - Data migration tool
  - Dry run mode (default) - shows what would change
  - Live mode (`--live` flag) - performs actual migration
  - Safe: uses transactions, detailed logging

### Documentation
- **`documentation/TIMEZONE_STANDARDIZATION_GUIDE.md`** - Complete technical guide
- **`TIMEZONE_MIGRATION_CHECKLIST.md`** - Step-by-step migration checklist

## Files Modified

### Database Layer
- **`includes/db.php`**
  - Sets MySQL session timezone to UTC
  - Ensures all `NOW()`, `CURRENT_TIMESTAMP` use UTC

### Business Logic
- **`includes/promotion.php`**
  - Timezone-aware promotion validation
  - Converts UTC (database) to Asia/Kuala_Lumpur for comparison
  - Handles both migrated and legacy data

### Admin Panel
- **`admin/promotions.php`**
  - Converts datetime inputs from app timezone to UTC for storage
  - Converts datetime displays from UTC to app timezone
  - Uses timezone helper functions for all datetime operations

- **`admin/orders.php`**
  - Displays order timestamps in Asia/Kuala_Lumpur
  - Uses `formatDateTime()` and `dbToAppTime()` helpers

### Checkout & Payments
- **`checkout.php`**
  - Stores order `created_at` in UTC using `getCurrentDateTimeUTC()`
  - All datetime operations use timezone helpers

- **`callback.php`**
  - Logs timestamps in app timezone for readability
  - Database operations use UTC

## Migration Strategy

### Phase 1: Analysis (No Risk)
1. Run `analyze_timezone_state.php`
2. Review current state
3. Identify all datetime columns

### Phase 2: Code Deployment (Low Risk)
1. Deploy timezone.php configuration
2. Deploy updated PHP files
3. System continues working (handles both old and new data)

### Phase 3: Data Migration (Medium Risk)
1. Run dry run: `migrate_timezone_data.php`
2. Review changes
3. Run live migration: `migrate_timezone_data.php --live`
4. Verify results

### Safety Features

- **Dry run mode**: Test without modifying data
- **Transactions**: Rollback on error
- **Detailed logging**: Track all changes
- **Backward compatible**: Handles both UTC and legacy data

## Key Functions Reference

```php
// Get current time in app timezone
getCurrentDateTime() 
// Returns: '2024-01-15 14:30:00' (Asia/Kuala_Lumpur)

// Get current time in UTC for database
getCurrentDateTimeUTC() 
// Returns: '2024-01-15 06:30:00' (UTC)

// Convert database UTC to app timezone
dbToAppTime($utcDateTime) 
// '2024-01-15 06:30:00' (UTC) → '2024-01-15 14:30:00' (Asia/Kuala_Lumpur)

// Convert app timezone to database UTC
appToDbTime($appDateTime) 
// '2024-01-15 14:30:00' (Asia/Kuala_Lumpur) → '2024-01-15 06:30:00' (UTC)

// Format datetime for display
formatDateTime($datetime, $format = 'Y-m-d H:i')
// Formats datetime in app timezone
```

## Testing Checklist

### Pre-Migration
- [ ] Backup database
- [ ] Run analysis script
- [ ] Review current state

### Post-Migration
- [ ] Promotion codes validate correctly
- [ ] Order timestamps display correctly
- [ ] Admin panel datetime inputs work
- [ ] No timezone errors in logs
- [ ] Database values are in UTC
- [ ] Display values are in Asia/Kuala_Lumpur

## Critical Considerations

### ⚠️ Data Migration Assumption

The migration script assumes existing datetime data is in **Asia/Kuala_Lumpur** (server local time). If your data is already in UTC or another timezone, you must modify the migration script accordingly.

### ⚠️ Promotion Validation

The promotion system now uses timezone-aware comparisons. It handles:
- Data already migrated to UTC
- Legacy data still in app timezone
- Automatic conversion during validation

### ⚠️ MySQL Permissions

The database connection requires permission to set session timezone:
```sql
SET time_zone = '+00:00';
```

If this fails, check MySQL user permissions.

## Rollback Procedure

If issues occur:

1. **Restore database backup**
2. **Revert code changes**:
   - Remove `require_once timezone.php` lines
   - Restore original `db.php`
   - Restore original `promotion.php`
3. **Reset MySQL timezone**:
   ```sql
   SET time_zone = @@global.time_zone;
   ```

## Next Steps

1. **Review** this summary and documentation
2. **Test** in development/staging environment first
3. **Backup** production database
4. **Run** analysis script
5. **Execute** migration following checklist
6. **Monitor** for issues post-migration

## Support

- **Technical Guide**: `documentation/TIMEZONE_STANDARDIZATION_GUIDE.md`
- **Migration Checklist**: `TIMEZONE_MIGRATION_CHECKLIST.md`
- **Analysis Script**: `DB Update/analyze_timezone_state.php`
- **Migration Script**: `DB Update/migrate_timezone_data.php`

## Success Metrics

✅ **Migration successful when:**
- All datetime values stored in UTC
- All displays show Asia/Kuala_Lumpur time
- Promotion codes validate correctly
- No timezone-related errors
- Admin panel works correctly
- Order timestamps accurate

---

**Implementation Date**: 2024-01-XX  
**Standard**: Database UTC, Application Asia/Kuala_Lumpur  
**Status**: Ready for migration

