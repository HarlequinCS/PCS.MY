/**
 * ============================================
 * SPA PREFETCHER - Vue.js Implementation
 * ============================================
 * 
 * This module provides prefetching for Vue.js Single Page Applications (SPA).
 * It prefetches route components and data in the background for instant navigation.
 * 
 * Features:
 * - Prefetches Vue components (code splitting)
 * - Prefetches API data for routes
 * - Memory caching for instant navigation
 * - Network-aware prefetching
 * - Works with Vue Router
 * 
 * Usage:
 * 1. Install: npm install vue-router
 * 2. Import and use prefetchPlugin in your Vue app
 * 3. Use prefetch-link component instead of router-link
 * 
 * Example:
 *   import { prefetchPlugin } from './js/spa-prefetcher-vue';
 *   app.use(prefetchPlugin);
 *   <prefetch-link to="/products">Products</prefetch-link>
 */

// ============================================
// CONFIGURATION
// ============================================

/**
 * Route prefetch configuration
 * Maps route paths to their component loaders and data fetchers
 */
const ROUTE_CONFIG = {
  '/products': {
    componentLoader: () => import('./pages/Products.vue'),
    dataFetcher: () => fetch('/api/products').then(r => r.json()),
    priority: 'high'
  },
  '/checkout': {
    componentLoader: () => import('./pages/Checkout.vue'),
    dataFetcher: null,
    priority: 'high'
  },
  '/about': {
    componentLoader: () => import('./pages/About.vue'),
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
 */
const routeCache = new Map();

/**
 * Currently prefetching routes
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
        console.warn(`[VuePrefetcher] Failed to prefetch data for ${route}:`, error);
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

    console.log(`[VuePrefetcher] Prefetched route: ${route}`);
  } catch (error) {
    console.warn(`[VuePrefetcher] Failed to prefetch ${route}:`, error);
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
// VUE PLUGIN
// ============================================

/**
 * Vue plugin for route prefetching
 * 
 * Usage:
 *   import { prefetchPlugin } from './js/spa-prefetcher-vue';
 *   app.use(prefetchPlugin);
 */
export const prefetchPlugin = {
  install(app, options = {}) {
    // Merge user config with default config
    if (options.routes) {
      Object.assign(ROUTE_CONFIG, options.routes);
    }

    // Provide prefetch functions globally
    app.config.globalProperties.$prefetchRoute = prefetchRoute;
    app.config.globalProperties.$isRouteCached = (route) => routeCache.has(route);
    app.config.globalProperties.$getCachedRouteData = (route) => routeCache.get(route)?.data;

    // Prefetch likely routes when route changes
    app.mixin({
      mounted() {
        if (this.$route) {
          const currentRoute = this.$route.path;
          setTimeout(() => {
            prefetchLikelyRoutes(currentRoute);
          }, 100);
        }
      },
      watch: {
        '$route.path'(newPath) {
          setTimeout(() => {
            prefetchLikelyRoutes(newPath);
          }, 100);
        }
      }
    });
  }
};

// ============================================
// VUE COMPONENTS
// ============================================

/**
 * PrefetchLink - Enhanced router-link component with prefetching
 * 
 * Usage: <prefetch-link to="/products">Products</prefetch-link>
 * 
 * Props:
 * - to: Route path (same as Vue Router router-link)
 * - prefetch-on-hover: Prefetch when user hovers (default: true)
 * - prefetch-on-mount: Prefetch when link mounts (default: false)
 */
export const PrefetchLink = {
  name: 'PrefetchLink',
  props: {
    to: {
      type: [String, Object],
      required: true
    },
    prefetchOnHover: {
      type: Boolean,
      default: true
    },
    prefetchOnMount: {
      type: Boolean,
      default: false
    }
  },
  data() {
    return {
      isHovered: false
    };
  },
  computed: {
    routePath() {
      if (typeof this.to === 'string') {
        return this.to;
      }
      return this.$router.resolve(this.to).route.path;
    }
  },
  mounted() {
    if (this.prefetchOnMount) {
      prefetchRoute(this.routePath);
    }
  },
  methods: {
    handleMouseEnter() {
      this.isHovered = true;
      if (this.prefetchOnHover) {
        prefetchRoute(this.routePath);
      }
    },
    handleMouseLeave() {
      this.isHovered = false;
    }
  },
  template: `
    <router-link
      :to="to"
      @mouseenter="handleMouseEnter"
      @mouseleave="handleMouseLeave"
    >
      <slot></slot>
    </router-link>
  `
};

// ============================================
// COMPOSABLES (Vue 3 Composition API)
// ============================================

/**
 * usePrefetch - Composable for manual prefetching
 * 
 * Usage (Vue 3):
 *   import { usePrefetch } from './js/spa-prefetcher-vue';
 *   const { prefetchRoute, isCached } = usePrefetch();
 *   prefetchRoute('/products');
 */
export function usePrefetch() {
  return {
    prefetchRoute,
    isCached: (route) => routeCache.has(route),
    getCachedData: (route) => routeCache.get(route)?.data,
    getCachedComponent: (route) => routeCache.get(route)?.component
  };
}

/**
 * useCachedRoute - Composable to get cached route data
 * 
 * Usage (Vue 3):
 *   import { useCachedRoute } from './js/spa-prefetcher-vue';
 *   const { component, data, isLoading } = useCachedRoute('/products');
 */
export function useCachedRoute(route) {
  const { ref, onMounted } = require('vue');
  const isLoading = ref(!routeCache.has(route));

  onMounted(() => {
    if (!routeCache.has(route)) {
      prefetchRoute(route).then(() => {
        isLoading.value = false;
      });
    }
  });

  return {
    component: routeCache.get(route)?.component,
    data: routeCache.get(route)?.data,
    isLoading,
    isCached: routeCache.has(route)
  };
}

// Export prefetch functions for manual use
export { prefetchRoute, prefetchLikelyRoutes, routeCache };
