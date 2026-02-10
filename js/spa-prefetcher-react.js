/**
 * ============================================
 * SPA PREFETCHER - React Implementation
 * ============================================
 * 
 * This module provides prefetching for React Single Page Applications (SPA).
 * It prefetches route components and data in the background for instant navigation.
 * 
 * Features:
 * - Prefetches React components (code splitting)
 * - Prefetches API data for routes
 * - Memory caching for instant navigation
 * - Network-aware prefetching
 * - Works with React Router
 * 
 * Usage:
 * 1. Install: npm install react-router-dom
 * 2. Import and use PrefetchLink instead of regular Link
 * 3. Configure routes to prefetch (see CONFIGURATION section)
 * 
 * Example:
 *   import { PrefetchLink } from './js/spa-prefetcher-react';
 *   <PrefetchLink to="/products">Products</PrefetchLink>
 */

import React, { useEffect, useState, useCallback } from 'react';
import { Link, useNavigate } from 'react-router-dom';

// ============================================
// CONFIGURATION
// ============================================

/**
 * Route prefetch configuration
 * Maps route paths to their component loaders and data fetchers
 */
const ROUTE_CONFIG = {
  '/products': {
    componentLoader: () => import('./pages/Products'), // Lazy-loaded component
    dataFetcher: () => fetch('/api/products').then(r => r.json()), // Optional API call
    priority: 'high'
  },
  '/checkout': {
    componentLoader: () => import('./pages/Checkout'),
    dataFetcher: null, // No data to prefetch
    priority: 'high'
  },
  '/about': {
    componentLoader: () => import('./pages/About'),
    dataFetcher: null,
    priority: 'low'
  }
};

/**
 * Maximum number of routes to cache
 */
const MAX_CACHE_SIZE = 15;

/**
 * Prefetch timeout (milliseconds)
 */
const PREFETCH_TIMEOUT = 10000;

/**
 * Check network quality before prefetching
 */
const RESPECT_NETWORK_QUALITY = true;

// ============================================
// CACHE MANAGEMENT
// ============================================

/**
 * Cache for prefetched components and data
 * Structure: { route: { component: Component, data: any, timestamp: number } }
 */
const routeCache = new Map();

/**
 * Currently prefetching routes (prevents duplicates)
 */
const prefetchingRoutes = new Set();

/**
 * Check if connection is good enough for prefetching
 */
function isGoodConnection() {
  if ('connection' in navigator) {
    const conn = navigator.connection || navigator.mozConnection || navigator.webkitConnection;
    if (conn) {
      if (conn.saveData) return false;
      const effectiveType = conn.effectiveType;
      if (effectiveType && ['slow-2g', '2g'].includes(effectiveType)) {
        return false;
      }
    }
  }
  return true;
}

/**
 * Prefetch a route (component + data)
 * 
 * @param {string} route - Route path to prefetch
 * @returns {Promise<void>}
 */
async function prefetchRoute(route) {
  const config = ROUTE_CONFIG[route];
  if (!config) return;

  // Skip if already cached or prefetching
  if (routeCache.has(route) || prefetchingRoutes.has(route)) {
    return;
  }

  // Skip if poor connection
  if (RESPECT_NETWORK_QUALITY && !isGoodConnection()) {
    return;
  }

  prefetchingRoutes.add(route);

  try {
    // Prefetch component (code splitting)
    const componentModule = await Promise.race([
      config.componentLoader(),
      new Promise((_, reject) => 
        setTimeout(() => reject(new Error('Timeout')), PREFETCH_TIMEOUT)
      )
    ]);

    // Prefetch data if configured
    let data = null;
    if (config.dataFetcher) {
      try {
        data = await Promise.race([
          config.dataFetcher(),
          new Promise((_, reject) => 
            setTimeout(() => reject(new Error('Timeout')), PREFETCH_TIMEOUT)
          )
        ]);
      } catch (error) {
        console.warn(`[SPAPrefetcher] Failed to prefetch data for ${route}:`, error);
        // Continue even if data fetch fails
      }
    }

    // Store in cache
    if (routeCache.size >= MAX_CACHE_SIZE) {
      const firstKey = routeCache.keys().next().value;
      routeCache.delete(firstKey);
    }

    routeCache.set(route, {
      component: componentModule.default || componentModule,
      data: data,
      timestamp: Date.now()
    });

    console.log(`[SPAPrefetcher] Prefetched route: ${route}`);
  } catch (error) {
    console.warn(`[SPAPrefetcher] Failed to prefetch ${route}:`, error);
  } finally {
    prefetchingRoutes.delete(route);
  }
}

/**
 * Prefetch likely next routes based on current route
 * 
 * @param {string} currentRoute - Current route path
 */
