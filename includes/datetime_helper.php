<?php
/**
 * DateTime Helper Functions
 * Normalizes datetime inputs from HTML forms to MySQL DATETIME format
 */

/**
 * Normalize datetime input from HTML datetime-local to MySQL DATETIME format
 * 
 * HTML datetime-local returns: 2026-01-03T14:30
 * MySQL DATETIME needs: 2026-01-03 14:30:00
 * 
 * @param string|null $input Datetime input from form (can be empty string or null)
 * @return string|null Normalized datetime string or NULL if empty
 */
function normalizeDateTime($input) {
    if (empty($input)) {
        return null;
    }
    
    // Remove any whitespace
    $input = trim($input);
    
    // If empty after trim, return null
    if (empty($input)) {
        return null;
    }
    
    // Replace 'T' with space and append ':00' for seconds
    // Input: 2026-01-03T14:30
    // Output: 2026-01-03 14:30:00
    return str_replace('T', ' ', $input) . ':00';
}

