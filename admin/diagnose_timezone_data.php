<?php
/**
 * Timezone Data Diagnosis
 * 
 * Analyzes existing datetime data to determine what timezone it's actually in
 * This helps identify if migration converted data incorrectly
 */

require_once __DIR__ . '/config.php';
requireAdminLogin();

define('DB_CONFIG_LOADED', true);
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/timezone.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/sidebar_helper.php';

$currentPage = basename(__FILE__);

try {
    $pdo = getDBConnection();
    
    // Get current time in both timezones
    $currentAppTime = getCurrentDateTime(); // Asia/Kuala_Lumpur
    $currentUTCTime = getCurrentDateTimeUTC(); // UTC
    
    // Get MySQL NOW() (should be UTC after our fix)
    $stmt = $pdo->query("SELECT NOW() as mysql_now, UTC_TIMESTAMP() as mysql_utc");
    $mysqlTime = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Sample data from each table
    $samples = [];
    
    // Check promotions table
    $promoStmt = $pdo->query("
        SELECT id, valid_from, valid_until, created_at, updated_at
        FROM promotions
        ORDER BY id DESC
        LIMIT 5
    ");
    $promotions = $promoStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Check orders table
    $orderStmt = $pdo->query("
        SELECT id, created_at
        FROM orders
        ORDER BY id DESC
        LIMIT 5
    ");
    $orders = $orderStmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $error = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Timezone Diagnosis - Admin Panel | Projek Cuti Sem</title>
    <link rel="stylesheet" href="/admin/css/admin.css?v=30">
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <style>
        .diagnosis-box {
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }
        .diagnosis-box h3 {
            margin: 0 0 1rem 0;
            color: #111827;
        }
        .time-comparison {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin: 1rem 0;
        }
        .time-box {
            background: white;
            border: 1px solid #d1d5db;
            border-radius: 4px;
            padding: 1rem;
        }
        .time-box strong {
            display: block;
            margin-bottom: 0.5rem;
            color: #374151;
        }
        .time-value {
            font-family: 'Courier New', monospace;
            font-size: 1.1rem;
            color: #111827;
        }
        .sample-data {
            background: white;
            border: 1px solid #d1d5db;
            border-radius: 4px;
            padding: 1rem;
            margin-top: 1rem;
        }
        .sample-data table {
            width: 100%;
            border-collapse: collapse;
        }
        .sample-data th,
        .sample-data td {
            padding: 0.5rem;
            text-align: left;
            border-bottom: 1px solid #e5e7eb;
        }
        .sample-data th {
            background: #f3f4f6;
            font-weight: 600;
        }
        .interpretation {
            background: #fef3c7;
            border-left: 4px solid #f59e0b;
            padding: 1rem;
            margin-top: 1rem;
        }
        .interpretation h4 {
            margin: 0 0 0.5rem 0;
            color: #92400e;
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
            <h1>Timezone Data Diagnosis</h1>
        </header>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-error">
                Error: <?php echo htmlspecialchars($error); ?>
            </div>
        <?php else: ?>
        
        <!-- Current Time Reference -->
        <div class="diagnosis-box">
            <h3>🕐 Current Time Reference</h3>
            <div class="time-comparison">
                <div class="time-box">
                    <strong>Asia/Kuala_Lumpur (Expected Display)</strong>
                    <div class="time-value"><?php echo htmlspecialchars($currentAppTime); ?></div>
                    <small style="color: #6b7280;">This is what users should see</small>
                </div>
                <div class="time-box">
                    <strong>UTC (Database Storage)</strong>
                    <div class="time-value"><?php echo htmlspecialchars($currentUTCTime); ?></div>
                    <small style="color: #6b7280;">This is what should be in database</small>
                </div>
            </div>
            <div style="margin-top: 1rem; padding: 1rem; background: #eff6ff; border-radius: 4px;">
                <strong>MySQL NOW():</strong> <?php echo htmlspecialchars($mysqlTime['mysql_now']); ?><br>
                <strong>MySQL UTC_TIMESTAMP():</strong> <?php echo htmlspecialchars($mysqlTime['mysql_utc']); ?>
            </div>
        </div>
        
        <!-- Promotions Data -->
        <?php if (!empty($promotions)): ?>
        <div class="diagnosis-box">
            <h3>🎟️ Promotions Table Data</h3>
            <div class="sample-data">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>valid_from (DB)</th>
                            <th>valid_from (Display)</th>
                            <th>valid_until (DB)</th>
                            <th>valid_until (Display)</th>
                            <th>created_at (DB)</th>
                            <th>created_at (Display)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($promotions as $promo): ?>
                        <tr>
                            <td><?php echo $promo['id']; ?></td>
                            <td style="font-family: monospace;">
                                <?php echo htmlspecialchars($promo['valid_from'] ?? '-'); ?>
                            </td>
                            <td style="font-family: monospace; color: #059669;">
                                <?php 
                                if ($promo['valid_from']) {
                                    echo htmlspecialchars(dbToAppTime($promo['valid_from']));
                                } else {
                                    echo '-';
                                }
                                ?>
                            </td>
                            <td style="font-family: monospace;">
                                <?php echo htmlspecialchars($promo['valid_until'] ?? '-'); ?>
                            </td>
                            <td style="font-family: monospace; color: #059669;">
                                <?php 
                                if ($promo['valid_until']) {
                                    echo htmlspecialchars(dbToAppTime($promo['valid_until']));
                                } else {
                                    echo '-';
                                }
                                ?>
                            </td>
                            <td style="font-family: monospace;">
                                <?php echo htmlspecialchars($promo['created_at'] ?? '-'); ?>
                            </td>
                            <td style="font-family: monospace; color: #059669;">
                                <?php 
                                if ($promo['created_at']) {
                                    echo htmlspecialchars(dbToAppTime($promo['created_at']));
                                } else {
                                    echo '-';
                                }
                                ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="interpretation">
                <h4>🔍 Interpretation</h4>
                <p><strong>DB Column:</strong> Raw value stored in database</p>
                <p><strong>Display Column:</strong> Value converted from UTC to Asia/Kuala_Lumpur (what users should see)</p>
                <p><strong>Expected:</strong> Display column should show Malaysia time (8 hours ahead of UTC)</p>
                <p><strong>If times look wrong:</strong> The data might have been converted incorrectly during migration</p>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Orders Data -->
        <?php if (!empty($orders)): ?>
        <div class="diagnosis-box">
            <h3>📦 Orders Table Data</h3>
            <div class="sample-data">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>created_at (DB)</th>
                            <th>created_at (Display)</th>
                            <th>Difference</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orders as $order): ?>
                        <?php
                        $dbTime = $order['created_at'];
                        $displayTime = dbToAppTime($dbTime);
                        
                        // Calculate difference
                        try {
                            $dbDt = new DateTime($dbTime, new DateTimeZone('UTC'));
                            $displayDt = new DateTime($displayTime, new DateTimeZone(APP_TIMEZONE));
                            $diff = $displayDt->getTimestamp() - $dbDt->getTimestamp();
                            $diffHours = round($diff / 3600, 1);
                        } catch (Exception $e) {
                            $diffHours = 'N/A';
                        }
                        ?>
                        <tr>
                            <td><?php echo $order['id']; ?></td>
                            <td style="font-family: monospace;">
                                <?php echo htmlspecialchars($dbTime); ?>
                            </td>
                            <td style="font-family: monospace; color: #059669;">
                                <?php echo htmlspecialchars($displayTime); ?>
                            </td>
                            <td>
                                <?php 
                                if ($diffHours === 8.0) {
                                    echo '<span style="color: #059669;">✓ Correct (+8h)</span>';
                                } else {
                                    echo '<span style="color: #dc2626;">⚠️ ' . $diffHours . 'h (Expected +8h)</span>';
                                }
                                ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Recommendations -->
        <div class="diagnosis-box">
            <h3>💡 Recommendations</h3>
            <ol style="line-height: 1.8;">
                <li><strong>Check the Display column:</strong> Does it show the correct Malaysia time?</li>
                <li><strong>If times are wrong:</strong> The migration may have double-converted or converted from wrong timezone</li>
                <li><strong>If DB times look like Malaysia time:</strong> Data might not have been converted to UTC</li>
                <li><strong>If DB times look like UTC:</strong> Data is correctly stored, check display conversion</li>
            </ol>
            
            <div style="margin-top: 1rem; padding: 1rem; background: #fee2e2; border-radius: 4px;">
                <strong>⚠️ If times are incorrect:</strong>
                <p style="margin: 0.5rem 0 0 0;">
                    You may need to reverse the migration and re-run it with correct assumptions about existing data timezone.
                </p>
            </div>
        </div>
        
        <?php endif; ?>
        
        <div style="margin-top: 1.5rem;">
            <a href="timezone_migration.php" class="btn btn-secondary">← Back to Migration</a>
        </div>
    </main>
    
    <script src="/admin/js/admin.js"></script>
</body>
</html>

