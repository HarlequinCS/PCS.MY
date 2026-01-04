<?php
/**
 * Create Settings Table for ToyyibPay Mode Toggle
 * Web-accessible script to create the settings table
 * 
 * SECURITY: Password protected - change the password below!
 */

// Simple password protection
session_start();
$requiredPassword = 'PCS@dmin2025!'; // Change this password!

$message = '';
$messageType = '';
$tableExists = false;

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
            
            // Check if table exists
            $checkStmt = $pdo->query("SHOW TABLES LIKE 'settings'");
            $tableExists = $checkStmt->rowCount() > 0;
            
            if ($action === 'create' && !$tableExists) {
                // Create settings table
                $pdo->exec("
                    CREATE TABLE IF NOT EXISTS `settings` (
                        `id` INT(11) NOT NULL AUTO_INCREMENT,
                        `setting_key` VARCHAR(100) NOT NULL UNIQUE COMMENT 'Setting key',
                        `setting_value` TEXT NULL COMMENT 'Setting value',
                        `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                        `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                        PRIMARY KEY (`id`),
                        UNIQUE KEY `setting_key` (`setting_key`),
                        INDEX `idx_setting_key` (`setting_key`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='System settings table'
                ");
                
                // Insert default settings
                $pdo->exec("
                    INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES
                    ('toyyibpay_mode', 'staging'),
                    ('toyyibpay_production_secret_key', ''),
                    ('toyyibpay_production_category_code', '')
                    ON DUPLICATE KEY UPDATE `setting_key` = `setting_key`
                ");
                
                $message = "Settings table created successfully!";
                $messageType = 'success';
                $tableExists = true;
                
            } elseif ($action === 'create' && $tableExists) {
                $message = "Settings table already exists. No changes made.";
                $messageType = 'info';
            } else {
                if ($tableExists) {
                    $message = "Settings table exists.";
                    $messageType = 'info';
                } else {
                    $message = "Settings table does not exist. Click 'Create Table' to create it.";
                    $messageType = 'warning';
                }
            }
            
        } catch (PDOException $e) {
            $message = "ERROR: " . $e->getMessage();
            $messageType = 'error';
            error_log("Database error: " . $e->getMessage());
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
    <title>Create Settings Table - Database Update</title>
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
            max-width: 600px;
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
        <h1>Create Settings Table</h1>
        <p class="subtitle">Database Update Script for ToyyibPay Mode Toggle</p>
        
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
                <button type="submit" name="action" value="create" class="btn-primary">
                    Create Table
                </button>
            </div>
        </form>
        
        <?php if ($tableExists && $_SERVER['REQUEST_METHOD'] === 'POST'): ?>
        <div class="message info" style="margin-top: 20px;">
            ✓ Settings table exists. You can now use the ToyyibPay mode toggle in the admin panel.
        </div>
        <?php endif; ?>
        
        <div class="security-warning">
            <strong>⚠️ Security Warning:</strong>
            This script modifies your database. Delete this file after running it, or restrict access via .htaccess or move it to a protected directory.
        </div>
    </div>
</body>
</html>

