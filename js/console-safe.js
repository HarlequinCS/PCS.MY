/**
 * Production-Safe Console Wrapper
 * Only logs in development (localhost), silent in production
 */
(function() {
    'use strict';
    
    const isDevelopment = window.location.hostname === 'localhost' || 
                        window.location.hostname === '127.0.0.1' ||
                        window.location.hostname === '::1' ||
                        window.location.hostname.includes('.local') ||
                        window.location.hostname.includes('.dev');
    
    // Store original console methods
    const originalConsole = {
        log: console.log,
        error: console.error,
        warn: console.warn,
        info: console.info,
        debug: console.debug
    };
    
    // Override console methods - only log in development
    if (!isDevelopment) {
        // Production: Silent console (no-op functions)
        console.log = function() {};
        console.debug = function() {};
        console.info = function() {};
        
        // Keep errors and warnings for production debugging (but can be removed if needed)
        // Uncomment to disable all console output in production:
        // console.error = function() {};
        // console.warn = function() {};
    }
    // Development: Use original console methods (already set by default)
    
})();
