<?php
/**
 * Promotion Code System
 * Production-ready promotion code validation and calculation
 * 
 * All dates/times use Asia/Kuala_Lumpur timezone
 * All codes stored and validated in UPPERCASE
 * All monetary values rounded to 2 decimal places
 */

// Load timezone configuration (sets default timezone and provides helper functions)
require_once __DIR__ . '/../config/timezone.php';

// Include datetime helper for normalization
require_once __DIR__ . '/datetime_helper.php';

/**
 * Validate promotion code
 * 
 * Validation order (MANDATORY):
 * 1. Code exists
 * 2. active = 1
 * 3. Time validity (valid_from/valid_until)
 * 4. Usage limit (usage_limit IS NULL OR used_count < usage_limit)
 * 5. Minimum purchase (cart_subtotal >= min_purchase)
 * 
 * @param string $code Promotion code (will be converted to UPPERCASE)
 * @param float $cartSubtotal Cart subtotal amount
 * @param PDO $pdo Database connection
 * @return array ['success' => bool, 'error' => string, 'promotion' => array|null]
 */
function validatePromotionCode($code, $cartSubtotal, $pdo) {
    // Convert to uppercase (case-insensitive)
    $code = strtoupper(trim($code));
    
    if (empty($code)) {
        return [
            'success' => false,
            'error' => 'Promotion code is required',
            'promotion' => null
        ];
    }
    
    try {
        // Step 1: Code exists
        $stmt = $pdo->prepare("SELECT * FROM promotions WHERE code = :code");
        $stmt->execute([':code' => $code]);
        $promotion = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$promotion) {
            return [
                'success' => false,
                'error' => 'Promotion code not found',
                'promotion' => null
            ];
        }
        
        // Step 2: Active status
        if ($promotion['active'] != 1) {
            return [
                'success' => false,
                'error' => 'Promotion code is not active',
                'promotion' => null
            ];
        }
        
        // Step 3: Time validity (TIMEZONE-AWARE)
        // Database stores UTC, application uses Asia/Kuala_Lumpur
        // Strategy: Get current time in app timezone, convert to UTC for comparison
        
        $timeValid = true;
        $validationError = null;
        
        // Get current time in Asia/Kuala_Lumpur, then convert to UTC for DB comparison
        $currentTimeApp = getCurrentDateTime(); // Asia/Kuala_Lumpur
        $currentTimeUTC = appToDbTime($currentTimeApp); // Convert to UTC
        
        // Promotion dates are stored in UTC (after migration) or may be in app timezone (before migration)
        // We'll handle both: assume UTC if format suggests it, otherwise treat as app timezone
        $validFromUTC = null;
        $validUntilUTC = null;
        
        if ($promotion['valid_from'] !== null) {
            // Try to parse - if it's already UTC format, use as-is; otherwise convert from app timezone
            try {
                // Assume stored value is in UTC (after migration)
                // But we need to validate based on app timezone logic
                // So: convert stored UTC to app timezone, then back to UTC (ensures consistency)
                $dt = new DateTime($promotion['valid_from'], new DateTimeZone(DB_TIMEZONE));
                $validFromUTC = $dt->format('Y-m-d H:i:s');
            } catch (Exception $e) {
                // Fallback: treat as app timezone and convert
                $validFromUTC = appToDbTime($promotion['valid_from']);
            }
            
            // Compare: current UTC time must be >= valid_from UTC
            if ($currentTimeUTC < $validFromUTC) {
                $timeValid = false;
                $validationError = 'Promotion code is not yet valid';
            }
        }
        
        if ($timeValid && $promotion['valid_until'] !== null) {
            try {
                $dt = new DateTime($promotion['valid_until'], new DateTimeZone(DB_TIMEZONE));
                $validUntilUTC = $dt->format('Y-m-d H:i:s');
            } catch (Exception $e) {
                $validUntilUTC = appToDbTime($promotion['valid_until']);
            }
            
            // Compare: current UTC time must be <= valid_until UTC
            if ($currentTimeUTC > $validUntilUTC) {
                $timeValid = false;
                $validationError = 'Promotion code has expired';
            }
        }
        
        // Debug logging for datetime validation
        error_log(json_encode([
            'code' => $code,
            'current_app' => $currentTimeApp,
            'current_utc' => $currentTimeUTC,
            'valid_from_db' => $promotion['valid_from'],
            'valid_from_utc' => $validFromUTC,
            'valid_until_db' => $promotion['valid_until'],
            'valid_until_utc' => $validUntilUTC,
            'time_valid' => $timeValid
        ]));
        
        if (!$timeValid) {
            return [
                'success' => false,
                'error' => $validationError ?: 'Promotion code is not valid at this time',
                'promotion' => null
            ];
        }
        
        // Step 4: Usage limit
        if ($promotion['usage_limit'] !== null && $promotion['used_count'] >= $promotion['usage_limit']) {
            return [
                'success' => false,
                'error' => 'Promotion code has reached usage limit',
                'promotion' => null
            ];
        }
        
        // Step 5: Minimum purchase
        $minPurchase = floatval($promotion['min_purchase']);
        $cartSubtotalRounded = round(floatval($cartSubtotal), 2);
        if ($minPurchase > 0 && $cartSubtotalRounded < $minPurchase) {
            return [
                'success' => false,
                'error' => 'Minimum purchase of RM ' . number_format($minPurchase, 2) . ' required',
                'promotion' => null
            ];
        }
        
        // Convert promotion dates from UTC to app timezone for return
        // (so frontend displays correct times)
        $promotionReturn = $promotion;
        if ($promotion['valid_from']) {
            $promotionReturn['valid_from'] = dbToAppTime($promotion['valid_from']);
        }
        if ($promotion['valid_until']) {
            $promotionReturn['valid_until'] = dbToAppTime($promotion['valid_until']);
        }
        
        // All validations passed
        return [
            'success' => true,
            'error' => null,
            'promotion' => $promotionReturn
        ];
        
    } catch (PDOException $e) {
        error_log('Promotion validation error: ' . $e->getMessage());
        return [
            'success' => false,
            'error' => 'Error validating promotion code',
            'promotion' => null
        ];
    }
}

