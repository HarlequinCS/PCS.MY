<?php
/**
 * Admin Dashboard - Email Monitoring
 * Display all registered email addresses for notifications
 */

require_once __DIR__ . '/config.php';
requireAdminLogin();

// Handle logout
if (isset($_GET['logout'])) {
    adminLogout();
    header('Location: /admin/');
    exit;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Monitoring - Admin Dashboard - Projek Cuti Sem</title>
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
            <a href="/admin/dashboard.php" class="nav-item">
                <span class="nav-icon">📝</span>
                <span>Comments</span>
            </a>
            <a href="/admin/emails.php" class="nav-item active">
                <span class="nav-icon">📧</span>
                <span>Email Monitoring</span>
            </a>
            <a href="/admin/emails.php?logout=1" class="nav-item">
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
            <h1>Email Monitoring</h1>
            <div class="header-stats">
                <div class="stat-card total">
                    <span class="stat-label">Total Emails</span>
                    <span class="stat-value" id="totalCount">0</span>
                </div>
                <div class="stat-card approved">
                    <span class="stat-label">Active</span>
                    <span class="stat-value" id="activeCount">0</span>
                </div>
                <div class="stat-card pending" style="border-left-color: #6b7280;">
                    <span class="stat-label">Inactive</span>
                    <span class="stat-value" id="inactiveCount">0</span>
                </div>
            </div>
        </header>
        
        <div class="content-body">
            <!-- Search and Filter Section -->
            <div class="email-filters">
                <div class="search-box">
                    <input type="text" id="emailSearch" placeholder="Search emails..." class="search-input">
                    <span class="search-icon">🔍</span>
                </div>
                <div class="filter-buttons">
                    <button class="filter-btn active" data-filter="all">All</button>
                    <button class="filter-btn" data-filter="active">Active</button>
                    <button class="filter-btn" data-filter="inactive">Inactive</button>
                </div>
            </div>
            
            <!-- Emails List -->
            <div class="emails-container" id="emailsContainer">
                <div class="loading-spinner">
                    <div class="spinner"></div>
                    <p>Loading emails...</p>
                </div>
            </div>
        </div>
    </main>
    
    <script>
        // Mobile menu toggle
        const mobileMenuToggle = document.getElementById('mobileMenuToggle');
        const sidebar = document.getElementById('sidebar');
        const sidebarOverlay = document.getElementById('sidebarOverlay');
        
        function toggleSidebar() {
            sidebar.classList.toggle('active');
            sidebarOverlay.classList.toggle('active');
            mobileMenuToggle.classList.toggle('active');
        }
        
        if (mobileMenuToggle) {
            mobileMenuToggle.addEventListener('click', toggleSidebar);
        }
        
        if (sidebarOverlay) {
            sidebarOverlay.addEventListener('click', toggleSidebar);
        }
        
        // Load emails
        async function loadEmails() {
            try {
                const response = await fetch('/admin/api/get_emails.php');
                const data = await response.json();
                
                if (data.success) {
                    displayEmails(data.emails);
                    updateStats(data.total_active, data.total_all);
                } else {
                    showError('Failed to load emails');
                }
            } catch (error) {
                console.error('Error loading emails:', error);
                showError('Failed to load emails. Please try again.');
            }
        }
        
        function updateStats(activeCount, totalCount) {
            document.getElementById('activeCount').textContent = activeCount;
            document.getElementById('totalCount').textContent = totalCount;
            document.getElementById('inactiveCount').textContent = totalCount - activeCount;
        }
        
        function displayEmails(emails) {
            const container = document.getElementById('emailsContainer');
            
            if (emails.length === 0) {
                container.innerHTML = `
                    <div class="empty-state">
                        <div class="empty-state-icon">📭</div>
                        <h3>No Emails Found</h3>
                        <p>No email addresses have been registered yet.</p>
                    </div>
                `;
                return;
            }
            
            container.innerHTML = emails.map(email => `
                <div class="email-card ${email.status}" data-status="${email.status}" data-email="${email.email.toLowerCase()}">
                    <div class="email-header">
                        <div class="email-address">
                            <span class="email-icon">✉️</span>
                            <a href="mailto:${email.email}" class="email-link">${email.email}</a>
                        </div>
                        <span class="email-status status-${email.status}">${email.status === 'active' ? 'Active' : 'Inactive'}</span>
                    </div>
                    <div class="email-meta">
                        <div class="meta-item">
                            <span class="meta-label">ID:</span>
                            <span class="meta-value">#${email.notification_id}</span>
                        </div>
                        <div class="meta-item">
                            <span class="meta-label">Registered:</span>
                            <span class="meta-value">${email.created_at_formatted}</span>
                        </div>
                        ${email.updated_at !== email.created_at ? `
                        <div class="meta-item">
                            <span class="meta-label">Updated:</span>
                            <span class="meta-value">${email.updated_at_formatted}</span>
                        </div>
                        ` : ''}
                        ${email.ip_address ? `
                        <div class="meta-item">
                            <span class="meta-label">IP:</span>
                            <span class="meta-value">${email.ip_address}</span>
                        </div>
                        ` : ''}
                    </div>
                </div>
            `).join('');
        }
        
        function showError(message) {
            const container = document.getElementById('emailsContainer');
            container.innerHTML = `
                <div class="empty-state">
                    <div class="empty-state-icon">⚠️</div>
                    <h3>Error</h3>
                    <p>${message}</p>
                </div>
            `;
        }
        
        // Search functionality
        const searchInput = document.getElementById('emailSearch');
        if (searchInput) {
            searchInput.addEventListener('input', (e) => {
                const searchTerm = e.target.value.toLowerCase();
                const emailCards = document.querySelectorAll('.email-card');
                
                emailCards.forEach(card => {
                    const email = card.getAttribute('data-email');
                    if (email.includes(searchTerm)) {
                        card.style.display = '';
                    } else {
                        card.style.display = 'none';
                    }
                });
            });
        }
        
        // Filter functionality
        const filterButtons = document.querySelectorAll('.filter-btn');
        filterButtons.forEach(btn => {
            btn.addEventListener('click', () => {
                // Update active state
                filterButtons.forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                
                // Filter emails
                const filter = btn.getAttribute('data-filter');
                const emailCards = document.querySelectorAll('.email-card');
                
                emailCards.forEach(card => {
                    const status = card.getAttribute('data-status');
                    if (filter === 'all') {
                        card.style.display = '';
                    } else if (filter === status) {
                        card.style.display = '';
                    } else {
                        card.style.display = 'none';
                    }
                });
            });
        });
        
        // Load emails on page load
        loadEmails();
    </script>
</body>
</html>

