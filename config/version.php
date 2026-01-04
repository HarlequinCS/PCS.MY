<?php
/**
 * Version Configuration
 * Centralized version control for CSS and JavaScript files
 * Update this version number whenever you deploy new CSS/JS changes
 */

// Version number - increment this when deploying CSS/JS changes
// Production version: Update this number whenever you deploy CSS/JS changes to force cache refresh
define('ASSETS_VERSION', '1.0.1');

/**
 * Get versioned asset URL
 * 
 * @param string $path Relative path to asset (e.g., 'css/style.css' or 'js/script.js')
 * @return string URL with version parameter
 */
function getVersionedAsset($path) {
    return $path . '?v=' . ASSETS_VERSION;
}

