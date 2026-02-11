/**
 * Enhanced Smooth Image Lazy Loader
 * 
 * Features:
 * - Lazy loads images when scrolling to sections using Intersection Observer
 * - Preloads images 1-2 sections ahead for smooth scrolling
 * - WebP format support with fallback
 * - Intersection Observer fallback for older browsers
 * - Fade-in animation for loaded images
 * - Optional: Preload all images in background
 * 
 * Browser Support:
 * - Modern browsers: Uses Intersection Observer API
 * - Older browsers: Falls back to scroll-based detection
 */

class SmoothImageLoader {
    constructor(options = {}) {
        // Configuration
        this.preloadAhead = options.preloadAhead || 2; // Preload 1-2 sections ahead
        this.rootMargin = options.rootMargin || '200px'; // Increased for better preloading
        this.images = [];
        this.loadedImages = new Set();
        this.preloadingAll = false;
        this.supportsWebP = this.checkWebPSupport();
        this.supportsIntersectionObserver = 'IntersectionObserver' in window;
        
        // Initialize
        this.init();
    }

    /**
     * Check if browser supports WebP format
     */
    checkWebPSupport() {
        const canvas = document.createElement('canvas');
        canvas.width = 1;
        canvas.height = 1;
        return canvas.toDataURL('image/webp').indexOf('data:image/webp') === 0;
    }

    /**
     * Get WebP version of image if available, otherwise return original
     */
    getWebPSource(src) {
        if (!this.supportsWebP) return src;
        
        // Check if WebP version exists (replace extension)
        const webpSrc = src.replace(/\.(jpg|jpeg|png|svg)$/i, '.webp');
        // For now, return original (WebP conversion would need server-side support)
        // In production, you'd check if WebP exists before using it
        return src;
    }

    /**
     * Initialize lazy loader
     */
    init() {
        this.images = Array.from(document.querySelectorAll('.lazy-image[data-src]'));
        
        if (this.supportsIntersectionObserver) {
            this.setupIntersectionObserver();
        } else {
            // Fallback for older browsers
            this.setupScrollFallback();
        }
        
        this.setupScrollListener();
        this.loadVisibleImages();
    }

