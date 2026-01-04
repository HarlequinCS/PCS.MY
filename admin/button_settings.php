<?php
/**
 * Admin Button Settings Page
 * Toggle Buy Now button behavior: Launch (redirect to order-baju.php) or Coming Soon (modal)
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

// Get current button mode
$modeStmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'button_mode' LIMIT 1");
$modeStmt->execute();
$modeResult = $modeStmt->fetch(PDO::FETCH_ASSOC);
$currentMode = ($modeResult && isset($modeResult['setting_value'])) ? $modeResult['setting_value'] : 'coming_soon';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Button Settings - Admin Panel</title>
    <link rel="stylesheet" href="/admin/css/admin.css?v=30">
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
        .mode-status.coming_soon {
            background: #ffc107;
            color: #856404;
        }
        .mode-status.launch {
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
        .toggle-btn.coming_soon {
            background: #007bff;
            color: white;
        }
        .toggle-btn.coming_soon:hover {
            background: #0056b3;
        }
        .toggle-btn.launch {
            background: #dc3545;
            color: white;
        }
        .toggle-btn.launch:hover {
            background: #c82333;
        }
        .toggle-btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
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
        .info-box {
            background: #e7f3ff;
            border: 1px solid #2196F3;
            border-radius: 6px;
            padding: 15px;
            margin-bottom: 20px;
            color: #0c5460;
        }
        .info-box strong {
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
                <h2>🔘 Buy Now Button Settings</h2>
                
                <?php if ($message): ?>
                <div class="alert alert-<?php echo $messageType; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
                <?php endif; ?>
                
                <!-- Mode Toggle Section -->
                <div class="mode-toggle-section">
                    <div class="mode-info">
                        <h3>Button Behavior</h3>
                        <p style="margin: 5px 0; color: #666;">
                            Current mode: 
                            <span class="mode-status <?php echo htmlspecialchars($currentMode); ?>">
                                <?php 
                                if ($currentMode === 'launch') {
                                    echo 'LAUNCH';
                                } else {
                                    echo 'COMING SOON';
                                }
                                ?>
                            </span>
                        </p>
                        <p style="margin: 5px 0 0 0; font-size: 14px; color: #999;">
                            <?php if ($currentMode === 'launch'): ?>
                                Buy Now buttons redirect to order page. Orders are enabled.
                            <?php else: ?>
                                Buy Now buttons show "Coming Soon" modal. Orders are disabled.
                            <?php endif; ?>
                        </p>
                    </div>
                    <button 
                        id="toggleModeBtn" 
                        class="toggle-btn <?php echo htmlspecialchars($currentMode); ?>"
                        data-current-mode="<?php echo htmlspecialchars($currentMode); ?>"
                    >
                        <?php if ($currentMode === 'coming_soon'): ?>
                            Switch to Launch
                        <?php else: ?>
                            Switch to Coming Soon
                        <?php endif; ?>
                    </button>
                </div>
                
                <div class="info-box">
                    <strong>ℹ️ About Button Modes:</strong>
                    <ul style="margin: 10px 0 0 20px; padding: 0;">
                        <li><strong>Coming Soon:</strong> Buy Now buttons open a "Coming Soon" modal with email notification form.</li>
                        <li><strong>Launch:</strong> Buy Now buttons redirect directly to the order page (order-baju.php).</li>
                    </ul>
                    <p style="margin: 10px 0 0 0;"><strong>Note:</strong> This setting is independent of payment gateway mode (staging/production).</p>
                </div>
            </div>
        </div>
    </main>
    
    <script src="/admin/js/admin.js"></script>
    <script>
        // Toggle mode functionality
        document.getElementById('toggleModeBtn').addEventListener('click', async function() {
            const btn = this;
            const currentMode = btn.dataset.currentMode;
            const newMode = currentMode === 'coming_soon' ? 'launch' : 'coming_soon';
            
            // Confirm before switching to launch
            if (newMode === 'launch') {
                const confirmed = confirm(
                    'Switch to LAUNCH mode?\n\n' +
                    'This will enable Buy Now buttons to redirect to the order page.\n\n' +
                    'Users will be able to place orders immediately.\n\n' +
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
                const response = await fetch('/admin/api/toggle_button_mode.php', {
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
                    btn.textContent = currentMode === 'coming_soon' ? 'Switch to Launch' : 'Switch to Coming Soon';
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Error switching mode. Please try again.');
                btn.disabled = false;
                btn.textContent = currentMode === 'coming_soon' ? 'Switch to Launch' : 'Switch to Coming Soon';
            }
        });
    </script>
</body>
</html>

