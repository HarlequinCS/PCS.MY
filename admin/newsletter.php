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

// Get email statistics and list
$stats = [
    'total' => 0,
    'active' => 0,
    'inactive' => 0
];

$allEmails = [];

try {
    // Check if source column exists, if not add it
    $columns = $pdo->query("SHOW COLUMNS FROM email_notifications LIKE 'source'")->fetchAll();
    if (empty($columns)) {
        $pdo->exec("ALTER TABLE email_notifications ADD COLUMN source VARCHAR(20) DEFAULT 'subscriber' AFTER status");
    }
    
    $totalStmt = $pdo->query("SELECT COUNT(*) as total FROM email_notifications");
    $stats['total'] = (int)$totalStmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    $activeStmt = $pdo->query("SELECT COUNT(*) as total FROM email_notifications WHERE status = 'active'");
    $stats['active'] = (int)$activeStmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    $stats['inactive'] = $stats['total'] - $stats['active'];
    
    // Get counts by source
    $subscriberStmt = $pdo->query("SELECT COUNT(*) as total FROM email_notifications WHERE status = 'active' AND (source = 'subscriber' OR source IS NULL)");
    $stats['subscribers'] = (int)$subscriberStmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    $customerStmt = $pdo->query("SELECT COUNT(*) as total FROM email_notifications WHERE status = 'active' AND source = 'customer'");
    $stats['customers'] = (int)$customerStmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Get all emails for selection (include source)
    $emailsStmt = $pdo->query("SELECT notification_id, email, status, COALESCE(source, 'subscriber') as source FROM email_notifications ORDER BY email ASC");
    $allEmails = $emailsStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Stats error: " . $e->getMessage());
}

// Handle form submission
$message = '';
$messageType = '';
$uploadedAttachments = [];

