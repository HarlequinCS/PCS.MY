<?php
/**
 * Admin Sidebar Helper
 * Dynamically generates sidebar menu based on admin directory structure
 */

/**
 * Get icon for menu item based on filename
 * 
 * @param string $filename The filename (without extension)
 * @return string Icon emoji or default icon
 */
function getMenuIcon($filename) {
    // Map common filenames to icons
    $iconMap = [
        'dashboard' => '📝',
        'emails' => '📧',
        'orders' => '📦',
        'products' => '🛍️',
        'promotions' => '🎟️',
        'settings' => '⚙️',
        'update_variant_names' => '✏️',
        'flush_sessions' => '🔄',
    ];
    
    // Check if we have a specific icon for this file
    if (isset($iconMap[$filename])) {
        return $iconMap[$filename];
    }
    
    // Default icon based on file type or name pattern
    if (strpos($filename, 'settings') !== false || strpos($filename, 'config') !== false) {
        return '⚙️';
    } elseif (strpos($filename, 'order') !== false) {
        return '📦';
    } elseif (strpos($filename, 'product') !== false) {
        return '🛍️';
    } elseif (strpos($filename, 'email') !== false) {
        return '📧';
    } elseif (strpos($filename, 'comment') !== false || strpos($filename, 'dashboard') !== false) {
        return '📝';
    } elseif (strpos($filename, 'promo') !== false) {
        return '🎟️';
    } else {
        return '📄'; // Default icon
    }
}

/**
 * Format menu item title from filename
 * 
 * @param string $filename The filename (without extension)
 * @return string Formatted title
 */
function formatMenuTitle($filename) {
    // Remove common prefixes/suffixes
    $title = str_replace(['_', '-'], ' ', $filename);
    
    // Capitalize first letter of each word
    $title = ucwords($title);
    
    // Special cases
    $title = str_replace('Php', 'PHP', $title);
    
    return $title;
}

/**
 * Check if a file should be excluded from menu
 * 
 * @param string $filename The filename
 * @return bool True if file should be excluded
 */
function shouldExcludeFromMenu($filename) {
    // Files to exclude from menu
    $excluded = [
        'index.php',
        'config.php',
        'sidebar_helper.php',
        '.htaccess',
        '.gitignore',
    ];
    
    // Directories to exclude
    $excludedDirs = [
        'css',
        'js',
        'api',
        'ajax',
        'images',
        'assets',
    ];
    
    // Check if file is in excluded list
    if (in_array($filename, $excluded)) {
        return true;
    }
    
    // Check if it's a directory that should be excluded
    $fullPath = __DIR__ . '/' . $filename;
    if (is_dir($fullPath) && in_array($filename, $excludedDirs)) {
        return true;
    }
    
    return false;
}

/**
 * Generate dynamic admin sidebar menu
 * 
 * @param string $currentPage The current page filename (e.g., 'dashboard.php')
 * @return string HTML for sidebar menu
 */
function generateAdminSidebar($currentPage = '') {
    $adminDir = __DIR__;
    $menuItems = [];
    
    // Scan admin directory for PHP files
    $files = scandir($adminDir);
    
    foreach ($files as $file) {
        // Skip hidden files, current/parent directories, and excluded files
        if ($file[0] === '.' || shouldExcludeFromMenu($file)) {
            continue;
        }
        
        $fullPath = $adminDir . '/' . $file;
        
        // Only include PHP files
        if (is_file($fullPath) && pathinfo($file, PATHINFO_EXTENSION) === 'php') {
            $filename = pathinfo($file, PATHINFO_FILENAME);
            $menuItems[] = [
                'filename' => $file,
                'name' => $filename,
                'title' => formatMenuTitle($filename),
                'icon' => getMenuIcon($filename),
                'url' => '/admin/' . $file
            ];
        }
    }
    
    // Sort menu items alphabetically by title
    usort($menuItems, function($a, $b) {
        return strcmp($a['title'], $b['title']);
    });
    
    // Build sidebar HTML
    $html = '<aside class="sidebar" id="sidebar">';
    $html .= '<div class="sidebar-header">';
    $html .= '<h2>PCS Admin</h2>';
    $html .= '<p>Admin Panel</p>';
    $html .= '</div>';
    
    $html .= '<nav class="sidebar-nav">';
    
    // Add menu items
    foreach ($menuItems as $item) {
        $isActive = ($item['filename'] === $currentPage) ? ' active' : '';
        $html .= sprintf(
            '<a href="%s" class="nav-item%s">',
            htmlspecialchars($item['url']),
            $isActive
        );
        $html .= '<span class="nav-icon">' . htmlspecialchars($item['icon']) . '</span>';
        $html .= '<span>' . htmlspecialchars($item['title']) . '</span>';
        $html .= '</a>';
    }
    
    // Add logout link
    $logoutUrl = '/admin/' . ($currentPage ?: 'dashboard.php') . '?logout=1';
    $html .= '<a href="' . htmlspecialchars($logoutUrl) . '" class="nav-item">';
    $html .= '<span class="nav-icon">🚪</span>';
    $html .= '<span>Logout</span>';
    $html .= '</a>';
    
    $html .= '</nav>';
    
    // Add sidebar footer
    $html .= '<div class="sidebar-footer">';
    if (isset($_SESSION['admin_username'])) {
        $html .= '<p>Logged in as: <strong>' . htmlspecialchars($_SESSION['admin_username']) . '</strong></p>';
    }
    $html .= '</div>';
    
    $html .= '</aside>';
    
    return $html;
}

