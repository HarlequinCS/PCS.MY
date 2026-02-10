# Page Prefetching Guide - Fast Navigation System

## Overview

This guide explains how to implement fast page loading with background prefetching for both multi-page HTML/PHP sites and Single Page Applications (SPA).

## Table of Contents

1. [How It Works](#how-it-works)
2. [Multi-Page Implementation (Vanilla JS)](#multi-page-implementation-vanilla-js)
3. [SPA Implementation (React)](#spa-implementation-react)
4. [SPA Implementation (Vue.js)](#spa-implementation-vuejs)
5. [Configuration](#configuration)
6. [Optimization Tips](#optimization-tips)
7. [Troubleshooting](#troubleshooting)

---

## How It Works

### Core Concept

1. **First Page Load**: The initial page loads immediately without any blocking
2. **Background Prefetching**: After the first page loads, likely next pages are fetched in the background
3. **Memory Caching**: Prefetched pages are stored in memory for instant access
4. **Instant Navigation**: When users click links, cached pages are used for instant navigation
5. **Fallback**: If prefetch fails, normal navigation proceeds

### Benefits

- ✅ **Fast First Page**: No blocking, immediate render
- ✅ **Instant Navigation**: Cached pages load instantly
- ✅ **Network Efficient**: Only prefetches likely next pages
- ✅ **Smart**: Respects network quality and user preferences
- ✅ **Graceful Fallback**: Works even if prefetch fails

---

## Multi-Page Implementation (Vanilla JS)

### Step 1: Include the Script

Add the prefetcher script to your HTML pages (preferably in the `<head>` or before `</body>`):

```html
<!-- Page Prefetcher - Preloads next pages in background -->
<script src="js/page-prefetcher.js" defer></script>
```

**Important**: Use `defer` attribute to ensure it doesn't block first page render.

### Step 2: Configure Pages to Prefetch

Edit `js/page-prefetcher.js` and update the `PREFETCH_CONFIG`:

```javascript
const PREFETCH_CONFIG = {
  // High priority: Most likely next pages (prefetch immediately)
  high: [
    { url: 'order-baju.php', priority: 'high', delay: 0 },
    { url: 'behind-the-product.html', priority: 'high', delay: 0 },
  ],
  // Low priority: Less likely but still possible (prefetch after delay)
  low: [
    { url: 'checkout.php', priority: 'low', delay: 2000 },
  ]
};
```

### Step 3: How It Works

1. **First page loads** → User sees content immediately
2. **After page load** → Prefetcher starts fetching configured pages in background
3. **User clicks link** → If page is cached, instant navigation; otherwise, normal navigation

### Step 4: Manual Control (Optional)

You can manually control prefetching from other scripts:

```javascript
// Prefetch a specific page
window.PagePrefetcher.prefetch('order-baju.php');

// Check if a page is cached
if (window.PagePrefetcher.isCached('order-baju.php')) {
  console.log('Page is ready!');
}

// Get cache statistics
const stats = window.PagePrefetcher.getStats();
console.log('Cached pages:', stats.cached);

// Clear cache (useful for testing)
window.PagePrefetcher.clearCache();
```

### Example: Navigation Flow

```
User visits index.html
  ↓
Page loads immediately (no blocking)
  ↓
Background: Prefetcher fetches order-baju.php and behind-the-product.html
  ↓
User clicks "BUY NOW" link
  ↓
Instant navigation to order-baju.php (from cache)
  ↓
Background: Prefetcher fetches checkout.php
```

---

## SPA Implementation (React)

### Step 1: Install Dependencies

```bash
npm install react-router-dom
```

### Step 2: Import and Setup

In your main App component:

```jsx
import { BrowserRouter } from 'react-router-dom';
import { PrefetchProvider } from './js/spa-prefetcher-react';

function App() {
  return (
    <BrowserRouter>
      <PrefetchProvider>
        {/* Your app routes */}
      </PrefetchProvider>
    </BrowserRouter>
  );
}
```

### Step 3: Configure Routes

Edit `js/spa-prefetcher-react.js` and update `ROUTE_CONFIG`:

```javascript
const ROUTE_CONFIG = {
  '/products': {
    componentLoader: () => import('./pages/Products'),
    dataFetcher: () => fetch('/api/products').then(r => r.json()),
    priority: 'high'
  },
  '/checkout': {
    componentLoader: () => import('./pages/Checkout'),
    dataFetcher: null,
    priority: 'high'
  }
};
```

### Step 4: Use PrefetchLink

Replace regular `Link` components with `PrefetchLink`:

```jsx
import { PrefetchLink } from './js/spa-prefetcher-react';

// Instead of:
// <Link to="/products">Products</Link>

// Use:
<PrefetchLink to="/products">Products</PrefetchLink>
```

### Step 5: Use Hooks (Optional)

```jsx
import { usePrefetch, useCachedRoute } from './js/spa-prefetcher-react';

function MyComponent() {
  const { prefetchRoute, isCached } = usePrefetch();
  
  // Manually prefetch on hover
  const handleMouseEnter = () => {
    prefetchRoute('/products');
  };
  
  // Get cached route data
  const { component, data, isLoading } = useCachedRoute('/products');
  
  return (
    <div onMouseEnter={handleMouseEnter}>
      {isCached('/products') ? 'Ready!' : 'Loading...'}
    </div>
  );
}
```

---

## SPA Implementation (Vue.js)

### Step 1: Install Dependencies

```bash
npm install vue-router
```

### Step 2: Import and Setup

In your main app file:

```javascript
import { createApp } from 'vue';
import { createRouter } from 'vue-router';
import { prefetchPlugin } from './js/spa-prefetcher-vue';

const app = createApp(App);
const router = createRouter({ /* your routes */ });

app.use(router);
app.use(prefetchPlugin, {
  routes: {
    '/products': {
      componentLoader: () => import('./pages/Products.vue'),
      dataFetcher: () => fetch('/api/products').then(r => r.json()),
      priority: 'high'
    }
  }
});

app.mount('#app');
```

### Step 3: Use PrefetchLink Component

Register the component globally or locally:

```vue
<template>
  <prefetch-link to="/products">Products</prefetch-link>
</template>

<script>
import { PrefetchLink } from './js/spa-prefetcher-vue';

export default {
  components: {
    PrefetchLink
  }
}
</script>
```

### Step 4: Use Composable (Vue 3)

```vue
<template>
  <div @mouseenter="handleMouseEnter">
    {{ isCached('/products') ? 'Ready!' : 'Loading...' }}
  </div>
</template>

<script setup>
import { usePrefetch } from './js/spa-prefetcher-vue';

const { prefetchRoute, isCached } = usePrefetch();

const handleMouseEnter = () => {
  prefetchRoute('/products');
};
</script>
```

---

## Configuration

### Multi-Page Sites

Edit `js/page-prefetcher.js`:

```javascript
// Pages to prefetch
const PREFETCH_CONFIG = {
  high: [
    { url: 'page1.php', priority: 'high', delay: 0 },
  ],
  low: [
    { url: 'page2.php', priority: 'low', delay: 2000 },
  ]
};

// Maximum cache size
const MAX_CACHE_SIZE = 10;

// Prefetch timeout
const PREFETCH_TIMEOUT = 10000;

// Respect network quality
const RESPECT_NETWORK_QUALITY = true;
```

### SPA (React/Vue)

Edit `js/spa-prefetcher-react.js` or `js/spa-prefetcher-vue.js`:

```javascript
// Route configuration
const ROUTE_CONFIG = {
  '/route': {
    componentLoader: () => import('./Component'),
    dataFetcher: () => fetch('/api/data').then(r => r.json()),
    priority: 'high' // or 'low'
  }
};

// Maximum cache size
const MAX_CACHE_SIZE = 15;

// Prefetch timeout
const PREFETCH_TIMEOUT = 10000;

// Respect network quality
const RESPECT_NETWORK_QUALITY = true;
```

---

## Optimization Tips

### 1. Optimize First Page Load (LCP - Largest Contentful Paint)

**Critical Resources:**
- Preload critical images: `<link rel="preload" as="image" href="hero.jpg">`
- Preload critical CSS: `<link rel="preload" href="critical.css" as="style">`
- Defer non-critical JavaScript: `<script defer src="script.js">`

**Example from your index.html:**
```html
<!-- Preload critical hero images -->
<link rel="preload" as="image" href="images/hijaufront.svg" fetchpriority="high">
<link rel="preload" as="image" href="images/hijauback.svg" fetchpriority="high">
```

### 2. Smart Prefetching Strategy

**High Priority Pages:**
- Most common user flows (e.g., "Buy Now" → "Checkout")
- Pages with high click-through rates
- Pages visible in navigation

**Low Priority Pages:**
- Less common pages
- Pages deeper in navigation
- Pages that require authentication

### 3. Network Usage Optimization

**Respect User Preferences:**
- The prefetcher automatically skips prefetching on:
  - Slow connections (2G, slow-2G)
  - Data saver mode
  - Poor network quality

**Limit Prefetching:**
- Only prefetch 2-3 high priority pages
- Use delays for low priority pages
- Set reasonable cache limits

### 4. Cache Management

**Memory Cache:**
- Limited size (default: 10-15 pages)
- Oldest entries removed when limit reached
- Cleared on page refresh

**Browser Cache:**
- Leverages HTTP cache headers
- Respects cache-control directives
- Works with service workers

### 5. Performance Monitoring

```javascript
// Check prefetch statistics
const stats = window.PagePrefetcher.getStats();
console.log('Cache stats:', stats);

// Monitor prefetch success/failure
// Check browser console for prefetch logs
```

---

## Troubleshooting

### Prefetching Not Working

**Check:**
1. Script is loaded: Check browser console for `[PagePrefetcher] Initialized`
2. Network quality: Prefetching is skipped on slow connections
3. Configuration: Verify URLs in `PREFETCH_CONFIG` are correct
4. CORS: Ensure same-origin or proper CORS headers

**Debug:**
```javascript
// Enable verbose logging
console.log(window.PagePrefetcher.getStats());
```

### Pages Not Caching

**Possible Causes:**
1. Cache size limit reached
2. Prefetch timeout
3. Network error
4. Invalid HTML response

**Solution:**
- Check browser console for error messages
- Verify URLs are accessible
- Increase `MAX_CACHE_SIZE` if needed

### Navigation Not Instant

**Check:**
1. Page is cached: `window.PagePrefetcher.isCached('url')`
2. Link click is intercepted (check event listeners)
3. Browser compatibility (modern browsers only)

**Fallback:**
- Normal navigation still works if cache miss
- Prefetch errors are non-blocking

### Memory Usage

**If memory usage is high:**
- Reduce `MAX_CACHE_SIZE`
- Prefetch fewer pages
- Clear cache periodically: `window.PagePrefetcher.clearCache()`

---

## Browser Compatibility

### Supported Browsers

- ✅ Chrome/Edge 90+
- ✅ Firefox 88+
- ✅ Safari 14+
- ✅ Mobile browsers (iOS Safari, Chrome Mobile)

### Required Features

- `fetch()` API
- `Map` and `Set` (ES6)
- `Promise` (ES6)
- `IntersectionObserver` (optional, for lazy loading)

### Fallbacks

- Older browsers: Normal navigation (no prefetching)
- No network API: Assumes good connection
- Fetch fails: Normal navigation proceeds

---

## Best Practices

### 1. Start Small

Begin with 1-2 high priority pages, then expand based on analytics.

### 2. Monitor Performance

Track:
- First page load time (should not increase)
- Navigation speed (should improve)
- Network usage (should be reasonable)
- Cache hit rate

### 3. Test on Real Devices

Test on:
- Slow 3G connections
- Mobile devices
- Different browsers

### 4. Respect User Preferences

- Don't prefetch on data saver mode
- Don't prefetch on slow connections
- Allow users to disable prefetching (future enhancement)

### 5. Keep It Simple

- Only prefetch likely next pages
- Don't over-optimize
- Focus on user experience

---

## Advanced Usage

### Custom Prefetch Logic

```javascript
// Prefetch based on user behavior
document.addEventListener('mousemove', (e) => {
  const link = e.target.closest('a');
  if (link && link.href) {
    window.PagePrefetcher.prefetch(link.href);
  }
}, { passive: true });
```

### Prefetch on Scroll

```javascript
// Prefetch pages as user scrolls (near bottom)
window.addEventListener('scroll', () => {
  if (window.innerHeight + window.scrollY >= document.body.offsetHeight - 1000) {
    window.PagePrefetcher.prefetch('next-page.php');
  }
}, { passive: true });
```

### Analytics Integration

```javascript
// Track prefetch performance
window.addEventListener('pagecached', (e) => {
  // Send analytics event
  analytics.track('page_prefetched', { url: e.detail.url });
});
```

---

## Summary

### Multi-Page Sites
1. Include `js/page-prefetcher.js` with `defer`
2. Configure `PREFETCH_CONFIG` with likely next pages
3. Navigation automatically uses cached pages

### SPA (React)
1. Wrap app with `PrefetchProvider`
2. Use `PrefetchLink` instead of `Link`
3. Configure `ROUTE_CONFIG` with routes

### SPA (Vue)
1. Install `prefetchPlugin`
2. Use `PrefetchLink` component
3. Configure `ROUTE_CONFIG` with routes

### Key Benefits
- ✅ Fast first page load (no blocking)
- ✅ Instant navigation (cached pages)
- ✅ Network efficient (smart prefetching)
- ✅ Graceful fallback (works even if prefetch fails)

---

## Questions?

Check the code comments in:
- `js/page-prefetcher.js` - Multi-page implementation
- `js/spa-prefetcher-react.js` - React SPA implementation
- `js/spa-prefetcher-vue.js` - Vue.js SPA implementation
