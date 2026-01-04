<?php
/**
 * Timezone State Analysis Script
 * 
 * CRITICAL: Run this BEFORE migration to understand current state
 * 
 * This script analyzes:
 * - Current PHP timezone
 * - Current MySQL timezone
 * - How existing datetime data is stored
 * - Tables and columns with datetime data
 * 
 * DO NOT MODIFY DATA - Analysis only
 */

// Load configuration
define('DB_CONFIG_LOADED', true);
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/timezone.php';
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: text/plain; charset=utf-8');

echo "========================================\n";
echo "TIMEZONE STATE ANALYSIS\n";
echo "========================================\n\n";

// 1. PHP Timezone
echo "1. PHP TIMEZONE STATE\n";
echo "   Current default timezone: " . date_default_timezone_get() . "\n";
echo "   Current PHP time: " . date('Y-m-d H:i:s') . "\n";
echo "   PHP timezone offset: " . date('P') . "\n\n";

// 2. MySQL Timezone
try {
    $pdo = getDBConnection();
    
    // Get MySQL global timezone
    $stmt = $pdo->query("SELECT @@global.time_zone as global_tz, @@session.time_zone as session_tz");
    $tzInfo = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "2. MYSQL TIMEZONE STATE\n";
    echo "   Global timezone: " . ($tzInfo['global_tz'] ?? 'UNKNOWN') . "\n";
    echo "   Session timezone: " . ($tzInfo['session_tz'] ?? 'UNKNOWN') . "\n";
    
    // Get MySQL NOW()
    $stmt = $pdo->query("SELECT NOW() as mysql_now, UTC_TIMESTAMP() as mysql_utc");
    $mysqlTime = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "   MySQL NOW(): " . $mysqlTime['mysql_now'] . "\n";
    echo "   MySQL UTC_TIMESTAMP(): " . $mysqlTime['mysql_utc'] . "\n";
    echo "   PHP time (for comparison): " . date('Y-m-d H:i:s') . "\n";
    
    $phpTime = new DateTime('now', new DateTimeZone(APP_TIMEZONE));
    $mysqlTimeObj = new DateTime($mysqlTime['mysql_now']);
    $diff = abs($phpTime->getTimestamp() - $mysqlTimeObj->getTimestamp());
    echo "   Time difference: " . $diff . " seconds\n";
    
    if ($diff > 60) {
        echo "   ⚠️  WARNING: Significant time difference detected!\n";
    }
    echo "\n";
    
    // 3. Find all datetime columns
    echo "3. DATETIME COLUMNS IN DATABASE\n";
    $stmt = $pdo->query("
        SELECT 
            TABLE_NAME,
            COLUMN_NAME,
            COLUMN_TYPE,
            COLUMN_DEFAULT,
            IS_NULLABLE
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
        AND (COLUMN_TYPE LIKE '%DATETIME%' OR COLUMN_TYPE LIKE '%TIMESTAMP%')
        ORDER BY TABLE_NAME, COLUMN_NAME
    ");
    $datetimeColumns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($datetimeColumns)) {
        echo "   No datetime columns found.\n\n";
    } else {
        foreach ($datetimeColumns as $col) {
            echo "   Table: {$col['TABLE_NAME']}\n";
            echo "     Column: {$col['COLUMN_NAME']}\n";
            echo "     Type: {$col['COLUMN_TYPE']}\n";
            echo "     Default: " . ($col['COLUMN_DEFAULT'] ?? 'NULL') . "\n";
            echo "     Nullable: {$col['IS_NULLABLE']}\n";
            
            // Get sample data
            $sampleStmt = $pdo->query("
                SELECT {$col['COLUMN_NAME']} as sample_value
                FROM {$col['TABLE_NAME']}
                WHERE {$col['COLUMN_NAME']} IS NOT NULL
                ORDER BY {$col['COLUMN_NAME']} DESC
                LIMIT 3
            ");
            $samples = $sampleStmt->fetchAll(PDO::FETCH_ASSOC);
            if (!empty($samples)) {
                echo "     Sample values:\n";
                foreach ($samples as $sample) {
                    echo "       - " . $sample['sample_value'] . "\n";
                }
            }
            echo "\n";
        }
    }
    
    // 4. Check specific critical tables
    echo "4. CRITICAL TABLES ANALYSIS\n";
    
    $criticalTables = [
        'orders' => ['created_at', 'updated_at'],
        'promotions' => ['valid_from', 'valid_until', 'created_at', 'updated_at'],
        'comment' => ['created_at'],
        'visitors' => ['visit_date'],
        'email_notifications' => ['created_at', 'updated_at']
    ];
    
    foreach ($criticalTables as $table => $columns) {
        // Check if table exists
        $tableExists = $pdo->query("SHOW TABLES LIKE '$table'")->rowCount() > 0;
        
        if (!$tableExists) {
            echo "   Table '$table': DOES NOT EXIST\n\n";
            continue;
        }
        
        echo "   Table: $table\n";
        
        foreach ($columns as $column) {
            // Check if column exists
            $colExists = $pdo->query("SHOW COLUMNS FROM $table LIKE '$column'")->rowCount() > 0;
            
            if (!$colExists) {
                echo "     Column '$column': DOES NOT EXIST\n";
                continue;
            }
            
            // Get row count
            $countStmt = $pdo->query("SELECT COUNT(*) as cnt FROM $table WHERE $column IS NOT NULL");
            $count = $countStmt->fetch(PDO::FETCH_ASSOC)['cnt'];
            
            echo "     Column '$column': $count non-null values\n";
            
            if ($count > 0) {
                // Get min, max, and sample
                $statsStmt = $pdo->query("
                    SELECT 
                        MIN($column) as min_val,
                        MAX($column) as max_val,
                        $column as sample_val
                    FROM $table
                    WHERE $column IS NOT NULL
                    ORDER BY $column DESC
                    LIMIT 1
                ");
                $stats = $statsStmt->fetch(PDO::FETCH_ASSOC);
                
                if ($stats) {
                    echo "       Min: " . $stats['min_val'] . "\n";
                    echo "       Max: " . $stats['max_val'] . "\n";
                    echo "       Latest: " . $stats['sample_val'] . "\n";
                }
            }
        }
        echo "\n";
    }
    
    // 5. Recommendations
    echo "5. RECOMMENDATIONS\n";
    echo "   Based on analysis:\n";
    echo "   - Target timezone: " . APP_TIMEZONE . " (UTC+8)\n";
    echo "   - Database storage: " . DB_TIMEZONE . " (UTC)\n";
    echo "   - Application layer converts between timezones\n";
    echo "   - MySQL session timezone should be set to UTC\n";
    echo "   - All PHP datetime operations use " . APP_TIMEZONE . "\n\n";
    
    echo "========================================\n";
    echo "ANALYSIS COMPLETE\n";
    echo "========================================\n";
    echo "\n";
    echo "NEXT STEPS:\n";
    echo "1. Review the analysis above\n";
    echo "2. Run migrate_timezone_data.php (DRY RUN first)\n";
    echo "3. Verify conversion results\n";
    echo "4. Run migrate_timezone_data.php (LIVE MODE)\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}

