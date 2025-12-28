<?php
/**
 * Admin Dashboard - Comment Moderation
 */

require_once __DIR__ . '/config.php';
requireAdminLogin();

define('DB_CONFIG_LOADED', true);
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/db.php';

// Handle logout
if (isset($_GET['logout'])) {
    adminLogout();
    header('Location: /admin/');
    exit;
}

$pdo = getDBConnection();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Projek Cuti Sem</title>
    <link rel="stylesheet" href="/admin/css/admin.css?v=30">
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
</head>
<body class="admin-dashboard">
    <!-- Mobile Menu Toggle -->
    <button class="mobile-menu-toggle" id="mobileMenuToggle" aria-label="Toggle menu">☰</button>
    
    <!-- Sidebar Overlay -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    
    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <h2>PCS Admin</h2>
            <p>Admin Panel</p>
        </div>
        
        <nav class="sidebar-nav">
            <a href="/admin/dashboard.php" class="nav-item active">
                <span class="nav-icon">📝</span>
                <span>Comments</span>
            </a>
            <a href="/admin/emails.php" class="nav-item">
                <span class="nav-icon">📧</span>
                <span>Email Monitoring</span>
            </a>
            <a href="/admin/dashboard.php?logout=1" class="nav-item">
                <span class="nav-icon">🚪</span>
                <span>Logout</span>
            </a>
        </nav>
        
        <div class="sidebar-footer">
            <p>Logged in as: <strong><?php echo htmlspecialchars($_SESSION['admin_username']); ?></strong></p>
        </div>
    </aside>
    
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
    
    <script src="/admin/js/admin.js"></script>
</body>
</html>

