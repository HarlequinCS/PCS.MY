<?php
/**
 * CSRF Protection
 * Prevents Cross-Site Request Forgery attacks
 * 
 * Usage:
 * - In forms: <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
 * - On submit: if (!validateCSRFToken($_POST['csrf_token'] ?? '')) { die('Invalid token'); }
 */

// Ensure session is started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Generate or retrieve CSRF token
 * 
 * @return string CSRF token
 */
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Validate CSRF token
 * 
 * @param string $token Token to validate
 * @return bool True if valid, false otherwise
 */
function validateCSRFToken($token) {
    if (!isset($_SESSION['csrf_token'])) {
        return false;
    }
    
    // Use hash_equals for timing attack prevention
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Regenerate CSRF token (use after successful form submission)
 */
function regenerateCSRFToken() {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/**
 * Get CSRF token for AJAX requests
 * Returns token in JSON format
 */
function getCSRFTokenJSON() {
    header('Content-Type: application/json');
    echo json_encode(['csrf_token' => generateCSRFToken()]);
    exit;
}

