<?php
/**
 * Timezone Configuration
 * 
 * CRITICAL: This system uses Asia/Kuala_Lumpur (UTC+8) as the SINGLE source of truth
 * for all datetime operations across the entire application.
 * 
 * STANDARD: Database stores UTC, Application converts to/from Asia/Kuala_Lumpur
 * 
 * This is the industry best practice:
 * - Database: UTC (timezone-agnostic, prevents DST issues)
 * - Application: Asia/Kuala_Lumpur (user-facing, business logic)
 * - Frontend: Asia/Kuala_Lumpur (display only)
 */

// Define timezone constants
define('APP_TIMEZONE', 'Asia/Kuala_Lumpur');
define('DB_TIMEZONE', 'UTC');

// Set PHP default timezone (MANDATORY - must be first)
date_default_timezone_set(APP_TIMEZONE);

/**
 * Get current datetime in application timezone (Asia/Kuala_Lumpur)
 * Returns formatted string for MySQL DATETIME
 * 
 * @return string MySQL DATETIME format: 'Y-m-d H:i:s'
 */
function getCurrentDateTime() {
    $dt = new DateTime('now', new DateTimeZone(APP_TIMEZONE));
    return $dt->format('Y-m-d H:i:s');
}

/**
 * Convert datetime from database (UTC) to application timezone (Asia/Kuala_Lumpur)
 * 
 * @param string|null $utcDateTime MySQL DATETIME string in UTC (or null)
 * @return string|null MySQL DATETIME string in Asia/Kuala_Lumpur (or null)
 */
function dbToAppTime($utcDateTime) {
    if (empty($utcDateTime)) {
        return null;
    }
    
    try {
        // Parse as UTC
        $dt = new DateTime($utcDateTime, new DateTimeZone(DB_TIMEZONE));
        // Convert to app timezone
        $dt->setTimezone(new DateTimeZone(APP_TIMEZONE));
        return $dt->format('Y-m-d H:i:s');
    } catch (Exception $e) {
        error_log("Timezone conversion error (UTC to App): " . $e->getMessage());
        return $utcDateTime; // Return original on error
    }
}

/**
 * Convert datetime from application timezone (Asia/Kuala_Lumpur) to database (UTC)
 * 
 * @param string|null $appDateTime MySQL DATETIME string in Asia/Kuala_Lumpur (or null)
 * @return string|null MySQL DATETIME string in UTC (or null)
 */
function appToDbTime($appDateTime) {
    if (empty($appDateTime)) {
        return null;
    }
    
    try {
        // Parse as app timezone
        $dt = new DateTime($appDateTime, new DateTimeZone(APP_TIMEZONE));
        // Convert to UTC
        $dt->setTimezone(new DateTimeZone(DB_TIMEZONE));
        return $dt->format('Y-m-d H:i:s');
    } catch (Exception $e) {
        error_log("Timezone conversion error (App to UTC): " . $e->getMessage());
        return $appDateTime; // Return original on error
    }
}

/**
 * Get current datetime in UTC for database storage
 * 
 * @return string MySQL DATETIME format in UTC: 'Y-m-d H:i:s'
 */
function getCurrentDateTimeUTC() {
    $dt = new DateTime('now', new DateTimeZone(APP_TIMEZONE));
    $dt->setTimezone(new DateTimeZone(DB_TIMEZONE));
    return $dt->format('Y-m-d H:i:s');
}

/**
 * Format datetime for display (assumes input is already in app timezone)
 * 
 * @param string|null $datetime MySQL DATETIME string
 * @param string $format PHP date format (default: 'Y-m-d H:i')
 * @return string Formatted datetime string
 */
function formatDateTime($datetime, $format = 'Y-m-d H:i') {
    if (empty($datetime)) {
        return '-';
    }
    
    try {
        $dt = new DateTime($datetime, new DateTimeZone(APP_TIMEZONE));
        return $dt->format($format);
    } catch (Exception $e) {
        error_log("DateTime format error: " . $e->getMessage());
        return $datetime; // Return original on error
    }
}

/**
 * Create DateTime object in application timezone
 * 
 * @param string|null $datetime MySQL DATETIME string (null = now)
 * @return DateTime DateTime object in Asia/Kuala_Lumpur
 */
function createAppDateTime($datetime = null) {
    if ($datetime === null) {
        return new DateTime('now', new DateTimeZone(APP_TIMEZONE));
    }
    
    return new DateTime($datetime, new DateTimeZone(APP_TIMEZONE));
}

/**
 * Create DateTime object in UTC for database operations
 * 
 * @param string|null $datetime MySQL DATETIME string in UTC (null = now)
 * @return DateTime DateTime object in UTC
 */
function createDbDateTime($datetime = null) {
    if ($datetime === null) {
        $dt = new DateTime('now', new DateTimeZone(APP_TIMEZONE));
        $dt->setTimezone(new DateTimeZone(DB_TIMEZONE));
        return $dt;
    }
    
    return new DateTime($datetime, new DateTimeZone(DB_TIMEZONE));
}