function prefetchLikelyRoutes(currentRoute) {
  // Get high priority routes first
  const highPriorityRoutes = Object.entries(ROUTE_CONFIG)
    .filter(([route, config]) => config.priority === 'high' && route !== currentRoute)
    .map(([route]) => route);

  highPriorityRoutes.forEach(route => {
    prefetchRoute(route);
  });

  // Prefetch low priority routes after delay
  setTimeout(() => {
    const lowPriorityRoutes = Object.entries(ROUTE_CONFIG)
      .filter(([route, config]) => config.priority === 'low' && route !== currentRoute)
      .map(([route]) => route);

    lowPriorityRoutes.forEach(route => {
      prefetchRoute(route);
    });
  }, 2000);
}

// ============================================
// REACT COMPONENTS
// ============================================

/**
 * PrefetchLink - Enhanced Link component with prefetching
 * 
 * Usage: <PrefetchLink to="/products">Products</PrefetchLink>
 * 
 * Props:
 * - to: Route path (same as React Router Link)
 * - prefetchOnHover: Prefetch when user hovers (default: true)
 * - prefetchOnMount: Prefetch when link mounts (default: false)
 * - ...other props passed to Link
 */
export function PrefetchLink({ 
  to, 
  prefetchOnHover = true, 
  prefetchOnMount = false,
  children,
  ...linkProps 
}) {
  const [isHovered, setIsHovered] = useState(false);

  // Prefetch on mount if enabled
  useEffect(() => {
    if (prefetchOnMount) {
      prefetchRoute(to);
    }
  }, [to, prefetchOnMount]);

  // Prefetch on hover
  useEffect(() => {
    if (prefetchOnHover && isHovered) {
      prefetchRoute(to);
    }
  }, [to, prefetchOnHover, isHovered]);

  return (
    <Link
      to={to}
      onMouseEnter={() => setIsHovered(true)}
      onMouseLeave={() => setIsHovered(false)}
      {...linkProps}
    >
      {children}
    </Link>
  );
}

/**
 * PrefetchProvider - Context provider for route prefetching
 * Wraps your app and handles automatic prefetching
 * 
 * Usage:
 *   <PrefetchProvider>
 *     <App />
 *   </PrefetchProvider>
 */
export const PrefetchContext = React.createContext({
  prefetchRoute,
  isCached: (route) => routeCache.has(route),
  getCachedData: (route) => routeCache.get(route)?.data
});

export function PrefetchProvider({ children }) {
  const navigate = useNavigate();
  const [currentRoute, setCurrentRoute] = useState(window.location.pathname);

  // Prefetch likely routes when route changes
  useEffect(() => {
    const handleRouteChange = () => {
      const newRoute = window.location.pathname;
      setCurrentRoute(newRoute);
      
      // Prefetch likely next routes
      setTimeout(() => {
        prefetchLikelyRoutes(newRoute);
      }, 100);
    };

    // Listen for route changes
    window.addEventListener('popstate', handleRouteChange);
    
    // Initial prefetch
    handleRouteChange();

    return () => {
      window.removeEventListener('popstate', handleRouteChange);
    };
  }, []);

  // Get cached component for a route
  const getCachedComponent = useCallback((route) => {
    return routeCache.get(route)?.component;
  }, []);

  // Get cached data for a route
  const getCachedData = useCallback((route) => {
    return routeCache.get(route)?.data;
  }, []);

  // Check if route is cached
  const isCached = useCallback((route) => {
    return routeCache.has(route);
  }, []);

  const value = {
    prefetchRoute,
    isCached,
    getCachedData,
    getCachedComponent,
    currentRoute
  };

  return (
    <PrefetchContext.Provider value={value}>
      {children}
    </PrefetchContext.Provider>
  );
}

/**
 * usePrefetch - Hook for manual prefetching
 * 
 * Usage:
 *   const { prefetchRoute, isCached } = usePrefetch();
 *   prefetchRoute('/products');
 */
export function usePrefetch() {
  const context = React.useContext(PrefetchContext);
  if (!context) {
    throw new Error('usePrefetch must be used within PrefetchProvider');
  }
  return context;
}

/**
 * useCachedRoute - Hook to get cached route data
 * 
 * Usage:
 *   const { component, data, isLoading } = useCachedRoute('/products');
 */
export function useCachedRoute(route) {
  const { getCachedComponent, getCachedData, isCached } = usePrefetch();
  const [isLoading, setIsLoading] = useState(!isCached(route));

  useEffect(() => {
    if (!isCached(route)) {
      prefetchRoute(route).then(() => {
        setIsLoading(false);
      });
    }
  }, [route, isCached]);

  return {
    component: getCachedComponent(route),
    data: getCachedData(route),
    isLoading,
    isCached: isCached(route)
  };
}

// Export prefetch functions for manual use
export { prefetchRoute, prefetchLikelyRoutes, routeCache };