// Create uploads directory if it doesn't exist
$uploadsDir = __DIR__ . '/../uploads/newsletter_attachments/';
if (!file_exists($uploadsDir)) {
    mkdir($uploadsDir, 0755, true);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'send_campaign') {
    $subject = trim($_POST['subject'] ?? '');
    $body = trim($_POST['body'] ?? '');
    $recipientType = $_POST['recipient_type'] ?? 'active';
    $testEmail = trim($_POST['test_email'] ?? '');
    $testOnly = isset($_POST['test_only']);
    $selectedEmails = $_POST['selected_emails'] ?? [];
    
    // Handle file uploads
    $attachments = [];
    if (!empty($_FILES['attachments']['name'][0])) {
        $allowedTypes = [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.ms-powerpoint',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'image/jpeg',
            'image/png',
            'image/gif',
            'text/plain',
            'application/zip',
            'application/x-zip-compressed'
        ];
        
        $maxFileSize = 10 * 1024 * 1024; // 10MB per file
        
        foreach ($_FILES['attachments']['name'] as $key => $fileName) {
            if ($_FILES['attachments']['error'][$key] === UPLOAD_ERR_OK) {
                $tmpName = $_FILES['attachments']['tmp_name'][$key];
                $fileSize = $_FILES['attachments']['size'][$key];
                $fileType = $_FILES['attachments']['type'][$key];
                
                // Validate file size
                if ($fileSize > $maxFileSize) {
                    $message = "File '{$fileName}' exceeds maximum size of 10MB";
                    $messageType = 'error';
                    continue;
                }
                
                // Validate file type
                if (!in_array($fileType, $allowedTypes)) {
                    $message = "File '{$fileName}' has an invalid file type. Allowed: PDF, Word, Excel, PowerPoint, Images, Text, ZIP";
                    $messageType = 'error';
                    continue;
                }
                
                // Sanitize filename
                $safeFileName = preg_replace('/[^a-zA-Z0-9._-]/', '_', $fileName);
                $uniqueFileName = time() . '_' . uniqid() . '_' . $safeFileName;
                $targetPath = $uploadsDir . $uniqueFileName;
                
                // Move uploaded file
                if (move_uploaded_file($tmpName, $targetPath)) {
                    $attachments[] = [
                        'path' => $targetPath,
                        'name' => $fileName,
                        'size' => $fileSize
                    ];
                    $uploadedAttachments[] = $targetPath; // Track for cleanup
                } else {
                    error_log("Failed to move uploaded file: {$fileName}");
                }
            }
        }
    }
    
    // Debug logging (remove in production)
    error_log("Newsletter Campaign - Recipient Type: " . $recipientType);
    error_log("Newsletter Campaign - Selected Emails Count: " . count($selectedEmails));
    if (!empty($selectedEmails)) {
        error_log("Newsletter Campaign - Selected Email IDs: " . implode(', ', $selectedEmails));
    }
    
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
                // Send test email with attachments
                $testBody = "<div style='background: #fff3cd; padding: 20px; border: 2px solid #ffc107; border-radius: 8px; margin-bottom: 20px;'><strong>🧪 TEST EMAIL</strong><br>This is a test email. The actual campaign will be sent to all recipients.</div>" . $body;
                
                $result = sendSMTPEmail($testEmail, "[TEST] " . $subject, $testBody, '', $attachments);
                
                if ($result) {
                    $message = "Test email sent successfully to {$testEmail}";
                    $messageType = 'success';
                } else {
                    $message = "Failed to send test email. Please check SMTP configuration.";
                    $messageType = 'error';
                }
            }
        } else {
            // Determine recipients
            try {
                $recipients = [];
                
                // Validate and sanitize selected emails array
                if (isset($selectedEmails) && is_array($selectedEmails)) {
                    $selectedEmails = array_filter(array_map('intval', $selectedEmails)); // Remove empty values and convert to integers
                } else {
                    $selectedEmails = [];
                }
                
                if ($recipientType === 'selected') {
                    // Send to selected emails only
                    if (empty($selectedEmails)) {
                        $message = 'Please select at least one recipient when using "Select Specific Recipients"';
                        $messageType = 'error';
                        // Stop processing - don't send to anyone
                        $recipients = [];
                    } else {
                        // Log for debugging (remove in production)
                        error_log("Sending to selected emails: " . implode(', ', $selectedEmails));
                        $placeholders = str_repeat('?,', count($selectedEmails) - 1) . '?';
                        $stmt = $pdo->prepare("SELECT email FROM email_notifications WHERE notification_id IN ($placeholders)");
                        $stmt->execute($selectedEmails);
                        $recipients = $stmt->fetchAll(PDO::FETCH_COLUMN);
                        error_log("Found " . count($recipients) . " recipients for selected campaign");
                    }
                } elseif ($recipientType === 'active') {
                    // Send to active subscribers
                    $stmt = $pdo->query("SELECT email FROM email_notifications WHERE status = 'active'");
                    $recipients = $stmt->fetchAll(PDO::FETCH_COLUMN);
                } elseif ($recipientType === 'all') {
                    // Send to all subscribers
                    $stmt = $pdo->query("SELECT email FROM email_notifications");
                    $recipients = $stmt->fetchAll(PDO::FETCH_COLUMN);
                } else {
                    // Fallback: if recipient type is invalid, default to active
                    $stmt = $pdo->query("SELECT email FROM email_notifications WHERE status = 'active'");
                    $recipients = $stmt->fetchAll(PDO::FETCH_COLUMN);
                }
                
                // Only proceed if we have recipients and no validation errors
                if (!empty($recipients) && $messageType !== 'error') {
                    $successCount = 0;
                    $failCount = 0;
                    
                    foreach ($recipients as $email) {
                        $result = sendSMTPEmail($email, $subject, $body, '', $attachments);
                        if ($result) {
                            $successCount++;
                        } else {
                            $failCount++;
                        }
                        
                        // Small delay to avoid overwhelming SMTP server
                        usleep(100000); // 0.1 second delay
                    }
                    
                    // Clean up uploaded files after sending
                    foreach ($uploadedAttachments as $filePath) {
                        if (file_exists($filePath)) {
                            @unlink($filePath);
                        }
                    }
                    
                    $attachmentInfo = !empty($attachments) ? ' with ' . count($attachments) . ' attachment(s)' : '';
                    $message = "Campaign sent{$attachmentInfo}! Success: {$successCount}, Failed: {$failCount}";
                    $messageType = 'success';
                } elseif (empty($recipients) && $messageType !== 'error') {
                    // Fallback error message if no recipients found and no specific error set
                    $message = 'No recipients found. Please check your selection.';
                    $messageType = 'error';
                }
            } catch (PDOException $e) {
                error_log("Campaign send error: " . $e->getMessage());
                $message = "Error sending campaign: " . $e->getMessage();
                $messageType = 'error';
                
                // Clean up uploaded files on error
                foreach ($uploadedAttachments as $filePath) {
                    if (file_exists($filePath)) {
                        @unlink($filePath);
                    }
                }
            }
        }
    }
    
    // Clean up uploaded files if validation failed
    if ($messageType === 'error' && !empty($uploadedAttachments)) {
        foreach ($uploadedAttachments as $filePath) {
            if (file_exists($filePath)) {
                @unlink($filePath);
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
    <link rel="stylesheet" href="<?php echo getAdminAsset('css/admin.css'); ?>">
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
        
        #emailSelectionList label:hover {
            background: #e9ecef !important;
            border-color: var(--primary-color);
        }
        
        #emailSelectionList input[type="checkbox"]:checked + span {
            font-weight: 600;
            color: var(--primary-color);
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
        
        .attachment-section {
            background: #f8f9fa;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 1rem;
            margin-top: 0.5rem;
        }
        
        .attachment-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem;
            background: white;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            margin-bottom: 0.5rem;
        }
        
        .attachment-item:last-child {
            margin-bottom: 0;
        }
        
        .attachment-icon {
            font-size: 1.5rem;
        }
        
        .attachment-info {
            flex: 1;
        }
        
        .attachment-name {
            font-weight: 500;
            color: var(--text-primary);
            margin-bottom: 0.25rem;
        }
        
        .attachment-size {
            font-size: 0.75rem;
            color: var(--text-secondary);
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
                <h3>Total Emails</h3>
                <div class="stat-value"><?php echo $stats['total']; ?></div>
            </div>
            <div class="stat-card">
                <h3>Active Subscribers</h3>
                <div class="stat-value"><?php echo $stats['subscribers'] ?? 0; ?></div>
            </div>
            <div class="stat-card">
                <h3>Customer Emails</h3>
                <div class="stat-value"><?php echo $stats['customers'] ?? 0; ?></div>
            </div>
            <div class="stat-card">
                <h3>Active Total</h3>
                <div class="stat-value"><?php echo $stats['active']; ?></div>
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
            <form method="POST" action="" id="newsletterForm" enctype="multipart/form-data">
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
                        <div class="recipient-option">
                            <input type="radio" name="recipient_type" value="selected" id="recipient_selected">
                            <label for="recipient_selected">
                                Select Specific Recipients
                                <span class="recipient-count" id="selectedCount">0 selected</span>
                            </label>
                        </div>
                    </div>
                    
                    <!-- Email Selection List (shown when "Select Specific" is chosen) -->
                    <div id="emailSelectionList" style="display: none; margin-top: 1.5rem; max-height: 500px; overflow-y: auto; border: 1px solid var(--border-color); border-radius: 8px; padding: 1rem; background: #f8f9fa;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; flex-wrap: wrap; gap: 1rem;">
                            <strong>Select Recipients:</strong>
                            <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                                <select id="emailFilter" style="padding: 0.5rem; border: 1px solid var(--border-color); border-radius: 4px; font-size: 0.875rem;">
                                    <option value="all">All Emails</option>
                                    <option value="active">Active Only</option>
                                    <option value="inactive">Inactive Only</option>
                                    <option value="subscriber">Subscribers Only</option>
                                    <option value="customer">Customers Only</option>
                                </select>
                                <button type="button" id="selectAllBtn" class="btn btn-sm btn-secondary">Select All</button>
                                <button type="button" id="deselectAllBtn" class="btn btn-sm btn-secondary">Deselect All</button>
                            </div>
                        </div>
                        <div id="emailListContainer" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 0.5rem;">
                            <?php foreach ($allEmails as $email): ?>
                                <?php 
                                $source = $email['source'] ?? 'subscriber';
                                $sourceLabel = $source === 'customer' ? 'Customer' : 'Subscriber';
                                $sourceColor = $source === 'customer' ? '#3b82f6' : '#10b981';
                                ?>
                                <label class="email-checkbox-label" style="display: flex; align-items: center; gap: 0.5rem; padding: 0.5rem; background: white; border: 1px solid var(--border-color); border-radius: 4px; cursor: pointer; transition: all 0.2s;" data-status="<?php echo htmlspecialchars($email['status']); ?>" data-source="<?php echo htmlspecialchars($source); ?>">
                                    <input type="checkbox" name="selected_emails[]" value="<?php echo htmlspecialchars($email['notification_id']); ?>" class="email-checkbox" data-email="<?php echo htmlspecialchars($email['email']); ?>" data-status="<?php echo htmlspecialchars($email['status']); ?>" data-source="<?php echo htmlspecialchars($source); ?>" form="newsletterForm">
                                    <span style="flex: 1; font-size: 0.875rem;"><?php echo htmlspecialchars($email['email']); ?></span>
                                    <span style="font-size: 0.7rem; color: <?php echo $sourceColor; ?>; font-weight: 500; margin-right: 0.25rem;" title="<?php echo $sourceLabel; ?>">
                                        <?php echo $source === 'customer' ? '🛒' : '📧'; ?>
                                    </span>
                                    <span style="font-size: 0.75rem; color: <?php echo $email['status'] === 'active' ? '#10b981' : '#6b7280'; ?>; font-weight: 500;">
                                        <?php echo $email['status'] === 'active' ? '✓' : '○'; ?>
                                    </span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                        <div id="selectionWarning" style="display: none; margin-top: 1rem; padding: 0.75rem; background: #fff3cd; border: 1px solid #ffc107; border-radius: 4px; color: #856404; font-size: 0.875rem;">
                            ⚠️ Please select at least one recipient before sending.
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
                
                <!-- Attachments Section -->
                <div class="form-section">
                    <h3>📎 Attachments (Optional)</h3>
                    <div class="form-group">
                        <input type="file" name="attachments[]" id="attachments" multiple accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.jpg,.jpeg,.png,.gif,.txt,.zip" class="form-group input" style="width: 100%; padding: 0.75rem;">
                        <small style="color: var(--text-secondary); margin-top: 0.5rem; display: block;">
                            You can attach multiple files. Maximum file size: 10MB per file.<br>
                            Allowed types: PDF, Word (.doc, .docx), Excel (.xls, .xlsx), PowerPoint (.ppt, .pptx), Images (.jpg, .png, .gif), Text (.txt), ZIP (.zip)
                        </small>
                        <div id="attachmentList" style="margin-top: 1rem;"></div>
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
    
    <script src="<?php echo getAdminAsset('js/admin.js'); ?>"></script>
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
        
        // Form submission confirmation and validation
        const newsletterForm = document.getElementById('newsletterForm');
        const sendBtn = document.getElementById('sendBtn');
        
        if (newsletterForm && sendBtn) {
            newsletterForm.addEventListener('submit', function(e) {
                const testEmail = document.getElementById('testEmail').value;
                const testOnly = e.submitter?.hasAttribute('name') && e.submitter.getAttribute('name') === 'test_only';
                const recipientType = document.querySelector('input[name="recipient_type"]:checked')?.value;
                
                // If sending test email, allow it
                if (testOnly || testEmail) {
                    return true;
                }
                
                // Validate recipient selection
                if (recipientType === 'selected') {
                    const selectedCheckboxes = document.querySelectorAll('.email-checkbox:checked');
                    if (selectedCheckboxes.length === 0) {
                        e.preventDefault();
                        alert('Please select at least one recipient before sending the campaign.');
                        return false;
                    }
                    
                    const recipientCount = selectedCheckboxes.length;
                    const confirmed = confirm(`Are you sure you want to send this email to ${recipientCount} selected recipient(s)?\n\nThis action cannot be undone.`);
                    
                    if (!confirmed) {
                        e.preventDefault();
                        return false;
                    }
                    
                    // Show loading state
                    sendBtn.disabled = true;
                    sendBtn.textContent = `Sending to ${recipientCount} recipient(s)...`;
                } else {
                    // For active/all recipients
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
        
        // Show/hide email selection list based on recipient type
        const recipientRadios = document.querySelectorAll('input[name="recipient_type"]');
        const emailSelectionList = document.getElementById('emailSelectionList');
        const selectedCount = document.getElementById('selectedCount');
        const emailCheckboxes = document.querySelectorAll('.email-checkbox');
        
        function updateSelectedCount() {
            const checked = document.querySelectorAll('.email-checkbox:checked').length;
            selectedCount.textContent = `${checked} selected`;
            
            // Show/hide warning
            const selectionWarning = document.getElementById('selectionWarning');
            const recipientType = document.querySelector('input[name="recipient_type"]:checked')?.value;
            if (selectionWarning && recipientType === 'selected') {
                selectionWarning.style.display = checked === 0 ? 'block' : 'none';
            }
        }
        
        function toggleEmailList() {
            const selected = document.querySelector('input[name="recipient_type"]:checked');
            const selectionWarning = document.getElementById('selectionWarning');
            
            if (selected && selected.value === 'selected') {
                emailSelectionList.style.display = 'block';
                // Show warning if no emails selected
                const checked = document.querySelectorAll('.email-checkbox:checked').length;
                if (selectionWarning) {
                    selectionWarning.style.display = checked === 0 ? 'block' : 'none';
                }
            } else {
                emailSelectionList.style.display = 'none';
                if (selectionWarning) {
                    selectionWarning.style.display = 'none';
                }
            }
            updateSelectedCount();
        }
        
        recipientRadios.forEach(radio => {
            radio.addEventListener('change', toggleEmailList);
        });
        
        emailCheckboxes.forEach(checkbox => {
            checkbox.addEventListener('change', updateSelectedCount);
        });
        
        // Select All / Deselect All buttons
        const selectAllBtn = document.getElementById('selectAllBtn');
        const deselectAllBtn = document.getElementById('deselectAllBtn');
        const emailFilter = document.getElementById('emailFilter');
        const emailListContainer = document.getElementById('emailSelectionList');
        
        if (selectAllBtn) {
            selectAllBtn.addEventListener('click', function() {
                const visibleCheckboxes = document.querySelectorAll('.email-checkbox-label:not([style*="display: none"]) .email-checkbox');
                visibleCheckboxes.forEach(cb => cb.checked = true);
                updateSelectedCount();
            });
        }
        
        if (deselectAllBtn) {
            deselectAllBtn.addEventListener('click', function() {
                emailCheckboxes.forEach(cb => cb.checked = false);
                updateSelectedCount();
            });
        }
        
        // Email filter functionality
        if (emailFilter) {
            emailFilter.addEventListener('change', function() {
                const filterValue = this.value;
                const emailLabels = document.querySelectorAll('.email-checkbox-label');
                
                emailLabels.forEach(label => {
                    const status = label.getAttribute('data-status');
                    const source = label.getAttribute('data-source');
                    
                    if (filterValue === 'all') {
                        label.style.display = 'flex';
                    } else if (filterValue === 'active' && status === 'active') {
                        label.style.display = 'flex';
                    } else if (filterValue === 'inactive' && status === 'inactive') {
                        label.style.display = 'flex';
                    } else if (filterValue === 'subscriber' && source === 'subscriber' && status === 'active') {
                        label.style.display = 'flex';
                    } else if (filterValue === 'customer' && source === 'customer' && status === 'active') {
                        label.style.display = 'flex';
                    } else {
                        label.style.display = 'none';
                    }
                });
            });
        }
        
        // Initialize
        toggleEmailList();
        
        // Attachment file list display
        const attachmentsInput = document.getElementById('attachments');
        const attachmentList = document.getElementById('attachmentList');
        
        if (attachmentsInput && attachmentList) {
            attachmentsInput.addEventListener('change', function() {
                const files = Array.from(this.files);
                attachmentList.innerHTML = '';
                
                if (files.length > 0) {
                    const listContainer = document.createElement('div');
                    listContainer.style.cssText = 'background: #f8f9fa; border: 1px solid var(--border-color); border-radius: 8px; padding: 1rem; margin-top: 0.5rem;';
                    
                    const header = document.createElement('div');
                    header.style.cssText = 'font-weight: 600; margin-bottom: 0.75rem; color: var(--text-primary);';
                    header.textContent = `Selected Files (${files.length}):`;
                    listContainer.appendChild(header);
                    
                    files.forEach((file, index) => {
                        const fileItem = document.createElement('div');
                        fileItem.style.cssText = 'display: flex; justify-content: space-between; align-items: center; padding: 0.5rem; background: white; border: 1px solid var(--border-color); border-radius: 4px; margin-bottom: 0.5rem;';
                        
                        const fileInfo = document.createElement('div');
                        fileInfo.style.cssText = 'flex: 1;';
                        
                        const fileName = document.createElement('div');
                        fileName.style.cssText = 'font-weight: 500; color: var(--text-primary);';
                        fileName.textContent = file.name;
                        
                        const fileSize = document.createElement('div');
                        fileSize.style.cssText = 'font-size: 0.75rem; color: var(--text-secondary); margin-top: 0.25rem;';
                        const sizeInMB = (file.size / (1024 * 1024)).toFixed(2);
                        fileSize.textContent = `${sizeInMB} MB`;
                        
                        fileInfo.appendChild(fileName);
                        fileInfo.appendChild(fileSize);
                        fileItem.appendChild(fileInfo);
                        
                        // File type icon
                        const fileIcon = document.createElement('span');
                        fileIcon.style.cssText = 'font-size: 1.5rem; margin-right: 0.5rem;';
                        const extension = file.name.split('.').pop().toLowerCase();
                        const iconMap = {
                            'pdf': '📄',
                            'doc': '📝', 'docx': '📝',
                            'xls': '📊', 'xlsx': '📊',
                            'ppt': '📊', 'pptx': '📊',
                            'jpg': '🖼️', 'jpeg': '🖼️', 'png': '🖼️', 'gif': '🖼️',
                            'txt': '📄',
                            'zip': '📦'
                        };
                        fileIcon.textContent = iconMap[extension] || '📎';
                        fileItem.insertBefore(fileIcon, fileInfo);
                        
                        listContainer.appendChild(fileItem);
                    });
                    
                    attachmentList.appendChild(listContainer);
                } else {
                    attachmentList.innerHTML = '';
                }
            });
        }
    </script>
</body>
</html>
