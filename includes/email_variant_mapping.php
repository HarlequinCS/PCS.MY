<?php
/**
 * Variant Name Mapping
 * Maps internal variant codes to customer-facing display names
 * 
 * This ensures emails and customer-facing content always show
 * the latest marketing-friendly variant names instead of internal codes
 */

// Prevent direct access
if (!defined('EMAIL_VARIANT_MAPPING_LOADED')) {
    define('EMAIL_VARIANT_MAPPING_LOADED', true);
}

/**
 * Get variant display name from internal code
 * 
 * @param string $internalCode Internal variant code (e.g. 'hitamdepan', 'hijaufront', 'merahdepan')
 * @return string Customer-facing variant name (e.g. 'Classic Black', 'Forest Green', 'Royal Red')
 */
function getVariantDisplayName($internalCode) {
    $variantMapping = [
        'hitamdepan' => 'Classic Black',
        'hijaufront' => 'Forest Green',
        'merahdepan' => 'Royal Red'
    ];
    
    // Return mapped name if exists, otherwise return the code (fallback)
    return $variantMapping[$internalCode] ?? $internalCode;
}

/**
 * Map array of order items to use display names
 * 
 * @param array $orderItems Array of order items with 'variant' key
 * @return array Order items with 'variant_display_name' added
 */
function mapOrderItemsVariants($orderItems) {
    $mappedItems = [];
    
    foreach ($orderItems as $item) {
        $item['variant_display_name'] = getVariantDisplayName($item['variant'] ?? '');
        $mappedItems[] = $item;
    }
    
    return $mappedItems;
}

