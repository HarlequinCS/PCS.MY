<?php
/**
 * Admin API - Get Email Notifications List
 * Returns all registered email addresses for notification monitoring
 */

header('Content-Type: application/json');

// Require admin authentication
if (!file_exists(__DIR__ . '/../config.php')) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Configuration file not found']);
    exit;
}

require_once __DIR__ . '/../config.php';

// Check if admin is logged in
if (!isAdminLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

// Load database configuration
define('DB_CONFIG_LOADED', true);
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/db.php';

try {
    $pdo = getDBConnection();
    
    // Get all email notifications ordered by creation date (newest first)
    $stmt = $pdo->query("
        SELECT 
            notification_id,
            email,
            status,
            ip_address,
            created_at,
            updated_at
        FROM email_notifications
        ORDER BY created_at DESC
    ");
    
    $emails = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get total count of active emails
    $totalStmt = $pdo->query("SELECT COUNT(*) as total FROM email_notifications WHERE status = 'active'");
    $totalResult = $totalStmt->fetch(PDO::FETCH_ASSOC);
    $totalActive = (int)$totalResult['total'];
    
    // Get total count of all emails (including inactive)
    $allTotalStmt = $pdo->query("SELECT COUNT(*) as total FROM email_notifications");
    $allTotalResult = $allTotalStmt->fetch(PDO::FETCH_ASSOC);
    $totalAll = (int)$allTotalResult['total'];
    
    // Format dates for display
    foreach ($emails as &$email) {
        $email['created_at_formatted'] = date('M d, Y H:i', strtotime($email['created_at']));
        $email['updated_at_formatted'] = date('M d, Y H:i', strtotime($email['updated_at']));
    }
    
    echo json_encode([
        'success' => true,
        'emails' => $emails,
        'total_active' => $totalActive,
        'total_all' => $totalAll
    ]);
    
} catch (PDOException $e) {
    error_log("Email fetch error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to fetch emails'
    ]);
}

