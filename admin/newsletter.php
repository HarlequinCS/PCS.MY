<?php
/**
 * Admin Newsletter - Email Campaign Panel
 * Professional emailing platform to send notifications to registered users
 */

require_once __DIR__ . '/config.php';
requireAdminLogin();

define('DB_CONFIG_LOADED', true);
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/email.php';
require_once __DIR__ . '/sidebar_helper.php';

// Handle logout
if (isset($_GET['logout'])) {
    adminLogout();
    header('Location: /admin/');
    exit;
}

$pdo = getDBConnection();
$currentPage = basename(__FILE__);

// Get email statistics
$stats = [
    'total' => 0,
    'active' => 0,
    'inactive' => 0
];

try {
    $totalStmt = $pdo->query("SELECT COUNT(*) as total FROM email_notifications");
    $stats['total'] = (int)$totalStmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    $activeStmt = $pdo->query("SELECT COUNT(*) as total FROM email_notifications WHERE status = 'active'");
    $stats['active'] = (int)$activeStmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    $stats['inactive'] = $stats['total'] - $stats['active'];
} catch (PDOException $e) {
    error_log("Stats error: " . $e->getMessage());
}

// Handle form submission
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'send_campaign') {
    $subject = trim($_POST['subject'] ?? '');
    $body = trim($_POST['body'] ?? '');
    $recipientType = $_POST['recipient_type'] ?? 'active';
    $testEmail = trim($_POST['test_email'] ?? '');
    $testOnly = isset($_POST['test_only']);
    
    // Validation
    if (empty($subject)) {
        $message = 'Subject is required';
        $messageType = 'error';
    } elseif (empty($body)) {
        $message = 'Email body is required';
        $messageType = 'error';
    } else {
        // If test email is provided or test_only is set, send to test email only
        if ($testOnly || !empty($testEmail)) {
            if (empty($testEmail)) {
                $message = 'Test email address is required';
                $messageType = 'error';
            } elseif (!filter_var($testEmail, FILTER_VALIDATE_EMAIL)) {
                $message = 'Invalid test email address';
                $messageType = 'error';
            } else {
                // Send test email
                $testBody = "<div style='background: #fff3cd; padding: 20px; border: 2px solid #ffc107; border-radius: 8px; margin-bottom: 20px;'><strong>🧪 TEST EMAIL</strong><br>This is a test email. The actual campaign will be sent to all recipients.</div>" . $body;
                
                $result = sendSMTPEmail($testEmail, "[TEST] " . $subject, $testBody);
                
                if ($result) {
                    $message = "Test email sent successfully to {$testEmail}";
                    $messageType = 'success';
                } else {
                    $message = "Failed to send test email. Please check SMTP configuration.";
                    $messageType = 'error';
                }
            }
        } else {
            // Send to all recipients
            try {
                $query = "SELECT email FROM email_notifications WHERE status = 'active'";
                if ($recipientType === 'all') {
                    $query = "SELECT email FROM email_notifications";
                }
                
                $stmt = $pdo->query($query);
                $recipients = $stmt->fetchAll(PDO::FETCH_COLUMN);
                
                if (empty($recipients)) {
                    $message = 'No recipients found';
                    $messageType = 'error';
                } else {
                    $successCount = 0;
                    $failCount = 0;
                    
                    foreach ($recipients as $email) {
                        $result = sendSMTPEmail($email, $subject, $body);
                        if ($result) {
                            $successCount++;
                        } else {
                            $failCount++;
                        }
                        
                        // Small delay to avoid overwhelming SMTP server
                        usleep(100000); // 0.1 second delay
                    }
                    
                    $message = "Campaign sent! Success: {$successCount}, Failed: {$failCount}";
                    $messageType = 'success';
                }
            } catch (PDOException $e) {
                error_log("Campaign send error: " . $e->getMessage());
                $message = "Error sending campaign: " . $e->getMessage();
                $messageType = 'error';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Newsletter - Admin Dashboard - Projek Cuti Sem</title>
    <link rel="stylesheet" href="/admin/css/admin.css?v=30">
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <style>
        .newsletter-form {
            background: white;
            border-radius: 12px;
            padding: 2rem;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }
        
        .form-section {
            margin-bottom: 2rem;
        }
        
        .form-section:last-child {
            margin-bottom: 0;
        }
        
        .form-section h3 {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 1rem;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .recipient-selector {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }
        
        .recipient-option {
            flex: 1;
            min-width: 150px;
        }
        
        .recipient-option input[type="radio"] {
            display: none;
        }
        
        .recipient-option label {
            display: block;
            padding: 1rem;
            border: 2px solid var(--border-color);
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            text-align: center;
            background: white;
        }
        
        .recipient-option input[type="radio"]:checked + label {
            border-color: var(--primary-color);
            background: rgba(255, 0, 0, 0.05);
            color: var(--primary-color);
            font-weight: 600;
        }
        
        .recipient-option label:hover {
            border-color: var(--primary-color);
        }
        
        .recipient-count {
            display: block;
            font-size: 0.875rem;
            color: var(--text-secondary);
            margin-top: 0.25rem;
        }
        
        .test-email-section {
            background: #fff3cd;
            border: 1px solid #ffc107;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .test-email-section h4 {
            margin: 0 0 0.5rem 0;
            color: #856404;
            font-size: 0.9375rem;
        }
        
        .test-email-section p {
            margin: 0 0 0.75rem 0;
            font-size: 0.875rem;
            color: #856404;
        }
        
        .test-email-input {
            display: flex;
            gap: 0.5rem;
        }
        
        .test-email-input input {
            flex: 1;
        }
        
        .email-preview {
            background: #f8f9fa;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 1.5rem;
            margin-top: 1rem;
            max-height: 400px;
            overflow-y: auto;
        }
        
        .email-preview h4 {
            margin: 0 0 1rem 0;
            font-size: 0.9375rem;
            color: var(--text-secondary);
        }
        
        .email-preview-content {
            background: white;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            padding: 1rem;
            font-size: 0.875rem;
            line-height: 1.6;
        }
        
        .button-group {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
        }
        
        .btn-test {
            background: #ffc107;
            color: #000;
        }
        
        .btn-test:hover {
            background: #e0a800;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: white;
            border-radius: 8px;
            padding: 1.5rem;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        
        .stat-card h3 {
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--text-secondary);
            margin: 0 0 0.5rem 0;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        
        .stat-card .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary-color);
        }
    </style>
</head>
<body class="admin-dashboard">
    <!-- Mobile Menu Toggle -->
    <button class="mobile-menu-toggle" id="mobileMenuToggle" aria-label="Toggle menu">☰</button>
    
    <!-- Sidebar Overlay -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    
    <!-- Dynamic Sidebar -->
    <?php echo generateAdminSidebar($currentPage); ?>
    
    <!-- Main Content -->
    <main class="main-content">
        <header class="content-header">
            <h1>📮 Newsletter Campaign</h1>
            <p style="color: var(--text-secondary); margin-top: 0.5rem;">Send professional email notifications to registered users</p>
        </header>
        
        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <h3>Total Subscribers</h3>
                <div class="stat-value"><?php echo $stats['total']; ?></div>
            </div>
            <div class="stat-card">
                <h3>Active Subscribers</h3>
                <div class="stat-value"><?php echo $stats['active']; ?></div>
            </div>
            <div class="stat-card">
                <h3>Inactive Subscribers</h3>
                <div class="stat-value"><?php echo $stats['inactive']; ?></div>
            </div>
        </div>
        
        <!-- Message Display -->
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType === 'success' ? 'success' : 'error'; ?>" style="margin-bottom: 2rem;">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        
        <!-- Newsletter Form -->
        <div class="newsletter-form">
            <form method="POST" action="" id="newsletterForm">
                <input type="hidden" name="action" value="send_campaign">
                
                <!-- Recipient Selection -->
                <div class="form-section">
                    <h3>📋 Select Recipients</h3>
                    <div class="recipient-selector">
                        <div class="recipient-option">
                            <input type="radio" name="recipient_type" value="active" id="recipient_active" checked>
                            <label for="recipient_active">
                                Active Subscribers
                                <span class="recipient-count"><?php echo $stats['active']; ?> recipients</span>
                            </label>
                        </div>
                        <div class="recipient-option">
                            <input type="radio" name="recipient_type" value="all" id="recipient_all">
                            <label for="recipient_all">
                                All Subscribers
                                <span class="recipient-count"><?php echo $stats['total']; ?> recipients</span>
                            </label>
                        </div>
                    </div>
                </div>
                
                <!-- Test Email Section -->
                <div class="test-email-section">
                    <h4>🧪 Test Email (Optional)</h4>
                    <p>Send a test email to yourself before sending to all recipients</p>
                    <div class="test-email-input">
                        <input type="email" name="test_email" id="testEmail" placeholder="your-email@example.com" class="form-group input">
                        <button type="submit" name="test_only" value="1" class="btn btn-test">Send Test</button>
                    </div>
                </div>
                
                <!-- Email Subject -->
                <div class="form-section">
                    <h3>✉️ Email Subject</h3>
                    <div class="form-group">
                        <input type="text" name="subject" id="emailSubject" placeholder="Enter email subject..." required class="form-group input" style="width: 100%;">
                    </div>
                </div>
                
                <!-- Email Body -->
                <div class="form-section">
                    <h3>📝 Email Body</h3>
                    <div class="form-group">
                        <textarea name="body" id="emailBody" rows="12" placeholder="Enter your email content here... You can use HTML for formatting." required class="form-group textarea" style="width: 100%; font-family: monospace; font-size: 0.875rem;"></textarea>
                        <small style="color: var(--text-secondary); margin-top: 0.5rem; display: block;">HTML is supported. Use &lt;br&gt; for line breaks, &lt;strong&gt; for bold, etc.</small>
                    </div>
                    
                    <!-- Email Preview -->
                    <div class="email-preview" id="emailPreview" style="display: none;">
                        <h4>Preview</h4>
                        <div class="email-preview-content" id="previewContent"></div>
                    </div>
                </div>
                
                <!-- Action Buttons -->
                <div class="button-group">
                    <button type="button" id="previewBtn" class="btn btn-secondary">👁️ Preview</button>
                    <button type="submit" class="btn btn-primary" id="sendBtn">📮 Send Campaign</button>
                </div>
            </form>
        </div>
    </main>
    
    <script src="/admin/js/admin.js"></script>
    <script>
        // Preview functionality
        const previewBtn = document.getElementById('previewBtn');
        const emailPreview = document.getElementById('emailPreview');
        const previewContent = document.getElementById('previewContent');
        const emailBody = document.getElementById('emailBody');
        const emailSubject = document.getElementById('emailSubject');
        
        if (previewBtn) {
            previewBtn.addEventListener('click', function() {
                const body = emailBody.value;
                const subject = emailSubject.value;
                
                if (!body) {
                    alert('Please enter email content first');
                    return;
                }
                
                previewContent.innerHTML = `
                    <div style="margin-bottom: 1rem; padding-bottom: 1rem; border-bottom: 1px solid #ddd;">
                        <strong>Subject:</strong> ${subject || '(No subject)'}
                    </div>
                    ${body}
                `;
                
                emailPreview.style.display = emailPreview.style.display === 'none' ? 'block' : 'none';
            });
        }
        
        // Form submission confirmation
        const newsletterForm = document.getElementById('newsletterForm');
        const sendBtn = document.getElementById('sendBtn');
        
        if (newsletterForm && sendBtn) {
            newsletterForm.addEventListener('submit', function(e) {
                const testEmail = document.getElementById('testEmail').value;
                const recipientType = document.querySelector('input[name="recipient_type"]:checked').value;
                
                // If not test email, confirm before sending
                if (!testEmail && !e.submitter?.hasAttribute('name')) {
                    const recipientCount = recipientType === 'active' ? <?php echo $stats['active']; ?> : <?php echo $stats['total']; ?>;
                    const confirmed = confirm(`Are you sure you want to send this email to ${recipientCount} recipient(s)?\n\nThis action cannot be undone.`);
                    
                    if (!confirmed) {
                        e.preventDefault();
                        return false;
                    }
                    
                    // Show loading state
                    sendBtn.disabled = true;
                    sendBtn.textContent = 'Sending...';
                }
            });
        }
        
        // Auto-update preview when typing
        if (emailBody) {
            let previewTimeout;
            emailBody.addEventListener('input', function() {
                clearTimeout(previewTimeout);
                previewTimeout = setTimeout(function() {
                    if (emailPreview.style.display !== 'none') {
                        previewContent.innerHTML = emailBody.value;
                    }
                }, 500);
            });
        }
    </script>
</body>
</html>