/**
 * Calculate discount amount
 * 
 * Percentage: discount = (subtotal × percentage) / 100, capped by max_discount if set
 * Fixed: discount = fixed_value
 * 
 * Discount must never exceed subtotal
 * max_discount is calculation-only, never part of validation
 * 
 * @param array $promotion Promotion data from database
 * @param float $cartSubtotal Cart subtotal amount
 * @return float Discount amount (rounded to 2 decimal places)
 */
function calculatePromotionDiscount($promotion, $cartSubtotal) {
    $cartSubtotalRounded = round(floatval($cartSubtotal), 2);
    $discount = 0.00;
    
    if ($promotion['type'] === 'percentage') {
        $percentage = floatval($promotion['value']);
        $discount = ($cartSubtotalRounded * $percentage) / 100;
        
        // Apply max_discount cap if set
        if ($promotion['max_discount'] !== null) {
            $maxDiscount = round(floatval($promotion['max_discount']), 2);
            if ($discount > $maxDiscount) {
                $discount = $maxDiscount;
            }
        }
    } else { // fixed
        $discount = round(floatval($promotion['value']), 2);
    }
    
    // Discount must never exceed subtotal
    if ($discount > $cartSubtotalRounded) {
        $discount = $cartSubtotalRounded;
    }
    
    // Round to 2 decimal places
    return round($discount, 2);
}

/**
 * Store validated promotion in session
 * 
 * @param string $code Promotion code
 * @param float $discount Discount amount
 */
function storePromotionInSession($code, $discount) {
    $_SESSION['promo'] = [
        'code' => strtoupper(trim($code)),
        'discount' => round(floatval($discount), 2),
        'validated_at' => time()
    ];
}

/**
 * Get validated promotion from session
 * 
 * @return array|null Session promo data or null
 */
function getPromotionFromSession() {
    return $_SESSION['promo'] ?? null;
}

/**
 * Clear promotion from session
 */
function clearPromotionFromSession() {
    unset($_SESSION['promo']);
}

/**
 * Increment promotion usage count
 * Called after order creation (even if payment fails)
 * 
 * @param string $code Promotion code
 * @param PDO $pdo Database connection
 */
function incrementPromotionUsage($code, $pdo) {
    $code = strtoupper(trim($code));
    if (empty($code)) {
        return;
    }
    
    try {
        $stmt = $pdo->prepare("
            UPDATE promotions 
            SET used_count = used_count + 1 
            WHERE code = :code
        ");
        $stmt->execute([':code' => $code]);
    } catch (PDOException $e) {
        error_log('Promotion usage increment error: ' . $e->getMessage());
    }
}

