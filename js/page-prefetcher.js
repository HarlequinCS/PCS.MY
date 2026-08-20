/**
 * ============================================
 * PAGE PREFETCHER - Fast Navigation System
 * ============================================
 * 
 * This module prefetches likely next pages in the background after the first page loads,
 * enabling instant navigation when users click links.
 * 
 * Features:
 * - Non-blocking: Doesn't interfere with first page load
 * - Smart prefetching: Only fetches likely next pages
 * - Memory caching: Stores HTML in memory for instant access
 * - Browser cache: Leverages browser's HTTP cache
 * - Fallback: Gracefully handles failures
 * - Network-aware: Respects user's connection quality
 * 
 * Usage:
 * 1. Include this script in your HTML: <script src="js/page-prefetcher.js" defer></script>
 * 2. Configure pages to prefetch (see CONFIGURATION section below)
 * 3. Navigation will automatically use cached pages when available
 */

(function() {
  'use strict';

  // ============================================
  // CONFIGURATION
  // ============================================
  
  /**
   * Pages to prefetch after first page loads
   * Format: { url: 'path/to/page', priority: 'high'|'low', delay: milliseconds }
   * - priority: 'high' = prefetch immediately, 'low' = prefetch after high priority pages
   * - delay: milliseconds to wait before prefetching (0 = immediate)
   */
  const PREFETCH_CONFIG = {
    // High priority: Most likely next pages (prefetch immediately)
    high: [
      { url: 'order-baju.php', priority: 'high', delay: 3000 },
      { url: 'behind-the-product.html', priority: 'high', delay: 4500 },
    ],
    low: [
    ]
  };

  /**
   * Maximum number of pages to cache in memory
   * Prevents excessive memory usage
   */
  const MAX_CACHE_SIZE = 10;

  /**
   * Timeout for prefetch requests (milliseconds)
   * Prevents hanging requests
   */
  const PREFETCH_TIMEOUT = 10000; // 10 seconds

  /**
   * Enable prefetching only on good network connections
   * Set to false to prefetch on all connections (not recommended)
   */
  const RESPECT_NETWORK_QUALITY = true;

  // ============================================
  // STATE MANAGEMENT
  // ============================================
  
  /**
   * In-memory cache for prefetched pages
   * Structure: { url: { html: string, timestamp: number, status: 'success'|'error' } }
   */
  const pageCache = new Map();

  /**
   * Track which pages are currently being prefetched
   * Prevents duplicate requests
   */
  const prefetchingUrls = new Set();

  /**
   * Track prefetch errors for fallback handling
   */
  const prefetchErrors = new Map();

  // ============================================
  // NETWORK QUALITY DETECTION
  // ============================================
  
  /**
   * Check if user has a good network connection
   * Uses Network Information API if available, otherwise assumes good connection
   * 
   * @returns {boolean} True if connection is good enough for prefetching
   */
  function isGoodConnection() {
    // If Network Information API is available, use it
    if ('connection' in navigator) {
      const conn = navigator.connection || navigator.mozConnection || navigator.webkitConnection;
      if (conn) {
        // Don't prefetch on slow connections or if user is on data saver mode
        if (conn.saveData) {
          return false; // User has data saver enabled
        }
        // Check effective connection type
        const effectiveType = conn.effectiveType;
        // Only prefetch on 3G or better (4G, 5G)
        if (effectiveType && ['slow-2g', '2g'].includes(effectiveType)) {
          return false;
        }
      }
    }
    // Default to true if we can't detect connection quality
    return true;
  }

  // ============================================
  // PREFETCHING LOGIC
  // ============================================
  
  /**
   * Prefetch a single page
   * Fetches HTML and stores it in memory cache
   * 
   * @param {string} url - URL to prefetch
   * @param {number} delay - Delay before prefetching (milliseconds)
   * @returns {Promise<void>}
   */
  function prefetchPage(url, delay = 0) {
    // Return early if already cached or currently prefetching
    if (pageCache.has(url) || prefetchingUrls.has(url)) {
      return Promise.resolve();
    }

    // Return early if network quality is poor
    if (RESPECT_NETWORK_QUALITY && !isGoodConnection()) {
      console.log('[PagePrefetcher] Skipping prefetch for', url, '- poor connection');
      return Promise.resolve();
    }

    // Mark as prefetching
    prefetchingUrls.add(url);

    return new Promise((resolve) => {
      // Apply delay if specified
      setTimeout(() => {
        // Create abort controller for timeout
        const controller = new AbortController();
        const timeoutId = setTimeout(() => controller.abort(), PREFETCH_TIMEOUT);

        // Fetch the page
        fetch(url, {
          method: 'GET',
          headers: {
            'Accept': 'text/html',
            'X-Requested-With': 'XMLHttpRequest', // Some servers use this to detect AJAX
          },
          signal: controller.signal,
          cache: 'default', // Use browser cache
        })
          .then(response => {
            clearTimeout(timeoutId);
            
            if (!response.ok) {
              throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            
            return response.text();
          })
          .then(html => {
            // Store in cache (respect max cache size)
            if (pageCache.size >= MAX_CACHE_SIZE) {
              // Remove oldest entry (simple FIFO)
              const firstKey = pageCache.keys().next().value;
              pageCache.delete(firstKey);
            }
            
            pageCache.set(url, {
              html: html,
              timestamp: Date.now(),
              status: 'success'
            });
            
            console.log('[PagePrefetcher] Successfully prefetched:', url);
            prefetchingUrls.delete(url);
            resolve();
          })
          .catch(error => {
            clearTimeout(timeoutId);
            
            // Store error for fallback handling
            prefetchErrors.set(url, {
              error: error.message,
              timestamp: Date.now()
            });
            
            console.warn('[PagePrefetcher] Failed to prefetch:', url, error.message);
            prefetchingUrls.delete(url);
            resolve(); // Resolve anyway - errors are non-critical
          });
      }, delay);
    });
  }

  /**
   * Prefetch multiple pages based on configuration
   * Processes high priority pages first, then low priority
   */
  function prefetchPages() {
    // Check network quality first
    if (RESPECT_NETWORK_QUALITY && !isGoodConnection()) {
      console.log('[PagePrefetcher] Skipping prefetch - poor network connection');
      return;
    }

    // Prefetch high priority pages immediately
    const highPriorityPages = PREFETCH_CONFIG.high || [];
    highPriorityPages.forEach(page => {
      prefetchPage(page.url, page.delay || 0);
    });

    // Prefetch low priority pages after a delay
    const lowPriorityPages = PREFETCH_CONFIG.low || [];
    lowPriorityPages.forEach(page => {
      prefetchPage(page.url, page.delay || 2000);
    });
  }

  // ============================================
  // NAVIGATION INTERCEPTION
  // ============================================
  
  /**
   * Intercept link clicks and use cached page if available
   * Falls back to normal navigation if cache miss
   * 
   * @param {Event} event - Click event
   */
  function handleLinkClick(event) {
    const link = event.currentTarget;
    const url = link.href || link.getAttribute('href');
    
    // Only handle internal links (same origin)
    if (!url || url.startsWith('http') && !url.startsWith(window.location.origin)) {
      return; // External link or invalid URL
    }
    
    // Normalize URL (remove hash, query params for matching)
    const normalizedUrl = normalizeUrl(url);
    
    // Check if we have this page cached
    const cached = pageCache.get(normalizedUrl);
    
    if (cached && cached.status === 'success') {
      // Prevent default navigation
      event.preventDefault();
      event.stopPropagation();
      
      // Use cached page for instant navigation
      navigateToCachedPage(normalizedUrl, cached.html);
      
      // Prefetch the new page's likely next pages
      setTimeout(() => prefetchPages(), 100);
    }
    // If not cached, let normal navigation proceed
  }

  /**
   * Normalize URL for cache matching
   * Removes hash and query params, converts to relative path
   * 
   * @param {string} url - URL to normalize
   * @returns {string} Normalized URL
   */
  function normalizeUrl(url) {
    try {
      const urlObj = new URL(url, window.location.origin);
      // Return pathname only (no hash, no query params)
      return urlObj.pathname;
    } catch (e) {
      // Fallback: return as-is if URL parsing fails
      return url.split('?')[0].split('#')[0];
    }
  }

  /**
   * Navigate to a cached page instantly
   * Replaces current page content with cached HTML
   * 
   * @param {string} url - URL being navigated to
   * @param {string} html - Cached HTML content
   */
  function navigateToCachedPage(url, html) {
    // Update browser history
    window.history.pushState({ prefetched: true }, '', url);
    
    // Create a temporary container to parse the HTML
    const tempDiv = document.createElement('div');
    tempDiv.innerHTML = html;
    
    // Extract the main content (body content)
    const newBody = tempDiv.querySelector('body');
    const newHead = tempDiv.querySelector('head');
    
    if (newBody && newHead) {
      // Update page title
      const newTitle = newHead.querySelector('title');
      if (newTitle) {
        document.title = newTitle.textContent;
      }
      
      // Replace body content
      document.body.innerHTML = newBody.innerHTML;
      
      // Re-execute scripts in the new page
      // Note: This is a simplified approach - for production, you may want more sophisticated script handling
      const scripts = newBody.querySelectorAll('script');
      scripts.forEach(oldScript => {
        const newScript = document.createElement('script');
        Array.from(oldScript.attributes).forEach(attr => {
          newScript.setAttribute(attr.name, attr.value);
        });
        newScript.textContent = oldScript.textContent;
        document.body.appendChild(newScript);
      });
      
      // Scroll to top
      window.scrollTo(0, 0);
      
      // Dispatch custom event for other scripts to react to navigation
      window.dispatchEvent(new CustomEvent('pagecached', { detail: { url } }));
      
      console.log('[PagePrefetcher] Navigated to cached page:', url);
    } else {
      // Fallback: normal navigation if parsing fails
      window.location.href = url;
    }
  }

  /**
   * Handle browser back/forward buttons
   * Uses browser's built-in history for instant navigation
   */
  function handlePopState(event) {
    // Browser handles this automatically with pushState
    // But we can dispatch event for other scripts
    window.dispatchEvent(new CustomEvent('pagecached', { detail: { url: window.location.pathname } }));
  }

  // ============================================
  // INITIALIZATION
  // ============================================
  
  /**
   * Initialize the prefetcher
   * Sets up event listeners and starts prefetching
   */
  function init() {
    // Wait for DOM to be ready
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', init);
      return;
    }

    // Wait for page to be fully loaded before starting prefetch
    // This ensures first page load isn't blocked
    if (document.readyState === 'interactive' || document.readyState === 'loading') {
      window.addEventListener('load', () => {
        // Small delay to ensure first page is fully rendered
        setTimeout(prefetchPages, 100);
      });
    } else {
      // Page already loaded
      setTimeout(prefetchPages, 100);
    }

    // Intercept link clicks
    document.addEventListener('click', (event) => {
      const link = event.target.closest('a');
      if (link && link.href) {
        handleLinkClick.call(link, event);
      }
    }, true); // Use capture phase to intercept early

    // Handle browser back/forward
    window.addEventListener('popstate', handlePopState);

    console.log('[PagePrefetcher] Initialized');
  }

  // ============================================
  // PUBLIC API (Optional - for manual control)
  // ============================================
  
  /**
   * Public API for manual prefetching
   * Can be called from other scripts: window.PagePrefetcher.prefetch('url')
   */
  window.PagePrefetcher = {
    /**
     * Manually prefetch a page
     * @param {string} url - URL to prefetch
     * @returns {Promise<void>}
     */
    prefetch: (url) => prefetchPage(url),
    
    /**
     * Check if a page is cached
     * @param {string} url - URL to check
     * @returns {boolean}
     */
    isCached: (url) => {
      const normalized = normalizeUrl(url);
      return pageCache.has(normalized) && pageCache.get(normalized).status === 'success';
    },
    
    /**
     * Clear the cache
     */
    clearCache: () => {
      pageCache.clear();
      prefetchingUrls.clear();
      prefetchErrors.clear();
    },
    
    /**
     * Get cache statistics
     * @returns {Object} Cache stats
     */
    getStats: () => ({
      cached: pageCache.size,
      prefetching: prefetchingUrls.size,
      errors: prefetchErrors.size,
      maxSize: MAX_CACHE_SIZE
    })
  };

  // Auto-initialize when script loads
  init();

})();
