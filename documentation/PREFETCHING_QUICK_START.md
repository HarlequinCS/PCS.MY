# Page Prefetching - Quick Start Guide

## 🚀 Quick Setup (5 minutes)

### For Multi-Page HTML/PHP Sites

1. **Include the script** in your HTML (already done in `index.html`):
   ```html
   <script src="js/page-prefetcher.js" defer></script>
   ```

2. **Configure pages to prefetch** in `js/page-prefetcher.js`:
   ```javascript
   const PREFETCH_CONFIG = {
     high: [
       { url: 'order-baju.php', priority: 'high', delay: 0 },
       { url: 'behind-the-product.html', priority: 'high', delay: 0 },
     ],
     low: [
       { url: 'checkout.php', priority: 'low', delay: 2000 },
     ]
   };
   ```

3. **Done!** Navigation will now be instant for prefetched pages.

---

## 📋 What Happens

```
User visits index.html
  ↓
Page loads immediately ✅
  ↓
Background: Prefetches order-baju.php, behind-the-product.html
  ↓
User clicks "BUY NOW"
  ↓
Instant navigation! ⚡ (from cache)
```

---

## 🎯 Configuration Options

### Priority Levels

- **High Priority**: Prefetch immediately (0ms delay)
  - Most common user flows
  - Primary navigation links
  - Call-to-action pages

- **Low Priority**: Prefetch after delay (2000ms+)
  - Secondary pages
  - Less common flows
  - Deep navigation pages

### Example Configuration

```javascript
const PREFETCH_CONFIG = {
  high: [
    { url: 'products.php', priority: 'high', delay: 0 },
    { url: 'cart.php', priority: 'high', delay: 0 },
  ],
  low: [
    { url: 'about.php', priority: 'low', delay: 3000 },
    { url: 'contact.php', priority: 'low', delay: 5000 },
  ]
};
```

---

## 🔧 Manual Control

```javascript
// Prefetch a specific page
window.PagePrefetcher.prefetch('order-baju.php');

// Check if cached
if (window.PagePrefetcher.isCached('order-baju.php')) {
  console.log('Ready!');
}

// Get stats
const stats = window.PagePrefetcher.getStats();
console.log('Cached:', stats.cached, 'Max:', stats.maxSize);
```

---

## ⚡ Performance Tips

1. **Only prefetch 2-3 high priority pages** - Don't overdo it
2. **Use delays for low priority** - Don't block network
3. **Test on slow connections** - Prefetcher auto-skips on 2G
4. **Monitor cache size** - Default is 10 pages max

---

## 🐛 Troubleshooting

**Not working?**
- Check browser console for `[PagePrefetcher] Initialized`
- Verify URLs in config are correct
- Check network tab for prefetch requests

**Still slow?**
- Verify pages are being cached: `window.PagePrefetcher.isCached('url')`
- Check cache stats: `window.PagePrefetcher.getStats()`
- Ensure prefetch completes before user clicks

---

## 📚 Full Documentation

See `documentation/PAGE_PREFETCHING_GUIDE.md` for:
- SPA implementations (React/Vue)
- Advanced configuration
- Optimization strategies
- Browser compatibility

---

## ✅ Checklist

- [ ] Script included in HTML
- [ ] Pages configured in `PREFETCH_CONFIG`
- [ ] Tested on actual site
- [ ] Verified instant navigation works
- [ ] Checked browser console for errors

---

**That's it!** Your site now has instant navigation. 🎉
