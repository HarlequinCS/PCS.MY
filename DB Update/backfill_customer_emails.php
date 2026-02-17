<?php
/**
 * Backfill Customer Emails to Newsletter List
 * 
 * This script adds all existing customer emails from paid orders
 * to the email_notifications table with source='customer'
 * 
 * SAFETY FEATURES:
 * - Dry run mode (default) - shows what would be added
 * - Skips emails that already exist
 * - Detailed logging
 * - Transaction support
 * 
 * USAGE:
 *   Dry run: php backfill_customer_emails.php
 *   Live run: php backfill_customer_emails.php --live
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
echo "BACKFILL CUSTOMER EMAILS TO NEWSLETTER\n";
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
    
    // Step 1: Add source column if it doesn't exist
    echo "Step 1: Checking for 'source' column...\n";
    $columns = $pdo->query("SHOW COLUMNS FROM email_notifications LIKE 'source'")->fetchAll();
    if (empty($columns)) {
        if ($isLiveMode) {
            $pdo->exec("ALTER TABLE email_notifications ADD COLUMN source VARCHAR(20) DEFAULT 'subscriber' AFTER status");
            echo "   ✓ Added 'source' column to email_notifications table\n";
        } else {
            echo "   [DRY RUN] Would add 'source' column to email_notifications table\n";
        }
    } else {
        echo "   ✓ 'source' column already exists\n";
    }
    echo "\n";
    
    // Step 2: Get all unique customer emails from paid orders
    echo "Step 2: Fetching customer emails from paid orders...\n";
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
    echo "   Found {$totalCustomers} unique customer emails from paid orders\n\n";
    
    if ($totalCustomers === 0) {
        echo "   No customer emails found. Nothing to migrate.\n";
        exit(0);
    }
    
    // Step 3: Check which emails already exist in newsletter list
    echo "Step 3: Checking existing newsletter emails...\n";
    $existingEmailsStmt = $pdo->query("SELECT email, source FROM email_notifications");
    $existingEmails = $existingEmailsStmt->fetchAll(PDO::FETCH_ASSOC);
    $existingEmailMap = [];
    foreach ($existingEmails as $existing) {
        $existingEmailMap[strtolower($existing['email'])] = $existing['source'] ?? 'subscriber';
    }
    
    $existingCount = count($existingEmailMap);
    echo "   Found {$existingCount} emails already in newsletter list\n\n";
    
    // Step 4: Process each customer email
    echo "Step 4: Processing customer emails...\n\n";
    
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
    echo "Summary:\n";
    echo "  - New emails to add: " . count($toAdd) . "\n";
    echo "  - Existing emails to update (subscriber → customer): " . count($toUpdate) . "\n";
    echo "  - Already customers (skipped): " . count($skipped) . "\n";
    echo "  - Total to process: " . (count($toAdd) + count($toUpdate)) . "\n\n";
    
    if (count($toAdd) === 0 && count($toUpdate) === 0) {
        echo "   ✓ All customer emails are already in the newsletter list!\n";
        exit(0);
    }
    
    // Step 5: Perform migration
    if ($isLiveMode) {
        echo "Step 5: Performing migration...\n\n";
        
        $pdo->beginTransaction();
        
        try {
            $addedCount = 0;
            $updatedCount = 0;
            $nowUTC = getCurrentDateTimeUTC();
            
            // Add new emails
            if (count($toAdd) > 0) {
                echo "  Adding " . count($toAdd) . " new customer emails...\n";
                $insertStmt = $pdo->prepare("
                    INSERT INTO email_notifications 
                    (email, ip_address, user_agent, status, source, created_at, updated_at) 
                    VALUES (?, NULL, NULL, 'active', 'customer', ?, ?)
                ");
                
                foreach ($toAdd as $customer) {
                    try {
                        $insertStmt->execute([$customer['email'], $nowUTC, $nowUTC]);
                        $addedCount++;
                        echo "    ✓ Added: {$customer['email']} (Orders: {$customer['order_count']})\n";
                    } catch (PDOException $e) {
                        if ($e->getCode() == 23000) {
                            // Duplicate entry - might have been added between check and insert
                            echo "    ⚠ Skipped duplicate: {$customer['email']}\n";
                        } else {
                            throw $e;
                        }
                    }
                }
            }
            
            // Update existing subscriber emails to customer
            if (count($toUpdate) > 0) {
                echo "\n  Updating " . count($toUpdate) . " subscriber emails to customer...\n";
                $updateStmt = $pdo->prepare("
                    UPDATE email_notifications 
                    SET source = 'customer', updated_at = ? 
                    WHERE email = ? AND (source = 'subscriber' OR source IS NULL)
                ");
                
                foreach ($toUpdate as $customer) {
                    $updateStmt->execute([$nowUTC, $customer['email']]);
                    $updatedCount++;
                    echo "    ✓ Updated: {$customer['email']} (Orders: {$customer['order_count']})\n";
                }
            }
            
            $pdo->commit();
            
            echo "\n";
            echo "========================================\n";
            echo "MIGRATION COMPLETED SUCCESSFULLY!\n";
            echo "========================================\n";
            echo "  Added: {$addedCount} new customer emails\n";
            echo "  Updated: {$updatedCount} subscriber emails to customer\n";
            echo "  Skipped: " . count($skipped) . " (already customers)\n";
            echo "\n";
            
        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
        
    } else {
        // Dry run - just show what would happen
        echo "Step 5: [DRY RUN] What would be migrated:\n\n";
        
        if (count($toAdd) > 0) {
            echo "  Would add " . count($toAdd) . " new customer emails:\n";
            foreach (array_slice($toAdd, 0, 10) as $customer) {
                echo "    - {$customer['email']} (Orders: {$customer['order_count']}, Last: {$customer['last_order']})\n";
            }
            if (count($toAdd) > 10) {
                echo "    ... and " . (count($toAdd) - 10) . " more\n";
            }
            echo "\n";
        }
        
        if (count($toUpdate) > 0) {
            echo "  Would update " . count($toUpdate) . " subscriber emails to customer:\n";
            foreach (array_slice($toUpdate, 0, 10) as $customer) {
                echo "    - {$customer['email']} (Orders: {$customer['order_count']}, Last: {$customer['last_order']})\n";
            }
            if (count($toUpdate) > 10) {
                echo "    ... and " . (count($toUpdate) - 10) . " more\n";
            }
            echo "\n";
        }
        
        echo "  To perform the actual migration, run:\n";
        echo "    php backfill_customer_emails.php --live\n\n";
    }
    
} catch (Exception $e) {
    echo "\n";
    echo "========================================\n";
    echo "ERROR!\n";
    echo "========================================\n";
    echo "Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
    echo "\n";
    exit(1);
}
