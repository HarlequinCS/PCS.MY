<?php
/**
 * Admin Settings Page - ToyyibPay Configuration
 * Allows toggling between staging and production ToyyibPay API
 */

require_once __DIR__ . '/config.php';
requireAdminLogin();

define('DB_CONFIG_LOADED', true);
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/sidebar_helper.php';

// Handle logout
if (isset($_GET['logout'])) {
    adminLogout();
    header('Location: /admin/');
    exit;
}

$pdo = getDBConnection();
$currentPage = basename(__FILE__);
$message = '';
$messageType = '';

// Production credentials are hardcoded in includes/toyyibpay_config.php
// No database storage needed - credentials form removed

// Get current mode
$modeStmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'toyyibpay_mode' LIMIT 1");
$modeStmt->execute();
$modeResult = $modeStmt->fetch(PDO::FETCH_ASSOC);
$currentMode = ($modeResult && isset($modeResult['setting_value'])) ? $modeResult['setting_value'] : 'staging';

// Production credentials are hardcoded in includes/toyyibpay_config.php
// Database storage is optional/fallback only
$productionSecret = '58bq974i-wcu2-ypkz-4mbn-cvjuxtpil8kr'; // Hardcoded in config file
$productionCategory = 'lfhcsj8w'; // Hardcoded in config file

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - ToyyibPay Configuration | Admin Panel</title>
    <link rel="stylesheet" href="<?php echo getAdminAsset('css/admin.css'); ?>">
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <style>
        .settings-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 30px 20px;
        }
        .settings-card {
            background: white;
            border-radius: 8px;
            padding: 30px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .settings-card h2 {
            margin: 0 0 20px 0;
            color: #333;
            font-size: 24px;
        }
        .mode-toggle-section {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 8px;
            margin-bottom: 30px;
        }
        .mode-info {
            flex: 1;
        }
        .mode-info h3 {
            margin: 0 0 8px 0;
            color: #333;
            font-size: 18px;
        }
        .mode-status {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 14px;
            font-weight: 600;
            margin-top: 5px;
        }
        .mode-status.staging {
            background: #ffc107;
            color: #856404;
        }
        .mode-status.production {
            background: #28a745;
            color: #155724;
        }
        .toggle-btn {
            padding: 12px 24px;
            border: none;
            border-radius: 6px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }
        .toggle-btn.staging {
            background: #007bff;
            color: white;
        }
        .toggle-btn.staging:hover {
            background: #0056b3;
        }
        .toggle-btn.production {
            background: #dc3545;
            color: white;
        }
        .toggle-btn.production:hover {
            background: #c82333;
        }
        .toggle-btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
        .credentials-form {
            margin-top: 30px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
        }
        .form-group input {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            box-sizing: border-box;
        }
        .form-group input:focus {
            outline: none;
            border-color: #007bff;
        }
        .form-help {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }
        .btn-save {
            padding: 12px 24px;
            background: #28a745;
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
        }
        .btn-save:hover {
            background: #218838;
        }
        .alert {
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
        }
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .warning-box {
            background: #fff3cd;
            border: 1px solid #ffc107;
            border-radius: 6px;
            padding: 15px;
            margin-bottom: 20px;
            color: #856404;
        }
        .warning-box strong {
            display: block;
            margin-bottom: 5px;
        }
    </style>
</head>
<body class="admin-dashboard">
    <!-- Mobile Menu Toggle -->
    <button class="mobile-menu-toggle" id="mobileMenuToggle" aria-label="Toggle menu">☰</button>
    
    <!-- Sidebar Overlay -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    
    <!-- Dynamic Sidebar - Automatically generated from admin directory -->
    <?php echo generateAdminSidebar($currentPage); ?>
    
    <main class="main-content">
        <div class="settings-container">
            <div class="settings-card">
                <h2>⚙️ ToyyibPay Configuration</h2>
                
                <?php if ($message): ?>
                <div class="alert alert-<?php echo $messageType; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
                <?php endif; ?>
                
                <!-- Mode Toggle Section -->
                <div class="mode-toggle-section">
                    <div class="mode-info">
                        <h3>Payment Gateway Mode</h3>
                        <p style="margin: 5px 0; color: #666;">
                            Current mode: 
                            <span class="mode-status <?php echo htmlspecialchars($currentMode); ?>">
                                <?php echo strtoupper(htmlspecialchars($currentMode)); ?>
                            </span>
                        </p>
                        <p style="margin: 5px 0 0 0; font-size: 14px; color: #999;">
                            <?php if ($currentMode === 'staging'): ?>
                                Using staging/test environment. Payments are not real.
                            <?php else: ?>
                                Using production environment. Payments are REAL and will be processed.
                            <?php endif; ?>
                        </p>
                    </div>
                    <button 
                        id="toggleModeBtn" 
                        class="toggle-btn <?php echo htmlspecialchars($currentMode); ?>"
                        data-current-mode="<?php echo htmlspecialchars($currentMode); ?>"
                    >
                        <?php if ($currentMode === 'staging'): ?>
                            Switch to Production
                        <?php else: ?>
                            Switch to Staging
                        <?php endif; ?>
                    </button>
                </div>
                
                <?php if ($currentMode === 'production'): ?>
                <div class="warning-box">
                    <strong>⚠️ Production Mode Active</strong>
                    You are currently using the production ToyyibPay API. All payments will be REAL and processed immediately. Use with caution.
                </div>
                <?php endif; ?>
                
                <!-- Production Credentials Info -->
                <div class="credentials-form">
                    <h3 style="margin: 0 0 20px 0; color: #333;">Production Credentials</h3>
                    <div class="warning-box" style="background: #e7f3ff; border: 1px solid #2196F3; color: #0c5460; margin-bottom: 20px;">
                        <strong>ℹ️ Credentials Location:</strong>
                        Production credentials are hardcoded in <code>includes/toyyibpay_config.php</code>.
                        <br><br>
                        <strong>Current Values:</strong><br>
                        Secret Key: <code><?php echo htmlspecialchars($productionSecret); ?></code><br>
                        Category Code: <code><?php echo htmlspecialchars($productionCategory); ?></code>
                        <br><br>
                        To update credentials, edit the file directly: <code>includes/toyyibpay_config.php</code>
                    </div>
                </div>
            </div>
        </div>
    </main>
    
    <script src="<?php echo getAdminAsset('js/admin.js'); ?>"></script>
    <script>
        // Toggle mode functionality
        document.getElementById('toggleModeBtn').addEventListener('click', async function() {
            const btn = this;
            const currentMode = btn.dataset.currentMode;
            const newMode = currentMode === 'staging' ? 'production' : 'staging';
            
            // Confirm before switching to production
            if (newMode === 'production') {
                const confirmed = confirm(
                    '⚠️ WARNING: You are about to switch to PRODUCTION mode.\n\n' +
                    'This will enable REAL payments. All transactions will be processed immediately.\n\n' +
                    'Make sure you have:\n' +
                    '1. Entered correct production credentials\n' +
                    '2. Tested the integration thoroughly\n' +
                    '3. Backed up your database\n\n' +
                    'Do you want to continue?'
                );
                
                if (!confirmed) {
                    return;
                }
            }
            
            // Disable button during request
            btn.disabled = true;
            btn.textContent = 'Switching...';
            
            try {
                const response = await fetch('/admin/api/toggle_toyyibpay_mode.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    }
                });
                
                const data = await response.json();
                
                if (data.success) {
                    // Reload page to show updated mode
                    window.location.reload();
                } else {
                    alert('Error: ' + (data.error || 'Failed to switch mode'));
                    btn.disabled = false;
                    btn.textContent = currentMode === 'staging' ? 'Switch to Production' : 'Switch to Staging';
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Error switching mode. Please try again.');
                btn.disabled = false;
                btn.textContent = currentMode === 'staging' ? 'Switch to Production' : 'Switch to Staging';
            }
        });
    </script>
</body>
</html>

