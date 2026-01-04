# Timezone Standardization - Migration Checklist

## Pre-Migration

- [ ] **Backup Database** - Create full database backup before any changes
- [ ] **Review Current State** - Run `DB Update/analyze_timezone_state.php`
- [ ] **Document Current Issues** - Note any existing timezone-related problems
- [ ] **Schedule Maintenance Window** - Plan for low-traffic period

## Migration Steps

### Step 1: Analysis
```bash
php DB\ Update/analyze_timezone_state.php
```
- [ ] Review PHP timezone state
- [ ] Review MySQL timezone state
- [ ] Check all datetime columns identified
- [ ] Verify sample data values

### Step 2: Dry Run
```bash
php DB\ Update/migrate_timezone_data.php
```
- [ ] Review what would change
- [ ] Verify conversion logic is correct
- [ ] Check row counts match expectations
- [ ] Note any warnings or errors

### Step 3: Code Deployment
- [ ] Deploy timezone.php configuration
- [ ] Deploy updated db.php (with UTC timezone setting)
- [ ] Deploy updated promotion.php
- [ ] Deploy updated admin/promotions.php
- [ ] Deploy updated checkout.php
- [ ] Deploy updated callback.php
- [ ] Deploy updated admin/orders.php

### Step 4: Live Migration
```bash
php DB\ Update/migrate_timezone_data.php --live
```
- [ ] Monitor for errors
- [ ] Verify migration completes successfully
- [ ] Check transaction committed
- [ ] Review summary output

## Post-Migration Verification

### Functional Tests
- [ ] **Promotion Codes**
  - [ ] Create new promotion with valid_from/valid_until
  - [ ] Verify promotion validates correctly when active
  - [ ] Verify promotion rejects when expired
  - [ ] Verify promotion rejects when not yet valid
  - [ ] Check admin panel displays correct times

- [ ] **Orders**
  - [ ] Create new order
  - [ ] Verify created_at timestamp is correct
  - [ ] Check admin panel displays order time correctly
  - [ ] Verify order timestamps in database are UTC

- [ ] **Payment Callbacks**
  - [ ] Test payment callback
  - [ ] Verify callback logs show correct time
  - [ ] Check order status updates correctly

- [ ] **Admin Panel**
  - [ ] Create promotion with datetime inputs
  - [ ] Edit existing promotion
  - [ ] Verify datetime displays correctly
  - [ ] Check order list shows correct times

### Data Verification
- [ ] **Database Values**
  - [ ] Spot check: orders.created_at values are UTC
  - [ ] Spot check: promotions.valid_from/valid_until are UTC
  - [ ] Verify UTC times are 8 hours behind Malaysia time

- [ ] **Display Values**
  - [ ] Admin panel shows Malaysia time
  - [ ] Order dates display correctly
  - [ ] Promotion dates display correctly

### Error Monitoring
- [ ] **Check Error Logs**
  - [ ] No timezone conversion errors
  - [ ] No datetime parsing errors
  - [ ] No database timezone errors

- [ ] **Application Logs**
  - [ ] Promotion validation logs show correct times
  - [ ] Order creation logs show correct times
  - [ ] No timezone-related warnings

## Rollback Plan

If issues occur:

1. **Immediate Rollback**
   - [ ] Restore database from backup
   - [ ] Revert code changes
   - [ ] Reset MySQL timezone: `SET time_zone = @@global.time_zone;`

2. **Partial Rollback**
   - [ ] Keep timezone.php (non-breaking)
   - [ ] Revert promotion validation changes
   - [ ] Revert admin panel changes
   - [ ] Keep database in UTC (already migrated)

## Long-Term Monitoring

- [ ] **Weekly Checks** (first month)
  - [ ] Verify no timezone-related errors
  - [ ] Check promotion validations working
  - [ ] Verify order timestamps correct

- [ ] **Documentation**
  - [ ] Update team on timezone standard
  - [ ] Document any custom datetime handling
  - [ ] Note any edge cases discovered

## Success Criteria

✅ Migration is successful when:
- All datetime values stored in UTC
- All displays show Asia/Kuala_Lumpur time
- Promotion codes validate correctly
- No timezone-related errors in logs
- Admin panel datetime inputs work correctly
- Order timestamps are accurate

## Support Contacts

- **Technical Issues**: Check `documentation/TIMEZONE_STANDARDIZATION_GUIDE.md`
- **Migration Scripts**: `DB Update/analyze_timezone_state.php`, `DB Update/migrate_timezone_data.php`
- **Configuration**: `config/timezone.php`

## Notes

- Migration assumes existing data is in Asia/Kuala_Lumpur
- All new data will be stored in UTC
- Application layer handles all timezone conversions
- Frontend always displays Asia/Kuala_Lumpur time

