<?php
/**
 * Backfill Customer Emails to Newsletter List (Web Version)
 * 
 * This script adds all existing customer emails from paid orders
 * to the email_notifications table with source='customer'
 * 
 * SECURITY: Requires admin login
 * 
 * USAGE:
 *   Access via browser: /DB Update/backfill_customer_emails_web.php
 *   Add ?live=1 to perform actual migration (otherwise dry run)
 */

// Load configuration
define('DB_CONFIG_LOADED', true);
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/timezone.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../admin/config.php';

// Require admin login
requireAdminLogin();

// Check for live mode
$isLiveMode = isset($_GET['live']) && $_GET['live'] == '1';
$isDryRun = !$isLiveMode;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Backfill Customer Emails - Migration Tool</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: #f5f5f5;
            padding: 2rem;
            line-height: 1.6;
        }
        .container {
            max-width: 900px;
            margin: 0 auto;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 2rem;
        }
        h1 {
            color: #333;
            margin-bottom: 1rem;
            border-bottom: 3px solid #d00000;
            padding-bottom: 0.5rem;
        }
        .mode-banner {
            padding: 1rem;
            border-radius: 6px;
            margin-bottom: 2rem;
            font-weight: 600;
        }
        .mode-banner.dry-run {
            background: #fff3cd;
            border: 2px solid #ffc107;
            color: #856404;
        }
        .mode-banner.live {
            background: #f8d7da;
            border: 2px solid #dc3545;
            color: #721c24;
        }
        .step {
            margin-bottom: 2rem;
            padding: 1rem;
            background: #f8f9fa;
            border-radius: 6px;
            border-left: 4px solid #007bff;
        }
        .step h2 {
            color: #007bff;
            margin-bottom: 0.5rem;
            font-size: 1.1rem;
        }
        .summary {
            background: #e7f3ff;
            border: 2px solid #007bff;
            border-radius: 6px;
            padding: 1.5rem;
            margin: 2rem 0;
        }
        .summary h2 {
            color: #007bff;
            margin-bottom: 1rem;
        }
        .summary-item {
            display: flex;
            justify-content: space-between;
            padding: 0.5rem 0;
            border-bottom: 1px solid #cce5ff;
        }
        .summary-item:last-child {
            border-bottom: none;
        }
        .summary-item strong {
            color: #333;
        }
        .summary-item .count {
            font-weight: 700;
            color: #007bff;
            font-size: 1.1rem;
        }
        .email-list {
            max-height: 400px;
            overflow-y: auto;
            background: white;
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 1rem;
            margin-top: 1rem;
        }
        .email-item {
            padding: 0.5rem;
            border-bottom: 1px solid #eee;
            font-size: 0.9rem;
        }
        .email-item:last-child {
            border-bottom: none;
        }
        .email-item .email {
            font-weight: 600;
            color: #333;
        }
        .email-item .meta {
            color: #666;
            font-size: 0.85rem;
            margin-top: 0.25rem;
        }
        .btn {
            display: inline-block;
            padding: 0.75rem 1.5rem;
            background: #d00000;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 600;
            margin-top: 1rem;
            border: none;
            cursor: pointer;
            font-size: 1rem;
        }
        .btn:hover {
            background: #b00000;
        }
        .btn-secondary {
            background: #6c757d;
        }
        .btn-secondary:hover {
            background: #5a6268;
        }
        .success {
            background: #d4edda;
            border: 2px solid #28a745;
            color: #155724;
            padding: 1rem;
            border-radius: 6px;
            margin: 1rem 0;
        }
        .error {
            background: #f8d7da;
            border: 2px solid #dc3545;
            color: #721c24;
            padding: 1rem;
            border-radius: 6px;
            margin: 1rem 0;
        }
        .info {
            background: #d1ecf1;
            border: 2px solid #17a2b8;
            color: #0c5460;
            padding: 1rem;
            border-radius: 6px;
            margin: 1rem 0;
        }
        .actions {
            margin-top: 2rem;
            padding-top: 2rem;
            border-top: 2px solid #eee;
        }
        .actions a {
            margin-right: 1rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>📧 Backfill Customer Emails to Newsletter</h1>
        
        <?php if ($isDryRun): ?>
            <div class="mode-banner dry-run">
                ⚠️ DRY RUN MODE - No data will be modified
            </div>
        <?php else: ?>
            <div class="mode-banner live">
                🚨 LIVE MODE - Data will be modified
            </div>
        <?php endif; ?>
        
        <?php
        try {
            $pdo = getDBConnection();
            
            // Step 1: Add source column if it doesn't exist
            echo '<div class="step">';
            echo '<h2>Step 1: Checking for \'source\' column</h2>';
            $columns = $pdo->query("SHOW COLUMNS FROM email_notifications LIKE 'source'")->fetchAll();
            if (empty($columns)) {
                if ($isLiveMode) {
                    $pdo->exec("ALTER TABLE email_notifications ADD COLUMN source VARCHAR(20) DEFAULT 'subscriber' AFTER status");
                    echo '<p class="success">✓ Added \'source\' column to email_notifications table</p>';
                } else {
                    echo '<p class="info">[DRY RUN] Would add \'source\' column to email_notifications table</p>';
                }
            } else {
                echo '<p class="success">✓ \'source\' column already exists</p>';
            }
            echo '</div>';
            
            // Step 2: Get all unique customer emails from paid orders
            echo '<div class="step">';
            echo '<h2>Step 2: Fetching customer emails from paid orders</h2>';
            $customerEmailsStmt = $pdo->query("
                SELECT DISTINCT 
                    email,
                    MAX(created_at) as last_order_date,
                    COUNT(*) as order_count
                FROM orders 
                WHERE status = 'paid' 
                AND email IS NOT NULL 
                AND email != ''
                AND email REGEXP '^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\\.[A-Za-z]{2,}$'
                GROUP BY email
                ORDER BY last_order_date DESC
            ");
            $customerEmails = $customerEmailsStmt->fetchAll(PDO::FETCH_ASSOC);
            
            $totalCustomers = count($customerEmails);
            echo '<p>Found <strong>' . $totalCustomers . '</strong> unique customer emails from paid orders</p>';
            echo '</div>';
            
            if ($totalCustomers === 0) {
                echo '<div class="info">No customer emails found. Nothing to migrate.</div>';
                echo '<div class="actions"><a href="backfill_customer_emails_web.php" class="btn btn-secondary">Run Again</a></div>';
                exit;
            }
            
            // Step 3: Check which emails already exist in newsletter list
            echo '<div class="step">';
            echo '<h2>Step 3: Checking existing newsletter emails</h2>';
            $existingEmailsStmt = $pdo->query("SELECT email, source FROM email_notifications");
            $existingEmails = $existingEmailsStmt->fetchAll(PDO::FETCH_ASSOC);
            $existingEmailMap = [];
            foreach ($existingEmails as $existing) {
                $existingEmailMap[strtolower($existing['email'])] = $existing['source'] ?? 'subscriber';
            }
            
            $existingCount = count($existingEmailMap);
            echo '<p>Found <strong>' . $existingCount . '</strong> emails already in newsletter list</p>';
            echo '</div>';
            
            // Step 4: Process each customer email
            echo '<div class="step">';
            echo '<h2>Step 4: Processing customer emails</h2>';
            
            $toAdd = [];
            $toUpdate = [];
            $skipped = [];
            
            foreach ($customerEmails as $customer) {
                $email = strtolower(trim($customer['email']));
                $orderCount = $customer['order_count'];
                $lastOrder = $customer['last_order_date'];
                
                if (isset($existingEmailMap[$email])) {
                    $currentSource = $existingEmailMap[$email];
                    
                    // If already exists as customer, skip
                    if ($currentSource === 'customer') {
                        $skipped[] = [
                            'email' => $customer['email'],
                            'reason' => 'Already exists as customer'
                        ];
                        continue;
                    }
                    
                    // If exists as subscriber, mark for update
                    if ($currentSource === 'subscriber' || empty($currentSource)) {
                        $toUpdate[] = [
                            'email' => $customer['email'],
                            'order_count' => $orderCount,
                            'last_order' => $lastOrder
                        ];
                    }
                } else {
                    // New email to add
                    $toAdd[] = [
                        'email' => $customer['email'],
                        'order_count' => $orderCount,
                        'last_order' => $lastOrder
                    ];
                }
            }
            
            // Display summary
            echo '<div class="summary">';
            echo '<h2>Summary</h2>';
            echo '<div class="summary-item">';
            echo '<strong>New emails to add:</strong>';
            echo '<span class="count">' . count($toAdd) . '</span>';
            echo '</div>';
            echo '<div class="summary-item">';
            echo '<strong>Existing emails to update (subscriber → customer):</strong>';
            echo '<span class="count">' . count($toUpdate) . '</span>';
            echo '</div>';
            echo '<div class="summary-item">';
            echo '<strong>Already customers (skipped):</strong>';
            echo '<span class="count">' . count($skipped) . '</span>';
            echo '</div>';
            echo '<div class="summary-item">';
            echo '<strong>Total to process:</strong>';
            echo '<span class="count">' . (count($toAdd) + count($toUpdate)) . '</span>';
            echo '</div>';
            echo '</div>';
            
            if (count($toAdd) === 0 && count($toUpdate) === 0) {
                echo '<div class="success">✓ All customer emails are already in the newsletter list!</div>';
                echo '<div class="actions"><a href="backfill_customer_emails_web.php" class="btn btn-secondary">Run Again</a></div>';
                exit;
            }
            
            // Step 5: Perform migration
            if ($isLiveMode) {
                echo '<div class="step">';
                echo '<h2>Step 5: Performing migration</h2>';
                
                $pdo->beginTransaction();
                
                try {
                    $addedCount = 0;
                    $updatedCount = 0;
                    $nowUTC = getCurrentDateTimeUTC();
                    
                    // Add new emails
                    if (count($toAdd) > 0) {
                        echo '<p><strong>Adding ' . count($toAdd) . ' new customer emails...</strong></p>';
                        $insertStmt = $pdo->prepare("
                            INSERT INTO email_notifications 
                            (email, ip_address, user_agent, status, source, created_at, updated_at) 
                            VALUES (?, NULL, NULL, 'active', 'customer', ?, ?)
                        ");
                        
                        echo '<div class="email-list">';
                        foreach ($toAdd as $customer) {
                            try {
                                $insertStmt->execute([$customer['email'], $nowUTC, $nowUTC]);
                                $addedCount++;
                                echo '<div class="email-item">';
                                echo '<div class="email">✓ Added: ' . htmlspecialchars($customer['email']) . '</div>';
                                echo '<div class="meta">Orders: ' . $customer['order_count'] . ' | Last: ' . $customer['last_order'] . '</div>';
                                echo '</div>';
                            } catch (PDOException $e) {
                                if ($e->getCode() == 23000) {
                                    // Duplicate entry
                                    echo '<div class="email-item">';
                                    echo '<div class="email">⚠ Skipped duplicate: ' . htmlspecialchars($customer['email']) . '</div>';
                                    echo '</div>';
                                } else {
                                    throw $e;
                                }
                            }
                        }
                        echo '</div>';
                    }
                    
                    // Update existing subscriber emails to customer
                    if (count($toUpdate) > 0) {
                        echo '<p style="margin-top: 1.5rem;"><strong>Updating ' . count($toUpdate) . ' subscriber emails to customer...</strong></p>';
                        $updateStmt = $pdo->prepare("
                            UPDATE email_notifications 
                            SET source = 'customer', updated_at = ? 
                            WHERE email = ? AND (source = 'subscriber' OR source IS NULL)
                        ");
                        
                        echo '<div class="email-list">';
                        foreach ($toUpdate as $customer) {
                            $updateStmt->execute([$nowUTC, $customer['email']]);
                            $updatedCount++;
                            echo '<div class="email-item">';
                            echo '<div class="email">✓ Updated: ' . htmlspecialchars($customer['email']) . '</div>';
                            echo '<div class="meta">Orders: ' . $customer['order_count'] . ' | Last: ' . $customer['last_order'] . '</div>';
                            echo '</div>';
                        }
                        echo '</div>';
                    }
                    
                    $pdo->commit();
                    
                    echo '<div class="success" style="margin-top: 2rem;">';
                    echo '<h2>Migration Completed Successfully!</h2>';
                    echo '<p>Added: <strong>' . $addedCount . '</strong> new customer emails</p>';
                    echo '<p>Updated: <strong>' . $updatedCount . '</strong> subscriber emails to customer</p>';
                    echo '<p>Skipped: <strong>' . count($skipped) . '</strong> (already customers)</p>';
                    echo '</div>';
                    
                } catch (Exception $e) {
                    $pdo->rollBack();
                    throw $e;
                }
                
                echo '</div>';
                
            } else {
                // Dry run - show what would happen
                echo '<div class="step">';
                echo '<h2>Step 5: [DRY RUN] What would be migrated</h2>';
                
                if (count($toAdd) > 0) {
                    echo '<p><strong>Would add ' . count($toAdd) . ' new customer emails:</strong></p>';
                    echo '<div class="email-list">';
                    foreach (array_slice($toAdd, 0, 20) as $customer) {
                        echo '<div class="email-item">';
                        echo '<div class="email">' . htmlspecialchars($customer['email']) . '</div>';
                        echo '<div class="meta">Orders: ' . $customer['order_count'] . ' | Last: ' . $customer['last_order'] . '</div>';
                        echo '</div>';
                    }
                    if (count($toAdd) > 20) {
                        echo '<div class="email-item"><em>... and ' . (count($toAdd) - 20) . ' more</em></div>';
                    }
                    echo '</div>';
                }
                
                if (count($toUpdate) > 0) {
                    echo '<p style="margin-top: 1.5rem;"><strong>Would update ' . count($toUpdate) . ' subscriber emails to customer:</strong></p>';
                    echo '<div class="email-list">';
                    foreach (array_slice($toUpdate, 0, 20) as $customer) {
                        echo '<div class="email-item">';
                        echo '<div class="email">' . htmlspecialchars($customer['email']) . '</div>';
                        echo '<div class="meta">Orders: ' . $customer['order_count'] . ' | Last: ' . $customer['last_order'] . '</div>';
                        echo '</div>';
                    }
                    if (count($toUpdate) > 20) {
                        echo '<div class="email-item"><em>... and ' . (count($toUpdate) - 20) . ' more</em></div>';
                    }
                    echo '</div>';
                }
                
                echo '</div>';
            }
            
        } catch (Exception $e) {
            echo '<div class="error">';
            echo '<h2>Error!</h2>';
            echo '<p><strong>Error:</strong> ' . htmlspecialchars($e->getMessage()) . '</p>';
            echo '<p><strong>File:</strong> ' . htmlspecialchars($e->getFile()) . '</p>';
            echo '<p><strong>Line:</strong> ' . $e->getLine() . '</p>';
            echo '</div>';
        }
        ?>
        
        <div class="actions">
            <?php if ($isDryRun): ?>
                <a href="?live=1" class="btn" onclick="return confirm('Are you sure you want to perform the LIVE migration? This will modify the database.');">🚨 Run LIVE Migration</a>
            <?php endif; ?>
            <a href="backfill_customer_emails_web.php" class="btn btn-secondary">🔄 Run Again (Dry Run)</a>
            <a href="../admin/newsletter.php" class="btn btn-secondary">📧 Go to Newsletter</a>
        </div>
    </div>
</body>
</html>
