<?php
/**
 * Add email_sent Column to Orders Table
 * Web-accessible script to add email_sent column for SMTP email duplicate protection
 * 
 * SECURITY: Password protected - change the password below!
 */

// Simple password protection
session_start();
$requiredPassword = 'PCS@dmin2025!'; // Change this password!

$message = '';
$messageType = '';
$columnInfo = null;
$columnExists = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';
    $action = $_POST['action'] ?? 'check';
    
    if ($password === $requiredPassword) {
        // Database configuration
        define('DB_CONFIG_LOADED', true);
        require_once __DIR__ . '/../config/database.php';
        require_once __DIR__ . '/../includes/db.php';
        
        try {
            $pdo = getDBConnection();
            
            // Check if column already exists
            $checkStmt = $pdo->query("
                SELECT COLUMN_NAME, DATA_TYPE, IS_NULLABLE, COLUMN_DEFAULT, COLUMN_COMMENT 
                FROM INFORMATION_SCHEMA.COLUMNS 
                WHERE TABLE_SCHEMA = DATABASE() 
                AND TABLE_NAME = 'orders' 
                AND COLUMN_NAME = 'email_sent'
            ");
            $columnInfo = $checkStmt->fetch(PDO::FETCH_ASSOC);
            $columnExists = !empty($columnInfo);
            
            if ($action === 'add' && !$columnExists) {
                // Begin transaction
                $pdo->beginTransaction();
                
                try {
                    // Add email_sent column
                    $pdo->exec("
                        ALTER TABLE `orders` 
                        ADD COLUMN `email_sent` TINYINT(1) NOT NULL DEFAULT 0 
                        COMMENT 'Email sent flag: 0 = not sent, 1 = sent' 
                        AFTER `status`
                    ");
                    
                    // Add index for better query performance
                    $pdo->exec("
                        ALTER TABLE `orders`
                        ADD INDEX `idx_email_sent` (`email_sent`)
                    ");
                    
                    $pdo->commit();
                    
                    $message = "Successfully added 'email_sent' column and index to orders table!";
                    $messageType = 'success';
                    
                    // Refresh column info
                    $checkStmt = $pdo->query("
                        SELECT COLUMN_NAME, DATA_TYPE, IS_NULLABLE, COLUMN_DEFAULT, COLUMN_COMMENT 
                        FROM INFORMATION_SCHEMA.COLUMNS 
                        WHERE TABLE_SCHEMA = DATABASE() 
                        AND TABLE_NAME = 'orders' 
                        AND COLUMN_NAME = 'email_sent'
                    ");
                    $columnInfo = $checkStmt->fetch(PDO::FETCH_ASSOC);
                    $columnExists = true;
                    
                } catch (PDOException $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    throw $e;
                }
                
            } elseif ($action === 'add' && $columnExists) {
                $message = "Column 'email_sent' already exists. No changes made.";
                $messageType = 'info';
            } else {
                // Just checking status
                if ($columnExists) {
                    $message = "Column 'email_sent' already exists in orders table.";
                    $messageType = 'info';
                } else {
                    $message = "Column 'email_sent' does not exist. Click 'Add Column' to create it.";
                    $messageType = 'warning';
                }
            }
            
        } catch (PDOException $e) {
            $message = "ERROR: " . $e->getMessage();
            $messageType = 'error';
            error_log("Database update error: " . $e->getMessage());
        }
    } else {
        $message = 'Invalid password!';
        $messageType = 'error';
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add email_sent Column - Database Update</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            max-width: 700px;
            width: 100%;
            padding: 40px;
        }
        h1 {
            color: #333;
            margin-bottom: 10px;
            font-size: 28px;
        }
        .subtitle {
            color: #666;
            margin-bottom: 30px;
            font-size: 14px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 500;
        }
        input[type="password"] {
            width: 100%;
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
            transition: border-color 0.3s;
        }
        input[type="password"]:focus {
            outline: none;
            border-color: #667eea;
        }
        .button-group {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }
        button {
            flex: 1;
            padding: 12px 24px;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
        }
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        .btn-secondary {
            background: #f0f0f0;
            color: #333;
        }
        .btn-secondary:hover {
            background: #e0e0e0;
        }
        .message {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            font-weight: 500;
        }
        .message.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .message.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .message.info {
            background: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }
        .message.warning {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }
        .column-info {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            padding: 20px;
            margin-top: 20px;
        }
        .column-info h3 {
            margin-bottom: 15px;
            color: #333;
        }
        .info-row {
            display: flex;
            padding: 8px 0;
            border-bottom: 1px solid #dee2e6;
        }
        .info-row:last-child {
            border-bottom: none;
        }
        .info-label {
            font-weight: 600;
            width: 150px;
            color: #555;
        }
        .info-value {
            color: #333;
        }
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }
        .status-exists {
            background: #d4edda;
            color: #155724;
        }
        .status-not-exists {
            background: #f8d7da;
            color: #721c24;
        }
        .security-warning {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 5px;
            padding: 15px;
            margin-top: 20px;
            color: #856404;
            font-size: 14px;
        }
        .security-warning strong {
            display: block;
            margin-bottom: 5px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Add email_sent Column</h1>
        <p class="subtitle">Database Update Script for SMTP Email Duplicate Protection</p>
        
        <?php if ($message): ?>
        <div class="message <?php echo htmlspecialchars($messageType); ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="form-group">
                <label for="password">Password:</label>
                <input type="password" id="password" name="password" required autofocus>
            </div>
            
            <div class="button-group">
                <button type="submit" name="action" value="check" class="btn-secondary">
                    Check Status
                </button>
                <button type="submit" name="action" value="add" class="btn-primary">
                    Add Column
                </button>
            </div>
        </form>
        
        <?php if (isset($columnInfo) && $columnExists): ?>
        <div class="column-info">
            <h3>Column Status: <span class="status-badge status-exists">EXISTS</span></h3>
            <div class="info-row">
                <span class="info-label">Column Name:</span>
                <span class="info-value"><?php echo htmlspecialchars($columnInfo['COLUMN_NAME']); ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Data Type:</span>
                <span class="info-value"><?php echo htmlspecialchars($columnInfo['DATA_TYPE']); ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Nullable:</span>
                <span class="info-value"><?php echo htmlspecialchars($columnInfo['IS_NULLABLE']); ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Default Value:</span>
                <span class="info-value"><?php echo htmlspecialchars($columnInfo['COLUMN_DEFAULT'] ?? 'NULL'); ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Comment:</span>
                <span class="info-value"><?php echo htmlspecialchars($columnInfo['COLUMN_COMMENT'] ?? 'N/A'); ?></span>
            </div>
        </div>
        <?php elseif (isset($columnExists) && !$columnExists && $_SERVER['REQUEST_METHOD'] === 'POST'): ?>
        <div class="column-info">
            <h3>Column Status: <span class="status-badge status-not-exists">NOT EXISTS</span></h3>
            <p style="margin-top: 10px; color: #666;">Click "Add Column" to create the email_sent column.</p>
        </div>
        <?php endif; ?>
        
        <div class="security-warning">
            <strong>⚠️ Security Warning:</strong>
            This script modifies your database. Delete this file after running it, or restrict access via .htaccess or move it to a protected directory.
        </div>
    </div>
</body>
</html>

