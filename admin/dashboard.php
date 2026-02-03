<?php
/**
 * Admin Dashboard - Comment Moderation
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Projek Cuti Sem</title>
    <link rel="stylesheet" href="<?php echo getAdminAsset('css/admin.css'); ?>">
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
</head>
<body class="admin-dashboard">
    <!-- Mobile Menu Toggle -->
    <button class="mobile-menu-toggle" id="mobileMenuToggle" aria-label="Toggle menu">☰</button>
    
    <!-- Sidebar Overlay -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    
    <!-- Dynamic Sidebar - Automatically generated from admin directory -->
    <?php echo generateAdminSidebar($currentPage); ?>
    
    <!-- Main Content -->
    <main class="main-content">
        <header class="content-header">
            <h1>Comment Moderation</h1>
            <div class="header-stats">
                <div class="stat-card pending">
                    <span class="stat-label">Pending</span>
                    <span class="stat-value" id="pendingCount">0</span>
                </div>
                <div class="stat-card approved">
                    <span class="stat-label">Approved</span>
                    <span class="stat-value" id="approvedCount">0</span>
                </div>
                <div class="stat-card total">
                    <span class="stat-label">Total</span>
                    <span class="stat-value" id="totalCount">0</span>
                </div>
            </div>
        </header>
        
        <div class="content-body">
            <!-- Filter Tabs -->
            <div class="filter-tabs">
                <button class="tab-btn active" data-filter="pending">Pending Review</button>
                <button class="tab-btn" data-filter="approved">Approved</button>
                <button class="tab-btn" data-filter="all">All Comments</button>
            </div>
            
            <!-- Comments List -->
            <div class="comments-container" id="commentsContainer">
                <div class="loading-spinner">
                    <div class="spinner"></div>
                    <p>Loading comments...</p>
                </div>
            </div>
        </div>
    </main>
    
    <script src="<?php echo getAdminAsset('js/admin.js'); ?>"></script>
</body>
</html>

