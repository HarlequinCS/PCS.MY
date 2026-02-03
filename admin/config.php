<?php
/**
 * Admin Configuration
 */

// Admin credentials (change these!)
define('ADMIN_USERNAME', 'admin');
define('ADMIN_PASSWORD', 'PCSadmin2024!'); // Change this password!

// Session configuration - PRODUCTION SECURITY
ini_set('session.cookie_httponly', 1);      // Prevent JavaScript access to cookies
ini_set('session.use_only_cookies', 1);     // Prevent session fixation via URL
ini_set('session.cookie_secure', 1);        // ✅ REQUIRED: Set to 1 for HTTPS (change to 0 only for localhost)
ini_set('session.cookie_samesite', 'Strict'); // CSRF protection
ini_set('session.gc_maxlifetime', 3600);    // 1 hour session timeout
ini_set('session.gc_probability', 1);        // Garbage collection probability
ini_set('session.gc_divisor', 100);          // Garbage collection divisor

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
function isAdminLoggedIn() {
    return isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
}

// Require login
function requireAdminLogin() {
    if (!isAdminLoggedIn()) {
        header('Location: /admin/');
        exit;
    }
}

// Login function
function adminLogin($username, $password) {
    if ($username === ADMIN_USERNAME && $password === ADMIN_PASSWORD) {
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_username'] = $username;
        $_SESSION['admin_login_time'] = time();
        return true;
    }
    return false;
}

// Logout function
function adminLogout() {
    $_SESSION = array();
    if (isset($_COOKIE[session_name()])) {
        setcookie(session_name(), '', time() - 3600, '/');
    }
    session_destroy();
}

// Include version configuration
require_once __DIR__ . '/../config/version.php';

/**
 * Get versioned admin asset URL with automatic cache busting
 * Uses file modification time for automatic versioning
 * 
 * @param string $path Relative path from admin root (e.g., 'css/admin.css' or 'js/admin.js')
 * @return string URL with version parameter
 */
function getAdminAsset($path) {
    $filePath = __DIR__ . '/' . $path;
    
    // If file exists, use modification time for cache busting
    if (file_exists($filePath)) {
        $mtime = filemtime($filePath);
        return '/admin/' . $path . '?v=' . $mtime;
    }
    
    // Fallback to version constant if file doesn't exist
    return '/admin/' . $path . '?v=' . ASSETS_VERSION;
}