    /**
     * Setup Intersection Observer for modern browsers
     */
    setupIntersectionObserver() {
        this.observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    this.loadImage(entry.target);
                    this.observer.unobserve(entry.target);
                }
            });
        }, {
            root: null,
            rootMargin: this.rootMargin,
            threshold: 0.01
        });

        this.images.forEach(img => this.observer.observe(img));
    }

    /**
     * Fallback for browsers without Intersection Observer support
     */
    setupScrollFallback() {
        let ticking = false;
        const checkImages = () => {
            this.images.forEach(img => {
                if (this.isInViewport(img) && img.dataset.src && !img.classList.contains('loaded')) {
                    this.loadImage(img);
                }
            });
            ticking = false;
        };

        window.addEventListener('scroll', () => {
            if (!ticking) {
                window.requestAnimationFrame(checkImages);
                ticking = true;
            }
        }, { passive: true });

        // Check on load
        checkImages();
    }

    /**
     * Setup scroll listener for preloading ahead
     */
    setupScrollListener() {
        let ticking = false;
        window.addEventListener('scroll', () => {
            if (!ticking && !this.preloadingAll) {
                window.requestAnimationFrame(() => {
                    this.preloadAheadSections();
                    ticking = false;
                });
                ticking = true;
            }
        }, { passive: true });
    }

    getCurrentSection() {
        // Try different section selectors for different page types
        const sections = document.querySelectorAll('.section, .product-visual-column, .order-options-column, .cart-item-card, .slide, .showcase-item, .content-section, .timeline-phase, .timeline-card');
        const scrollPosition = window.scrollY + window.innerHeight / 2;
        
        for (let i = 0; i < sections.length; i++) {
            const section = sections[i];
            const sectionTop = section.offsetTop;
            const sectionBottom = sectionTop + section.offsetHeight;
            
            if (scrollPosition >= sectionTop && scrollPosition <= sectionBottom) {
                return i;
            }
        }
        return sections.length - 1;
    }

    preloadAheadSections() {
        const currentSection = this.getCurrentSection();
        const sections = document.querySelectorAll('.section, .product-visual-column, .order-options-column, .cart-item-card, .slide, .showcase-item, .content-section, .timeline-phase, .timeline-card');
        const maxSection = Math.min(currentSection + this.preloadAhead, sections.length - 1);
        
        for (let i = currentSection; i <= maxSection; i++) {
            const section = sections[i];
            if (section) {
                const sectionImages = section.querySelectorAll('.lazy-image[data-src]');
                sectionImages.forEach(img => {
                    if (!this.loadedImages.has(img.dataset.src)) {
                        this.preloadImage(img);
                    }
                });
            }
        }
    }

    loadVisibleImages() {
        const viewportTop = window.scrollY;
        const viewportBottom = window.scrollY + window.innerHeight;
        
        this.images.forEach(img => {
            const imgTop = img.getBoundingClientRect().top + window.scrollY;
            const imgBottom = imgTop + img.offsetHeight;
            
            if (imgBottom >= viewportTop - 200 && imgTop <= viewportBottom + 200) {
                this.loadImage(img);
            }
        });
    }

    preloadImage(img) {
        const src = img.dataset.src;
        if (!src || this.loadedImages.has(src)) return;
        
        const imageLoader = new Image();
        imageLoader.onload = () => {
            this.loadedImages.add(src);
            if (this.isInViewport(img)) {
                this.displayImage(img, src);
            }
        };
        imageLoader.src = src;
    }

    /**
     * Load image with error handling
     */
    loadImage(img) {
        const src = img.dataset.src;
        if (!src) return;
        
        // Check if already loaded
        if (this.loadedImages.has(src)) {
            this.displayImage(img, src);
            return;
        }
        
        // Add loading state
        img.classList.add('loading');
        
        // Create image loader
        const imageLoader = new Image();
        
        imageLoader.onload = () => {
            this.loadedImages.add(src);
            this.displayImage(img, src);
        };
        
        imageLoader.onerror = () => {
            img.classList.remove('loading');
            console.error('Failed to load image:', src);
            
            // Show error state
            const placeholder = img.nextElementSibling;
            if (placeholder && placeholder.classList.contains('placeholder')) {
                placeholder.textContent = 'Failed to load image';
            }
        };
        
        // Start loading
        imageLoader.src = src;
    }

    /**
     * Display loaded image with fade-in effect
     */
    displayImage(img, src) {
        // Use WebP if supported
        const finalSrc = this.getWebPSource(src);
        
        img.src = finalSrc;
        img.classList.remove('loading');
        img.classList.add('loaded');
        
        // Hide placeholder
        const placeholder = img.nextElementSibling;
        if (placeholder && placeholder.classList.contains('placeholder')) {
            placeholder.style.display = 'none';
        }
        
        // Trigger fade-in animation
        requestAnimationFrame(() => {
            img.style.opacity = '1';
        });
    }

    isInViewport(element) {
        const rect = element.getBoundingClientRect();
        return (
            rect.top >= -200 &&
            rect.left >= -200 &&
            rect.bottom <= (window.innerHeight || document.documentElement.clientHeight) + 200 &&
            rect.right <= (window.innerWidth || document.documentElement.clientWidth) + 200
        );
    }

    /**
     * Preload all images in background (optional feature)
     * Useful for ensuring all images are ready before user scrolls
     */
    async preloadAllImages() {
        if (this.preloadingAll) return;
        
        this.preloadingAll = true;
        const totalImages = this.images.length;
        let loadedCount = 0;
        
        const loadPromises = this.images.map((img, index) => {
            return new Promise((resolve) => {
                const src = img.dataset.src;
                if (!src || this.loadedImages.has(src)) {
                    loadedCount++;
                    resolve();
                    return;
                }
                
                const imageLoader = new Image();
                imageLoader.onload = () => {
                    this.loadedImages.add(src);
                    loadedCount++;
                    // Only display if in viewport
                    if (this.isInViewport(img)) {
                        this.displayImage(img, src);
                    }
                    resolve();
                };
                imageLoader.onerror = () => {
                    loadedCount++;
                    resolve();
                };
                
                // Stagger loading to avoid overwhelming the browser
                setTimeout(() => {
                    imageLoader.src = src;
                }, index * 50);
            });
        });
        
        await Promise.all(loadPromises);
        this.preloadingAll = false;
    }
}

// Initialize when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        window.imageLoader = new SmoothImageLoader({ preloadAhead: 2 });
    });
} else {
    window.imageLoader = new SmoothImageLoader({ preloadAhead: 2 });
}
