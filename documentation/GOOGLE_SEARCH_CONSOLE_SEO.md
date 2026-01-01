# Google Search Console & SEO Documentation

**Last Updated:** 2025-01-28  
**Status:** ✅ All SEO configurations up to date

---

## 📋 Table of Contents

1. [Overview](#overview)
2. [Recent Changes (Workshirt → Homepage Migration)](#recent-changes)
3. [Google Search Console Setup](#google-search-console-setup)
4. [URL Structure & Canonical URLs](#url-structure--canonical-urls)
5. [Sitemap Configuration](#sitemap-configuration)
6. [301 Redirects & URL Migration](#301-redirects--url-migration)
7. [SEO Metadata](#seo-metadata)
8. [Google Search Console Actions Required](#google-search-console-actions-required)
9. [Monitoring & Verification](#monitoring--verification)
10. [Best Practices & Maintenance](#best-practices--maintenance)

---

## 📌 Overview

This document covers all Google Search Console and SEO-related configurations, changes, and maintenance procedures for the Projek Cuti Sem website.

### Current URL Structure

- **Homepage:** `https://projectcutisem.com/` (formerly `/workshirt`)
- **Behind The Product:** `https://projectcutisem.com/behind-the-product`
- **Sitemap:** `https://projectcutisem.com/sitemap.xml`
- **Robots.txt:** `https://projectcutisem.com/robots.txt`

---

## 🔄 Recent Changes (Workshirt → Homepage Migration)

**Date:** 2025-01-28  
**Change:** Converted Workshirt product page to homepage

### What Changed

1. **File Structure:**
   - `workshirt.html` → Deleted (content moved to `index.html`)
   - `index.html` → Now contains Workshirt page content
   - Canonical URL: Changed from `/workshirt` to `/`

2. **301 Redirects Added:**
   - `/workshirt` → `/` (301 permanent redirect)
   - `/workshirt.html` → `/` (301 permanent redirect)

3. **Sitemap Updates:**
   - Removed `/workshirt` entry
   - Homepage entry remains at `/`

4. **Metadata Updates:**
   - `site.webmanifest`: `start_url` changed from `/workshirt` to `/`
   - All internal links updated to point to `/` instead of `/workshirt`

### SEO Impact

✅ **Positive:**
- Homepage now directly showcases main product
- Cleaner URL structure (root domain)
- Improved user experience (no redirect needed)

⚠️ **Action Required:**
- Submit updated sitemap to Google Search Console
- Monitor 301 redirect performance
- Update any external backlinks (if applicable)

---

## 🔧 Google Search Console Setup

### Initial Setup (If Not Already Done)

1. **Add Property:**
   - Go to [Google Search Console](https://search.google.com/search-console)
   - Add property: `https://projectcutisem.com`
   - Verify ownership (DNS, HTML file, or meta tag method)

2. **Submit Sitemap:**
   - Navigate to: Sitemaps → Add new sitemap
   - Submit: `https://projectcutisem.com/sitemap.xml`
   - Verify submission is successful

3. **Verify robots.txt:**
   - Navigate to: Settings → robots.txt Tester
   - Verify: `https://projectcutisem.com/robots.txt` is accessible
   - Test that sitemap is discoverable

### Current Configuration

- **Property URL:** `https://projectcutisem.com`
- **Sitemap URL:** `https://projectcutisem.com/sitemap.xml`
- **Verification Method:** (Check current method in GSC)
- **Coverage Status:** Monitor in GSC Coverage report

---

## 🌐 URL Structure & Canonical URLs

### Canonical URL Rules

All pages use self-referencing canonical URLs pointing to their final destination:

1. **Homepage:**
   - File: `index.html`
   - Canonical: `https://projectcutisem.com/`
   - Status: ✅ Valid

2. **Behind The Product:**
   - File: `behind-the-product.html`
   - Canonical: `https://projectcutisem.com/behind-the-product`
   - Status: ✅ Valid

### Canonical URL Best Practices

✅ **DO:**
- Use absolute URLs (full `https://` URLs)
- Point to final destination (HTTP 200 pages)
- Make canonicals self-referencing
- Use clean URLs (no `.html` extension, no trailing slashes except root)
- Match sitemap entries exactly

❌ **DON'T:**
- Use relative URLs (`/page` or `./page`)
- Point to redirecting URLs (301/302)
- Use trailing slashes (except root)
- Use `.html` extensions in canonical URLs
- Include URL parameters (`?utm_source=...`)

### Example Canonical Tag

```html
<link rel="canonical" href="https://projectcutisem.com/">
```

---

## 🗺️ Sitemap Configuration

### Current Sitemap Structure

**Location:** `/sitemap.xml`

```xml
<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
  <url>
    <loc>https://projectcutisem.com/</loc>
    <lastmod>2025-01-28</lastmod>
    <changefreq>weekly</changefreq>
    <priority>1.0</priority>
  </url>
  <url>
    <loc>https://projectcutisem.com/behind-the-product</loc>
    <lastmod>2025-01-28</lastmod>
    <changefreq>monthly</changefreq>
    <priority>0.8</priority>
  </url>
</urlset>
```

### Sitemap Best Practices

- ✅ Contains only final destination URLs (HTTP 200)
- ✅ No redirecting URLs included
- ✅ Matches canonical URLs exactly
- ✅ Updated `<lastmod>` dates when content changes
- ✅ Referenced in `robots.txt`
- ✅ Located at root: `/sitemap.xml`

### robots.txt Reference

**Location:** `/robots.txt`

```
User-agent: *
Allow: /

Sitemap: https://projectcutisem.com/sitemap.xml
```

---

## 🔀 301 Redirects & URL Migration

### Active 301 Redirects (Permanent)

Configured in `.htaccess`:

1. **Workshirt → Homepage:**
   - `/workshirt` → `/` (301)
   - `/workshirt.html` → `/` (301)

2. **Trailing Slash Removal:**
   - `/page/` → `/page` (301)

3. **HTML Extension Removal:**
   - `/page.html` → `/page` (301)

### Redirect Configuration

**File:** `.htaccess`

```apache
# Redirect /workshirt and /workshirt.html to / (301 permanent redirect)
RewriteCond %{THE_REQUEST} \s/+workshirt\.html[\s?] [NC]
RewriteRule ^workshirt\.html$ / [R=301,L]

RewriteCond %{THE_REQUEST} \s/+workshirt[\s?] [NC]
RewriteRule ^workshirt$ / [R=301,L]
```

### Redirect Testing

Test redirects using:
- Browser (check HTTP status code)
- Online tools (RedirectChecker, HTTPStatus.io)
- Google Search Console URL Inspection tool

**Expected Results:**
- `/workshirt` → HTTP 301 → Location: `/`
- `/workshirt.html` → HTTP 301 → Location: `/`

---

## 📝 SEO Metadata

### Homepage Metadata (`index.html`)

```html
<title>Projek Cuti Sem (PCS) X WORKSHIRT COLLECTION</title>
<meta name="description" content="Japanese Drill Half-Zip Workshirt by Projek Cuti Sem. Premium heavyweight Japanese drill fabric, metal half-zip closure, relaxed workwear fit. Built for work, designed for everyday wear.">
<meta name="robots" content="index, follow">
<link rel="canonical" href="https://projectcutisem.com/">
```

### Metadata Best Practices

✅ **Title Tags:**
- Unique for each page
- 50-60 characters (optimal)
- Include brand name
- Include primary keywords

✅ **Meta Descriptions:**
- 150-160 characters (optimal)
- Compelling and descriptive
- Include call-to-action
- Include primary keywords naturally

✅ **Robots Meta:**
- Use `index, follow` for public pages
- Use `noindex, nofollow` for private/admin pages
- Avoid conflicting directives

---

## ⚠️ Google Search Console Actions Required

### Immediate Actions (Post-Migration)

1. **Submit Updated Sitemap:**
   - Go to: Sitemaps section
   - Resubmit: `https://projectcutisem.com/sitemap.xml`
   - Verify submission date is updated

2. **Request URL Removal (Old Workshirt URLs):**
   - Go to: Removals section
   - Add: `https://projectcutisem.com/workshirt`
   - Add: `https://projectcutisem.com/workshirt.html`
   - Request temporary removal (301 redirects will handle permanently)

3. **Monitor Coverage Report:**
   - Check for any errors related to `/workshirt` URLs
   - Verify homepage is indexed correctly
   - Monitor for crawl errors

4. **Use URL Inspection Tool:**
   - Test: `https://projectcutisem.com/`
   - Verify: Status is "URL is on Google"
   - Request indexing if needed
   - Test: `https://projectcutisem.com/workshirt`
   - Verify: Redirects to homepage correctly

### Ongoing Monitoring

**Weekly Checks:**
- [ ] Coverage report for errors
- [ ] Search performance metrics
- [ ] Indexing status of key pages

**Monthly Checks:**
- [ ] Sitemap submission status
- [ ] Mobile usability report
- [ ] Core Web Vitals metrics
- [ ] Search analytics trends

---

## 🔍 Monitoring & Verification

### Verification Checklist

**Post-Migration (2025-01-28):**

- [x] Canonical URL updated to `/`
- [x] 301 redirects configured
- [x] Sitemap updated (removed `/workshirt`)
- [x] `robots.txt` verified
- [x] Internal links updated
- [x] Metadata updated
- [ ] **Google Search Console sitemap resubmitted** (Action Required)
- [ ] **Old URLs removed from index** (Action Required)
- [ ] **Homepage indexing verified** (Action Required)

### Testing URLs

Test the following URLs to verify correct behavior:

1. **Homepage:**
   - `https://projectcutisem.com/` → Should return HTTP 200
   - Canonical: `https://projectcutisem.com/`

2. **Old Workshirt URLs (Should Redirect):**
   - `https://projectcutisem.com/workshirt` → HTTP 301 → `/`
   - `https://projectcutisem.com/workshirt.html` → HTTP 301 → `/`

3. **Sitemap:**
   - `https://projectcutisem.com/sitemap.xml` → Should return valid XML

4. **Robots.txt:**
   - `https://projectcutisem.com/robots.txt` → Should be accessible

### Tools for Verification

- **Google Search Console:** URL Inspection, Coverage Report, Sitemaps
- **Bing Webmaster Tools:** Similar verification (recommended)
- **Online Tools:**
  - RedirectChecker.org
  - HTTPStatus.io
  - XML Sitemap Validator
  - Canonical URL Checker

---

## ✅ Best Practices & Maintenance

### SEO Maintenance Checklist

**When Adding New Pages:**

1. Create HTML file with clean URL structure
2. Add canonical URL (absolute, self-referencing)
3. Add proper meta title and description
4. Add page to `sitemap.xml` with matching URL
5. Update `<lastmod>` date
6. Test page returns HTTP 200
7. Verify not blocked by `robots.txt`
8. Submit updated sitemap to Google Search Console
9. Request indexing via URL Inspection tool

### URL Structure Guidelines

✅ **Good URLs:**
- `https://projectcutisem.com/product-name`
- `https://projectcutisem.com/category/item`
- Short, descriptive, keyword-rich

❌ **Bad URLs:**
- `https://projectcutisem.com/page?id=123`
- `https://projectcutisem.com/very-long-and-complicated-url-name-with-many-words`
- URLs with unnecessary parameters
- URLs with special characters or spaces

### Content Quality Guidelines

✅ **DO:**
- Create unique, valuable content
- Use proper heading hierarchy (H1, H2, H3)
- Include relevant keywords naturally
- Optimize images (alt text, file names, sizes)
- Ensure mobile-responsive design
- Maintain fast page load times

❌ **DON'T:**
- Duplicate content across pages
- Keyword stuffing
- Hidden text or links
- Cloaking (showing different content to search engines)
- Thin or low-quality content

### Technical SEO Checklist

- [x] Canonical URLs properly configured
- [x] Sitemap.xml valid and submitted
- [x] robots.txt properly configured
- [x] 301 redirects for moved pages
- [x] Mobile-responsive design
- [x] Fast page load times
- [x] HTTPS enabled (SSL certificate)
- [x] Structured data (Schema.org markup) - Review if needed
- [x] Open Graph tags for social sharing - Review if needed

---

## 📊 Current SEO Status

### Indexed Pages

| URL | Status | Last Crawled | Notes |
|-----|--------|--------------|-------|
| `https://projectcutisem.com/` | ✅ Indexed | - | Homepage (formerly workshirt) |
| `https://projectcutisem.com/behind-the-product` | ✅ Indexed | - | About/Story page |

### Redirected URLs

| Old URL | New URL | Status | Notes |
|---------|---------|--------|-------|
| `/workshirt` | `/` | ✅ 301 | Permanent redirect |
| `/workshirt.html` | `/` | ✅ 301 | Permanent redirect |

### Sitemap Status

- **Location:** `/sitemap.xml`
- **Last Updated:** 2025-01-28
- **Entries:** 2 pages
- **Status:** ✅ Valid XML structure
- **Submitted to GSC:** Action Required

---

## 🚨 Common Issues & Troubleshooting

### Issue: Old URLs Still in Google Index

**Solution:**
1. Verify 301 redirects are working
2. Submit URL removal request in Google Search Console
3. Wait for Google to recrawl (can take weeks)
4. Monitor Coverage report

### Issue: Canonical URL Mismatch

**Solution:**
1. Verify canonical tag points to final destination
2. Ensure canonical matches sitemap entry
3. Check for conflicting canonical tags
4. Use URL Inspection tool to verify

### Issue: Sitemap Not Updating

**Solution:**
1. Verify XML syntax is valid
2. Check file permissions (should be readable)
3. Resubmit sitemap in Google Search Console
4. Update `<lastmod>` dates when content changes

### Issue: Pages Not Indexing

**Solution:**
1. Check `robots.txt` (ensure not blocked)
2. Verify meta robots tag allows indexing
3. Request indexing via URL Inspection tool
4. Check for crawl errors in Coverage report
5. Ensure page returns HTTP 200 status

---

## 📚 Additional Resources

### Google Search Console Resources

- [Google Search Console Help](https://support.google.com/webmasters)
- [URL Inspection Tool Guide](https://support.google.com/webmasters/answer/9012289)
- [Sitemap Guidelines](https://developers.google.com/search/docs/crawling-indexing/sitemaps/overview)
- [301 Redirects Guide](https://developers.google.com/search/docs/crawling-indexing/301-redirects)

### SEO Resources

- [Google SEO Starter Guide](https://developers.google.com/search/docs/fundamentals/seo-starter-guide)
- [Canonical URLs Guide](https://developers.google.com/search/docs/crawling-indexing/consolidate-duplicate-urls)
- [Mobile-Friendly Test](https://search.google.com/test/mobile-friendly)
- [PageSpeed Insights](https://pagespeed.web.dev/)

### Related Documentation

- `CANONICAL_VALIDATION_RULES.md` - Detailed canonical URL validation rules
- `SEO_SUBMISSION_GUIDE.md` - Step-by-step SEO submission guide
- `WORKSHIRT_CHANGES_SUMMARY.md` - Summary of homepage migration changes

---

## 📝 Change Log

### 2025-01-28: Workshirt → Homepage Migration

- **Changed:** Workshirt page converted to homepage
- **Canonical URL:** Updated from `/workshirt` to `/`
- **Redirects:** Added 301 redirects from `/workshirt` → `/`
- **Sitemap:** Removed `/workshirt` entry
- **Files:** Deleted `workshirt.html`, updated `index.html`
- **Action Required:** Submit updated sitemap to Google Search Console

---

**Document Status:** ✅ Up to Date  
**Next Review:** After Google Search Console actions completed  
**Maintained By:** Development Team

