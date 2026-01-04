<?php
/**
 * Timezone Migration Web Interface
 * 
 * Provides web-based interface for timezone analysis and migration
 * 
 * SECURITY: Requires admin login
 */

require_once __DIR__ . '/config.php';
requireAdminLogin();

define('DB_CONFIG_LOADED', true);
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/timezone.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/sidebar_helper.php';

$currentPage = basename(__FILE__);
$action = $_GET['action'] ?? 'analyze';
$message = '';
$messageType = '';
$output = '';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $postAction = $_POST['action'];
    
    if ($postAction === 'migrate' && isset($_POST['confirm']) && $_POST['confirm'] === 'yes') {
        // Live migration
        ob_start();
        try {
            $pdo = getDBConnection();
            $pdo->beginTransaction();
            
        // Define tables with their datetime columns and primary key column names
        $migrationTargets = [
            'orders' => [
                'columns' => ['created_at', 'updated_at'],
                'primary_key' => 'id'
            ],
            'promotions' => [
                'columns' => ['valid_from', 'valid_until', 'created_at', 'updated_at'],
                'primary_key' => 'id'
            ],
            'comment' => [
                'columns' => ['created_at'],
                'primary_key' => 'comment_id'
            ],
            'visitors' => [
                'columns' => ['visit_date'],
                'primary_key' => 'visitor_id'
            ],
            'email_notifications' => [
                'columns' => ['created_at', 'updated_at'],
                'primary_key' => 'notification_id'
            ]
        ];
        
        $totalRows = 0;
        $totalColumns = 0;
        $changes = [];
        
        echo "========================================\n";
        echo "TIMEZONE DATA MIGRATION (LIVE MODE)\n";
        echo "========================================\n\n";
        
        foreach ($migrationTargets as $table => $config) {
            $columns = $config['columns'];
            $primaryKey = $config['primary_key'];
                $tableExists = $pdo->query("SHOW TABLES LIKE '$table'")->rowCount() > 0;
                if (!$tableExists) {
                    echo "⏭️  Skipping table '$table' (does not exist)\n";
                    continue;
                }
                
                echo "📋 Processing table: $table\n";
                
                foreach ($columns as $column) {
                    $colExists = $pdo->query("SHOW COLUMNS FROM $table LIKE '$column'")->rowCount() > 0;
                    if (!$colExists) {
                        echo "   ⏭️  Skipping column '$column' (does not exist)\n";
                        continue;
                    }
                    
                    $stmt = $pdo->prepare("
                        SELECT $primaryKey, $column as datetime_value
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
                        $id = $row[$primaryKey] ?? null;
                        $originalValue = $row['datetime_value'];
                        
                        if (empty($originalValue)) {
                            continue;
                        }
                        
                        try {
                            $dt = new DateTime($originalValue, new DateTimeZone(APP_TIMEZONE));
                            $dt->setTimezone(new DateTimeZone(DB_TIMEZONE));
                            $utcValue = $dt->format('Y-m-d H:i:s');
                            
                            if ($originalValue !== $utcValue) {
                                $updateStmt = $pdo->prepare("
                                    UPDATE $table
                                    SET $column = :new_value
                                    WHERE $primaryKey = :id
                                ");
                                $updateStmt->execute([
                                    ':new_value' => $utcValue,
                                    ':id' => $id
                                ]);
                                
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
                        echo "      ✓ $columnChanges rows were updated\n";
                        $totalColumns++;
                    } else {
                        echo "      ✓ No changes needed\n";
                    }
                }
                
                echo "\n";
            }
            
            echo "========================================\n";
            echo "MIGRATION SUMMARY\n";
            echo "========================================\n";
            echo "Total rows updated: $totalRows\n";
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
            
            $pdo->commit();
            echo "\n✅ Migration complete - data has been updated\n";
            $message = "Migration completed successfully! $totalRows rows updated across $totalColumns columns.";
            $messageType = 'success';
            
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            echo "\n❌ ERROR: " . $e->getMessage() . "\n";
            $message = "Migration failed: " . $e->getMessage();
            $messageType = 'error';
        }
        
        $output = ob_get_clean();
        $action = 'result';
    }
}

// Run analysis
if ($action === 'analyze') {
    ob_start();
    try {
        $pdo = getDBConnection();
        
        echo "========================================\n";
        echo "TIMEZONE STATE ANALYSIS\n";
        echo "========================================\n\n";
        
        // PHP Timezone
        echo "1. PHP TIMEZONE STATE\n";
        echo "   Current default timezone: " . date_default_timezone_get() . "\n";
        echo "   Current PHP time: " . date('Y-m-d H:i:s') . "\n";
        echo "   PHP timezone offset: " . date('P') . "\n\n";
        
        // MySQL Timezone
        $stmt = $pdo->query("SELECT @@global.time_zone as global_tz, @@session.time_zone as session_tz");
        $tzInfo = $stmt->fetch(PDO::FETCH_ASSOC);
        
        echo "2. MYSQL TIMEZONE STATE\n";
        echo "   Global timezone: " . ($tzInfo['global_tz'] ?? 'UNKNOWN') . "\n";
        echo "   Session timezone: " . ($tzInfo['session_tz'] ?? 'UNKNOWN') . "\n";
        
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
        
        // Datetime columns
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
        
        // Critical tables
        echo "4. CRITICAL TABLES ANALYSIS\n";
        
        $criticalTables = [
            'orders' => ['created_at', 'updated_at'],
            'promotions' => ['valid_from', 'valid_until', 'created_at', 'updated_at'],
            'comment' => ['created_at'],
            'visitors' => ['visit_date'],
            'email_notifications' => ['created_at', 'updated_at']
        ];
        
        foreach ($criticalTables as $table => $columns) {
            $tableExists = $pdo->query("SHOW TABLES LIKE '$table'")->rowCount() > 0;
            
            if (!$tableExists) {
                echo "   Table '$table': DOES NOT EXIST\n\n";
                continue;
            }
            
            echo "   Table: $table\n";
            
            foreach ($columns as $column) {
                $colExists = $pdo->query("SHOW COLUMNS FROM $table LIKE '$column'")->rowCount() > 0;
                
                if (!$colExists) {
                    echo "     Column '$column': DOES NOT EXIST\n";
                    continue;
                }
                
                $countStmt = $pdo->query("SELECT COUNT(*) as cnt FROM $table WHERE $column IS NOT NULL");
                $count = $countStmt->fetch(PDO::FETCH_ASSOC)['cnt'];
                
                echo "     Column '$column': $count non-null values\n";
                
                if ($count > 0) {
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
        
        echo "========================================\n";
        echo "ANALYSIS COMPLETE\n";
        echo "========================================\n";
        
    } catch (Exception $e) {
        echo "ERROR: " . $e->getMessage() . "\n";
        echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    }
    
    $output = ob_get_clean();
}

// Dry run
if ($action === 'dryrun') {
    ob_start();
    try {
        $pdo = getDBConnection();
        $pdo->beginTransaction();
        
        // Define tables with their datetime columns and primary key column names
        $migrationTargets = [
            'orders' => [
                'columns' => ['created_at', 'updated_at'],
                'primary_key' => 'id'
            ],
            'promotions' => [
                'columns' => ['valid_from', 'valid_until', 'created_at', 'updated_at'],
                'primary_key' => 'id'
            ],
            'comment' => [
                'columns' => ['created_at'],
                'primary_key' => 'comment_id'
            ],
            'visitors' => [
                'columns' => ['visit_date'],
                'primary_key' => 'visitor_id'
            ],
            'email_notifications' => [
                'columns' => ['created_at', 'updated_at'],
                'primary_key' => 'notification_id'
            ]
        ];
        
        $totalRows = 0;
        $totalColumns = 0;
        $changes = [];
        
        echo "========================================\n";
        echo "TIMEZONE DATA MIGRATION (DRY RUN)\n";
        echo "========================================\n\n";
        echo "⚠️  DRY RUN MODE - No data will be modified\n\n";
        
        foreach ($migrationTargets as $table => $config) {
            $columns = $config['columns'];
            $primaryKey = $config['primary_key'];
            $tableExists = $pdo->query("SHOW TABLES LIKE '$table'")->rowCount() > 0;
            if (!$tableExists) {
                echo "⏭️  Skipping table '$table' (does not exist)\n";
                continue;
            }
            
            echo "📋 Processing table: $table\n";
            
            foreach ($columns as $column) {
                $colExists = $pdo->query("SHOW COLUMNS FROM $table LIKE '$column'")->rowCount() > 0;
                if (!$colExists) {
                    echo "   ⏭️  Skipping column '$column' (does not exist)\n";
                    continue;
                }
                
                $stmt = $pdo->prepare("
                    SELECT $primaryKey, $column as datetime_value
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
                $sampleChanges = [];
                
                foreach ($rows as $row) {
                    $id = $row[$primaryKey] ?? null;
                    $originalValue = $row['datetime_value'];
                    
                    if (empty($originalValue)) {
                        continue;
                    }
                    
                    try {
                        $dt = new DateTime($originalValue, new DateTimeZone(APP_TIMEZONE));
                        $dt->setTimezone(new DateTimeZone(DB_TIMEZONE));
                        $utcValue = $dt->format('Y-m-d H:i:s');
                        
                        if ($originalValue !== $utcValue) {
                            if (count($sampleChanges) < 3) {
                                $sampleChanges[] = [
                                    'id' => $id,
                                    'from' => $originalValue,
                                    'to' => $utcValue
                                ];
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
                    echo "      ✓ $columnChanges rows would be updated\n";
                    if (!empty($sampleChanges)) {
                        echo "      Sample changes:\n";
                        foreach ($sampleChanges as $sample) {
                            echo "        ID {$sample['id']}: {$sample['from']} → {$sample['to']}\n";
                        }
                    }
                    $totalColumns++;
                } else {
                    echo "      ✓ No changes needed\n";
                }
            }
            
            echo "\n";
        }
        
        $pdo->rollBack();
        
        echo "========================================\n";
        echo "DRY RUN SUMMARY\n";
        echo "========================================\n";
        echo "Total rows that would be updated: $totalRows\n";
        echo "Total columns that would be affected: $totalColumns\n\n";
        
        if (!empty($changes)) {
            echo "Changes by table/column:\n";
            foreach ($changes as $table => $columns) {
                echo "  $table:\n";
                foreach ($columns as $column => $count) {
                    echo "    $column: $count rows\n";
                }
            }
        }
        
        echo "\n✅ Dry run complete - no data was modified\n";
        
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        echo "\n❌ ERROR: " . $e->getMessage() . "\n";
        echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    }
    
    $output = ob_get_clean();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Timezone Migration - Admin Panel | Projek Cuti Sem</title>
    <link rel="stylesheet" href="/admin/css/admin.css?v=30">
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <style>
        .output-container {
            background: #1e1e1e;
            color: #d4d4d4;
            padding: 1.5rem;
            border-radius: 8px;
            font-family: 'Courier New', monospace;
            font-size: 0.875rem;
            line-height: 1.6;
            max-height: 600px;
            overflow-y: auto;
            white-space: pre-wrap;
            word-wrap: break-word;
            margin-top: 1.5rem;
        }
        .action-buttons {
            display: flex;
            gap: 1rem;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
        }
        .warning-box {
            background: #fef3c7;
            border: 1px solid #f59e0b;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1.5rem;
        }
        .warning-box h3 {
            margin: 0 0 0.5rem 0;
            color: #92400e;
        }
        .warning-box p {
            margin: 0.5rem 0;
            color: #78350f;
        }
        .confirm-box {
            background: #fee2e2;
            border: 2px solid #dc2626;
            border-radius: 8px;
            padding: 1.5rem;
            margin: 1.5rem 0;
        }
        .confirm-box h3 {
            margin: 0 0 1rem 0;
            color: #991b1b;
        }
        .confirm-box label {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin: 1rem 0;
            font-weight: 600;
        }
    </style>
</head>
<body class="admin-dashboard">
    <!-- Mobile Menu Toggle -->
    <button class="mobile-menu-toggle" id="mobileMenuToggle" aria-label="Toggle menu">☰</button>
    
    <!-- Sidebar Overlay -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    
    <!-- Dynamic Sidebar -->
    <?php echo generateAdminSidebar($currentPage); ?>
    
    <!-- Main Content -->
    <main class="main-content">
        <header class="content-header">
            <h1>Timezone Migration</h1>
        </header>
        
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType === 'success' ? 'success' : 'error'; ?>" style="margin-bottom: 1.5rem;">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        
        <div class="warning-box">
            <h3>⚠️ Important Information</h3>
            <p><strong>Before running migration:</strong></p>
            <ul style="margin: 0.5rem 0; padding-left: 1.5rem;">
                <li>Backup your database</li>
                <li>Run analysis first to understand current state</li>
                <li>Run dry run to see what would change</li>
                <li>Only run live migration when ready</li>
            </ul>
            <p style="margin-top: 1rem;"><strong>Migration Strategy:</strong></p>
            <ul style="margin: 0.5rem 0; padding-left: 1.5rem;">
                <li>Assumes existing data is in Asia/Kuala_Lumpur</li>
                <li>Converts to UTC for storage</li>
                <li>Application layer converts to Asia/Kuala_Lumpur for display</li>
            </ul>
        </div>
        
        <div class="action-buttons">
            <a href="?action=analyze" class="btn btn-primary">📊 Run Analysis</a>
            <a href="?action=dryrun" class="btn btn-secondary">🔍 Dry Run (Test)</a>
        </div>
        
        <?php if ($action === 'migrate'): ?>
            <div class="confirm-box">
                <h3>🚨 Confirm Live Migration</h3>
                <p>You are about to perform a <strong>LIVE MIGRATION</strong> that will modify your database.</p>
                <p><strong>This action cannot be undone without a database backup!</strong></p>
                
                <form method="POST">
                    <input type="hidden" name="action" value="migrate">
                    <label>
                        <input type="checkbox" name="confirm" value="yes" required>
                        I have backed up my database and understand this will modify data
                    </label>
                    <div style="margin-top: 1rem;">
                        <button type="submit" class="btn btn-danger">🚨 Execute Live Migration</button>
                        <a href="?" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        <?php endif; ?>
        
        <?php if ($action === 'dryrun' || $action === 'result'): ?>
            <div style="margin-top: 1.5rem;">
                <a href="?action=migrate" class="btn btn-danger">🚨 Proceed to Live Migration</a>
                <a href="?" class="btn btn-secondary">Back to Analysis</a>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($output)): ?>
            <div class="output-container">
                <?php echo htmlspecialchars($output); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($action === 'analyze' && empty($output)): ?>
            <div style="text-align: center; padding: 3rem; color: #6b7280;">
                <p>Click "Run Analysis" to analyze your current timezone state.</p>
            </div>
        <?php endif; ?>
    </main>
    
    <script src="/admin/js/admin.js"></script>
</body>
</html>

