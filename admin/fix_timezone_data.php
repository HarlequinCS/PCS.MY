<?php
/**
 * Timezone Data Fix Script
 * 
 * If migration converted data incorrectly, this script can fix it
 * 
 * SCENARIOS:
 * 1. Data was already in UTC, but migration converted it again (double conversion)
 *    - Fix: Add 8 hours back (reverse the conversion)
 * 
 * 2. Data was in Asia/Kuala_Lumpur, migration converted to UTC correctly
 *    - No fix needed, just ensure display uses dbToAppTime()
 * 
 * 3. Data was in different timezone
 *    - Manual fix required
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

// Handle fix action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'fix') {
    $fixType = $_POST['fix_type'] ?? '';
    $confirm = isset($_POST['confirm']) && $_POST['confirm'] === 'yes';
    
    if (!$confirm) {
        $message = 'Please confirm the fix operation';
        $messageType = 'error';
    } else {
        ob_start();
        try {
            $pdo = getDBConnection();
            $pdo->beginTransaction();
            
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
            
            echo "========================================\n";
            echo "TIMEZONE DATA FIX\n";
            echo "========================================\n\n";
            
            if ($fixType === 'reverse') {
                echo "FIX TYPE: Reverse Migration (Add 8 hours)\n";
                echo "This fixes data that was incorrectly converted from UTC to UTC\n\n";
            } elseif ($fixType === 'convert_to_utc') {
                echo "FIX TYPE: Convert to UTC (Subtract 8 hours)\n";
                echo "This converts Asia/Kuala_Lumpur data to UTC\n\n";
            }
            
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
                        echo "   ✓ Column '$column': No data to fix\n";
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
                            if ($fixType === 'reverse') {
                                // Reverse: Add 8 hours (UTC -> Asia/Kuala_Lumpur, then treat as UTC)
                                // Actually: Parse as UTC, add 8 hours, store as UTC
                                $dt = new DateTime($originalValue, new DateTimeZone('UTC'));
                                $dt->modify('+8 hours');
                                $fixedValue = $dt->format('Y-m-d H:i:s');
                            } elseif ($fixType === 'convert_to_utc') {
                                // Convert: Subtract 8 hours (Asia/Kuala_Lumpur -> UTC)
                                $dt = new DateTime($originalValue, new DateTimeZone(APP_TIMEZONE));
                                $dt->setTimezone(new DateTimeZone('UTC'));
                                $fixedValue = $dt->format('Y-m-d H:i:s');
                            } else {
                                continue;
                            }
                            
                            if ($originalValue !== $fixedValue) {
                                $updateStmt = $pdo->prepare("
                                    UPDATE $table
                                    SET $column = :new_value
                                    WHERE $primaryKey = :id
                                ");
                                $updateStmt->execute([
                                    ':new_value' => $fixedValue,
                                    ':id' => $id
                                ]);
                                
                                $columnChanges++;
                                $totalRows++;
                            }
                        } catch (Exception $e) {
                            echo "      ⚠️  Error processing row ID $id: " . $e->getMessage() . "\n";
                        }
                    }
                    
                    if ($columnChanges > 0) {
                        echo "      ✓ $columnChanges rows were fixed\n";
                    } else {
                        echo "      ✓ No changes needed\n";
                    }
                }
                
                echo "\n";
            }
            
            $pdo->commit();
            echo "========================================\n";
            echo "FIX COMPLETE\n";
            echo "========================================\n";
            echo "Total rows fixed: $totalRows\n";
            
            $message = "Fix completed successfully! $totalRows rows updated.";
            $messageType = 'success';
            
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            echo "\n❌ ERROR: " . $e->getMessage() . "\n";
            $message = "Fix failed: " . $e->getMessage();
            $messageType = 'error';
        }
        
        $output = ob_get_clean();
        $action = 'result';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fix Timezone Data - Admin Panel | Projek Cuti Sem</title>
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
        .warning-box {
            background: #fee2e2;
            border: 2px solid #dc2626;
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }
        .warning-box h3 {
            margin: 0 0 1rem 0;
            color: #991b1b;
        }
        .fix-option {
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 1rem;
        }
        .fix-option h4 {
            margin: 0 0 0.5rem 0;
        }
        .fix-option p {
            margin: 0.5rem 0;
            color: #6b7280;
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
            <h1>Fix Timezone Data</h1>
        </header>
        
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType === 'success' ? 'success' : 'error'; ?>" style="margin-bottom: 1.5rem;">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        
        <div class="warning-box">
            <h3>⚠️ Use This Tool Only If Times Are Wrong</h3>
            <p><strong>Before using this tool:</strong></p>
            <ol style="margin: 0.5rem 0; padding-left: 1.5rem;">
                <li>Go to "Diagnose Data" to check what's wrong</li>
                <li>Backup your database</li>
                <li>Understand which fix type you need</li>
            </ol>
        </div>
        
        <div style="margin-bottom: 1.5rem;">
            <a href="diagnose_timezone_data.php" class="btn btn-primary">🔬 Diagnose Data First</a>
            <a href="timezone_migration.php" class="btn btn-secondary">← Back to Migration</a>
        </div>
        
        <?php if ($action !== 'result'): ?>
        <form method="POST">
            <input type="hidden" name="action" value="fix">
            
            <div class="fix-option">
                <h4>Option 1: Reverse Migration (Add 8 hours)</h4>
                <p><strong>Use this if:</strong> Data was already in UTC, but migration converted it again (double conversion)</p>
                <p><strong>Example:</strong> UTC time 21:20:14 was converted to 13:20:14 (wrong!)</p>
                <p><strong>Fix:</strong> Add 8 hours back: 13:20:14 → 21:20:14</p>
                <label style="display: flex; align-items: center; gap: 0.5rem; margin-top: 1rem;">
                    <input type="radio" name="fix_type" value="reverse" required>
                    <strong>Select this option</strong>
                </label>
            </div>
            
            <div class="fix-option">
                <h4>Option 2: Convert to UTC (Subtract 8 hours)</h4>
                <p><strong>Use this if:</strong> Data is still in Asia/Kuala_Lumpur and needs to be converted to UTC</p>
                <p><strong>Example:</strong> Malaysia time 05:20:14 needs to become UTC 21:20:14</p>
                <p><strong>Fix:</strong> Subtract 8 hours: 05:20:14 → 21:20:14 (previous day)</p>
                <label style="display: flex; align-items: center; gap: 0.5rem; margin-top: 1rem;">
                    <input type="radio" name="fix_type" value="convert_to_utc" required>
                    <strong>Select this option</strong>
                </label>
            </div>
            
            <div class="warning-box" style="margin-top: 1.5rem;">
                <label style="display: flex; align-items: center; gap: 0.5rem;">
                    <input type="checkbox" name="confirm" value="yes" required>
                    <strong>I have backed up my database and understand this will modify data</strong>
                </label>
            </div>
            
            <div style="margin-top: 1.5rem;">
                <button type="submit" class="btn btn-danger">🚨 Execute Fix</button>
                <a href="?" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
        <?php endif; ?>
        
        <?php if (!empty($output)): ?>
            <div class="output-container">
                <?php echo htmlspecialchars($output); ?>
            </div>
        <?php endif; ?>
    </main>
    
    <script src="/admin/js/admin.js"></script>
</body>
</html>

