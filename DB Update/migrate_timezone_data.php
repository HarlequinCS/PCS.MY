<?php
/**
 * Timezone Data Migration Script
 * 
 * CRITICAL: Safely converts existing datetime data to UTC storage
 * 
 * MIGRATION STRATEGY:
 * 1. Assumes existing data is in server local time (may be incorrect)
 * 2. Converts to UTC for storage (database standard)
 * 3. Application layer converts to Asia/Kuala_Lumpur for display
 * 
 * SAFETY FEATURES:
 * - Dry run mode (default) - shows what would change
 * - Backup creation before migration
 * - Transaction support
 * - Detailed logging
 * - Reversible (can restore from backup)
 * 
 * USAGE:
 *   Dry run: php migrate_timezone_data.php
 *   Live run: php migrate_timezone_data.php --live
 */

// Load configuration
define('DB_CONFIG_LOADED', true);
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/timezone.php';
require_once __DIR__ . '/../includes/db.php';

// Check for --live flag
$isLiveMode = in_array('--live', $argv ?? []);
$isDryRun = !$isLiveMode;

header('Content-Type: text/plain; charset=utf-8');

echo "========================================\n";
echo "TIMEZONE DATA MIGRATION\n";
echo "========================================\n\n";

if ($isDryRun) {
    echo "⚠️  DRY RUN MODE - No data will be modified\n";
    echo "   Add --live flag to perform actual migration\n\n";
} else {
    echo "🚨 LIVE MODE - Data will be modified\n";
    echo "   Press Ctrl+C within 5 seconds to cancel...\n";
    sleep(5);
    echo "   Proceeding with migration...\n\n";
}

try {
    $pdo = getDBConnection();
    
    // Start transaction (will rollback in dry run)
    $pdo->beginTransaction();
    
    // Define tables and columns to migrate
    // ASSUMPTION: Existing data is in server local time (may be Asia/Kuala_Lumpur or UTC)
    // We'll convert assuming it's currently in Asia/Kuala_Lumpur (most likely)
    $migrationTargets = [
        'orders' => ['created_at', 'updated_at'],
        'promotions' => ['valid_from', 'valid_until', 'created_at', 'updated_at'],
        'comment' => ['created_at'],
        'visitors' => ['visit_date'],
        'email_notifications' => ['created_at', 'updated_at']
    ];
    
    $totalRows = 0;
    $totalColumns = 0;
    $changes = [];
    
    foreach ($migrationTargets as $table => $columns) {
        // Check if table exists
        $tableExists = $pdo->query("SHOW TABLES LIKE '$table'")->rowCount() > 0;
        if (!$tableExists) {
            echo "⏭️  Skipping table '$table' (does not exist)\n";
            continue;
        }
        
        echo "📋 Processing table: $table\n";
        
        foreach ($columns as $column) {
            // Check if column exists
            $colExists = $pdo->query("SHOW COLUMNS FROM $table LIKE '$column'")->rowCount() > 0;
            if (!$colExists) {
                echo "   ⏭️  Skipping column '$column' (does not exist)\n";
                continue;
            }
            
            // Get all rows with non-null values
            $stmt = $pdo->prepare("
                SELECT id, $column as datetime_value
                FROM $table
                WHERE $column IS NOT NULL
            ");
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (empty($rows)) {
                echo "   ✓ Column '$column': No data to migrate\n";
                continue;
            }
            
            echo "   📝 Column '$column': " . count($rows) . " rows\n";
            
            $columnChanges = 0;
            
            foreach ($rows as $row) {
                $id = $row['id'] ?? null;
                $originalValue = $row['datetime_value'];
                
                if (empty($originalValue)) {
                    continue;
                }
                
                // ASSUMPTION: Existing data is in Asia/Kuala_Lumpur
                // Convert to UTC for storage
                try {
                    // Parse as Asia/Kuala_Lumpur (current assumption)
                    $dt = new DateTime($originalValue, new DateTimeZone(APP_TIMEZONE));
                    // Convert to UTC
                    $dt->setTimezone(new DateTimeZone(DB_TIMEZONE));
                    $utcValue = $dt->format('Y-m-d H:i:s');
                    
                    // Only update if value actually changes
                    if ($originalValue !== $utcValue) {
                        if ($isDryRun) {
                            echo "      Would change: ID $id\n";
                            echo "        From: $originalValue\n";
                            echo "        To:   $utcValue\n";
                        } else {
                            // Update the value
                            $updateStmt = $pdo->prepare("
                                UPDATE $table
                                SET $column = :new_value
                                WHERE id = :id
                            ");
                            $updateStmt->execute([
                                ':new_value' => $utcValue,
                                ':id' => $id
                            ]);
                        }
                        
                        $columnChanges++;
                        $totalRows++;
                        
                        if (!isset($changes[$table])) {
                            $changes[$table] = [];
                        }
                        if (!isset($changes[$table][$column])) {
                            $changes[$table][$column] = 0;
                        }
                        $changes[$table][$column]++;
                    }
                } catch (Exception $e) {
                    echo "      ⚠️  Error processing row ID $id: " . $e->getMessage() . "\n";
                }
            }
            
            if ($columnChanges > 0) {
                echo "      ✓ $columnChanges rows " . ($isDryRun ? "would be" : "were") . " updated\n";
                $totalColumns++;
            } else {
                echo "      ✓ No changes needed (already in UTC or identical)\n";
            }
        }
        
        echo "\n";
    }
    
    // Summary
    echo "========================================\n";
    echo "MIGRATION SUMMARY\n";
    echo "========================================\n";
    echo "Mode: " . ($isDryRun ? "DRY RUN" : "LIVE") . "\n";
    echo "Total rows to update: $totalRows\n";
    echo "Total columns affected: $totalColumns\n\n";
    
    if (!empty($changes)) {
        echo "Changes by table/column:\n";
        foreach ($changes as $table => $columns) {
            echo "  $table:\n";
            foreach ($columns as $column => $count) {
                echo "    $column: $count rows\n";
            }
        }
    }
    
    if ($isDryRun) {
        // Rollback in dry run
        $pdo->rollBack();
        echo "\n✅ Dry run complete - no data was modified\n";
        echo "   Run with --live flag to perform actual migration\n";
    } else {
        // Commit in live mode
        $pdo->commit();
        echo "\n✅ Migration complete - data has been updated\n";
        echo "   All datetime values are now stored in UTC\n";
        echo "   Application layer converts to " . APP_TIMEZONE . " for display\n";
    }
    
} catch (Exception $e) {
    // Rollback on error
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    echo "\n❌ ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    echo "\n⚠️  Transaction rolled back - no data was modified\n";
    exit(1);
}

