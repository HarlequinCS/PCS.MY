<?php
/**
 * Setup Button Settings
 * Initializes button_mode and button_link in the database
 * Run this once to set up the button configuration
 */

// Define database config constant
define('DB_CONFIG_LOADED', true);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/db.php';

// Prevent direct access in production (optional - remove this check if you want to run it directly)
// Uncomment the lines below to secure this script:
/*
if ($_SERVER['HTTP_HOST'] !== 'localhost' && $_SERVER['HTTP_HOST'] !== '127.0.0.1') {
    die('This script can only be run from localhost');
}
*/

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup Button Settings</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            margin-top: 0;
        }
        .success {
            background: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 6px;
            margin: 15px 0;
            border: 1px solid #c3e6cb;
        }
        .error {
            background: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 6px;
            margin: 15px 0;
            border: 1px solid #f5c6cb;
        }
        .info {
            background: #d1ecf1;
            color: #0c5460;
            padding: 15px;
            border-radius: 6px;
            margin: 15px 0;
            border: 1px solid #bee5eb;
        }
        .btn {
            display: inline-block;
            padding: 12px 24px;
            background: #007bff;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            margin-top: 15px;
            border: none;
            cursor: pointer;
            font-size: 16px;
        }
        .btn:hover {
            background: #0056b3;
        }
        .btn-success {
            background: #28a745;
        }
        .btn-success:hover {
            background: #218838;
        }
        code {
            background: #f4f4f4;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: monospace;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background: #f8f9fa;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 Setup Button Settings</h1>
        
        <?php
        $errors = [];
        $success = [];
        
        try {
            $pdo = getDBConnection();
            
            // Check if settings table exists
            $tableCheck = $pdo->query("SHOW TABLES LIKE 'settings'");
            if ($tableCheck->rowCount() === 0) {
                // Create settings table if it doesn't exist
                $pdo->exec("
                    CREATE TABLE IF NOT EXISTS `settings` (
                        `setting_key` VARCHAR(100) NOT NULL PRIMARY KEY,
                        `setting_value` TEXT NOT NULL,
                        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
                ");
                $success[] = "Settings table created successfully";
            } else {
                $success[] = "Settings table already exists";
            }
            
            // Get current values
            $currentMode = 'coming_soon';
            $currentLink = '/order-baju.php';
            
            $modeStmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'button_mode' LIMIT 1");
            $modeStmt->execute();
            $modeResult = $modeStmt->fetch(PDO::FETCH_ASSOC);
            if ($modeResult) {
                $currentMode = $modeResult['setting_value'];
            }
            
            $linkStmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'button_link' LIMIT 1");
            $linkStmt->execute();
            $linkResult = $linkStmt->fetch(PDO::FETCH_ASSOC);
            if ($linkResult && !empty($linkResult['setting_value'])) {
                $currentLink = $linkResult['setting_value'];
            }
            
            // Handle form submission
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $newMode = $_POST['button_mode'] ?? 'coming_soon';
                $newLink = trim($_POST['button_link'] ?? '/order-baju.php');
                
                // Validate mode
                if (!in_array($newMode, ['coming_soon', 'launch'])) {
                    $errors[] = "Invalid button mode. Must be 'coming_soon' or 'launch'";
                } else {
                    // Save button mode
                    $stmt = $pdo->prepare("
                        INSERT INTO settings (setting_key, setting_value) 
                        VALUES ('button_mode', :mode)
                        ON DUPLICATE KEY UPDATE setting_value = :mode2
                    ");
                    $stmt->execute([':mode' => $newMode, ':mode2' => $newMode]);
                    $success[] = "Button mode set to: <code>{$newMode}</code>";
                    $currentMode = $newMode;
                }
                
                // Validate and save button link
                if (empty($newLink)) {
                    $newLink = '/order-baju.php';
                }
                
                // Ensure link starts with /
                if ($newLink[0] !== '/') {
                    $newLink = '/' . $newLink;
                }
                
                $stmt = $pdo->prepare("
                    INSERT INTO settings (setting_key, setting_value) 
                    VALUES ('button_link', :link)
                    ON DUPLICATE KEY UPDATE setting_value = :link2
                ");
                $stmt->execute([':link' => $newLink, ':link2' => $newLink]);
                $success[] = "Button link set to: <code>{$newLink}</code>";
                $currentLink = $newLink;
            }
            
            // Display current settings
            echo '<div class="info">';
            echo '<strong>Current Settings:</strong><br>';
            echo 'Button Mode: <code>' . htmlspecialchars($currentMode) . '</code><br>';
            echo 'Button Link: <code>' . htmlspecialchars($currentLink) . '</code>';
            echo '</div>';
            
            // Display success messages
            if (!empty($success)) {
                foreach ($success as $msg) {
                    echo '<div class="success">✓ ' . $msg . '</div>';
                }
            }
            
            // Display errors
            if (!empty($errors)) {
                foreach ($errors as $error) {
                    echo '<div class="error">✗ ' . htmlspecialchars($error) . '</div>';
                }
            }
            
        } catch (Exception $e) {
            echo '<div class="error">';
            echo '<strong>Error:</strong> ' . htmlspecialchars($e->getMessage());
            echo '</div>';
        }
        ?>
        
        <h2>Configure Button Settings</h2>
        
        <form method="POST">
            <table>
                <tr>
                    <th>Setting</th>
                    <th>Value</th>
                    <th>Description</th>
                </tr>
                <tr>
                    <td><strong>Button Mode</strong></td>
                    <td>
                        <select name="button_mode" style="width: 100%; padding: 8px;">
                            <option value="coming_soon" <?php echo ($currentMode === 'coming_soon') ? 'selected' : ''; ?>>Coming Soon</option>
                            <option value="launch" <?php echo ($currentMode === 'launch') ? 'selected' : ''; ?>>Launch</option>
                        </select>
                    </td>
                    <td>
                        <strong>Coming Soon:</strong> Buttons show "Coming Soon" modal<br>
                        <strong>Launch:</strong> Buttons redirect to order page
                    </td>
                </tr>
                <tr>
                    <td><strong>Button Link</strong></td>
                    <td>
                        <input type="text" name="button_link" value="<?php echo htmlspecialchars($currentLink); ?>" 
                               style="width: 100%; padding: 8px;" placeholder="/order-baju.php">
                    </td>
                    <td>
                        Target page when button is clicked<br>
                        (e.g., <code>/order-baju.php</code>, <code>/checkout.php</code>)
                    </td>
                </tr>
            </table>
            
            <button type="submit" class="btn btn-success">Save Settings</button>
        </form>
        
        <div class="info" style="margin-top: 30px;">
            <strong>📝 Important Notes:</strong>
            <ul>
                <li>Set <strong>Button Mode</strong> to <code>launch</code> to enable button redirects</li>
                <li>Set <strong>Button Link</strong> to the page where users should be redirected</li>
                <li>After saving, refresh your homepage to see the changes</li>
                <li>You can also configure these settings in the <a href="/admin/button_settings.php">Admin Panel</a></li>
            </ul>
        </div>
        
        <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd;">
            <a href="/" class="btn">← Back to Homepage</a>
            <a href="/admin/button_settings.php" class="btn">Go to Admin Panel</a>
        </div>
    </div>
</body>
</html>

