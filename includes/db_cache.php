<?php
/**
 * Database Query Cache Helper
 * Simple in-memory cache for frequently accessed data
 * 
 * This reduces database queries by caching frequently accessed settings
 * Cache is per-request only (cleared on each HTTP request)
 */

if (!defined('DB_CONFIG_LOADED')) {
    http_response_code(403);
    die('Direct access not allowed');
}

class QueryCache {
    private static $cache = [];
    private static $ttl = 300; // 5 minutes default TTL
    
    /**
     * Get cached value
     * 
     * @param string $key Cache key
     * @return mixed|null Cached value or null if not found/expired
     */
    public static function get($key) {
        if (isset(self::$cache[$key])) {
            $data = self::$cache[$key];
            // Check if cache is still valid
            if (time() - $data['time'] < self::$ttl) {
                return $data['value'];
            }
            // Cache expired, remove it
            unset(self::$cache[$key]);
        }
        return null;
    }
    
    /**
     * Set cached value
     * 
     * @param string $key Cache key
     * @param mixed $value Value to cache
     * @param int|null $ttl Time to live in seconds (optional, uses default if not provided)
     */
    public static function set($key, $value, $ttl = null) {
        self::$cache[$key] = [
            'value' => $value,
            'time' => time(),
            'ttl' => $ttl ?? self::$ttl
        ];
    }
    
    /**
     * Clear cache entry or all cache
     * 
     * @param string|null $key Cache key to clear, or null to clear all
     */
    public static function clear($key = null) {
        if ($key !== null) {
            unset(self::$cache[$key]);
        } else {
            self::$cache = [];
        }
    }
    
    /**
     * Check if key exists in cache and is valid
     * 
     * @param string $key Cache key
     * @return bool True if cached and valid
     */
    public static function has($key) {
        if (isset(self::$cache[$key])) {
            $data = self::$cache[$key];
            return (time() - $data['time']) < $data['ttl'];
        }
        return false;
    }
}

/**
 * Get setting value with caching
 * 
 * @param string $key Setting key
 * @param mixed $default Default value if not found
 * @return mixed Setting value or default
 */
function getSetting($key, $default = null) {
    $cacheKey = "setting_{$key}";
    
    // Try cache first
    $value = QueryCache::get($cacheKey);
    if ($value !== null) {
        return $value;
    }
    
    // Cache miss - query database
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = ? LIMIT 1");
        $stmt->execute([$key]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $value = $result ? $result['setting_value'] : $default;
        
        // Cache the result
        QueryCache::set($cacheKey, $value);
        
        return $value;
    } catch (Exception $e) {
        error_log("Error getting setting '{$key}': " . $e->getMessage());
        return $default;
    }
}

/**
 * Clear setting cache (call this after updating settings)
 * 
 * @param string|null $key Setting key to clear, or null to clear all setting caches
 */
function clearSettingCache($key = null) {
    if ($key !== null) {
        QueryCache::clear("setting_{$key}");
    } else {
        // Clear all setting caches
        QueryCache::clear();
    }
}

