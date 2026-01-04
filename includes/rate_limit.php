<?php
/**
 * Simple Rate Limiting
 * Prevents API abuse and spam
 * 
 * Uses file-based cache (consider Redis for high-traffic sites)
 */

/**
 * Check rate limit for a given key
 * 
 * @param string $key Rate limit key (e.g., 'comments', 'api')
 * @param int $maxRequests Maximum requests allowed
 * @param int $window Time window in seconds
 * @return bool True if allowed, false if rate limit exceeded
 */
function checkRateLimit($key, $maxRequests = 60, $window = 60) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    
    // Create unique cache key
    $cacheKey = "ratelimit_{$key}_{$ip}";
    $cacheFile = sys_get_temp_dir() . '/' . md5($cacheKey) . '.ratelimit';
    
    // Load existing requests
    $requests = [];
    if (file_exists($cacheFile)) {
        $data = file_get_contents($cacheFile);
        $requests = json_decode($data, true) ?: [];
    }
    
    // Filter out expired requests (older than window)
    $now = time();
    $requests = array_filter($requests, function($time) use ($now, $window) {
        return ($now - $time) < $window;
    });
    
    // Check if limit exceeded
    if (count($requests) >= $maxRequests) {
        http_response_code(429);
        header('Retry-After: ' . $window);
        header('Content-Type: application/json');
        
        if (php_sapi_name() !== 'cli') {
            echo json_encode([
                'error' => 'Rate limit exceeded',
                'message' => "Too many requests. Please try again in {$window} seconds.",
                'retry_after' => $window
            ]);
        }
        
        return false;
    }
    
    // Add current request
    $requests[] = $now;
    
    // Save updated requests
    file_put_contents($cacheFile, json_encode($requests));
    
    return true;
}

/**
 * Get remaining requests for a rate limit key
 * 
 * @param string $key Rate limit key
 * @param int $maxRequests Maximum requests allowed
 * @param int $window Time window in seconds
 * @return int Remaining requests
 */
function getRemainingRequests($key, $maxRequests = 60, $window = 60) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $cacheKey = "ratelimit_{$key}_{$ip}";
    $cacheFile = sys_get_temp_dir() . '/' . md5($cacheKey) . '.ratelimit';
    
    if (!file_exists($cacheFile)) {
        return $maxRequests;
    }
    
    $data = file_get_contents($cacheFile);
    $requests = json_decode($data, true) ?: [];
    
    $now = time();
    $requests = array_filter($requests, function($time) use ($now, $window) {
        return ($now - $time) < $window;
    });
    
    return max(0, $maxRequests - count($requests));
}

