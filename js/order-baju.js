/**
 * Order Page JavaScript Module
 * Handles product selection, cart management, and UI interactions
 * 
 * Features:
 * - Dynamic price calculation with size addons
 * - Variant selection with image preview
 * - Quantity controls
 * - Cart management (add/remove items)
 * - Modal management (size chart, image zoom)
 * - Form validation and submission
 */

(function() {
    'use strict';

    /**
     * OrderPageController - Main controller for order page functionality
     */
    class OrderPageController {
        constructor(config) {
            // Configuration from PHP
            this.config = {
                pricePerShirt: config.pricePerShirt || 50.00,
                originalPrice: config.originalPrice || 120.00,
                sizeAddonPrices: config.sizeAddonPrices || {},
                showOriginalPrice: config.showOriginalPrice || false
            };

            // DOM Elements
            this.elements = {};
            this.initializeElements();

            // State
            this.state = {
                selectedVariant: null,
                selectedSize: null,
                quantity: 1
            };

            // Initialize
            this.init();
        }

        /**
         * Initialize DOM element references
         */
        initializeElements() {
            // Form elements
            this.elements.orderForm = document.getElementById('orderForm');
            this.elements.quantityInput = document.getElementById('quantityInput');
            this.elements.quantityDecrease = document.getElementById('quantityDecrease');
            this.elements.quantityIncrease = document.getElementById('quantityIncrease');
            this.elements.totalPriceDisplay = document.getElementById('totalPriceDisplay');
            this.elements.originalTotalPriceDisplay = document.getElementById('originalTotalPriceDisplay');
            this.elements.addToCartBtn = document.getElementById('addToCartBtn');
            this.elements.btnText = this.elements.addToCartBtn?.querySelector('.btn-text');
            this.elements.btnLoader = this.elements.addToCartBtn?.querySelector('.btn-loader');

            // Variant and size selection
            this.elements.variantRadios = document.querySelectorAll('input[name="variant"]');
            this.elements.sizeRadios = document.querySelectorAll('input[name="size"]');

            // Product image
            this.elements.mainProductImage = document.getElementById('mainProductImage');
            this.elements.imagePlaceholder = document.getElementById('imagePlaceholder');
            this.elements.productImageWrapper = document.getElementById('productImageWrapper');
            this.elements.zoomHint = document.getElementById('zoomHint');

            // Cart elements
            this.elements.cartFab = document.getElementById('cartFab');
            this.elements.cartPopup = document.getElementById('cartPopup');
            this.elements.cartPopupClose = document.getElementById('cartPopupClose');
            this.elements.cartFabBadge = document.getElementById('cartFabBadge');

            // Modal elements
            this.elements.sizeChartBtn = document.getElementById('sizeChartBtn');
            this.elements.sizeChartModal = document.getElementById('sizeChartModal');
            this.elements.sizeChartClose = document.getElementById('sizeChartClose');
            this.elements.imageZoomModal = document.getElementById('imageZoomModal');
            this.elements.imageZoomClose = document.getElementById('imageZoomClose');
            this.elements.zoomedImage = document.getElementById('zoomedImage');
        }

        /**
         * Initialize event listeners and setup
         */
        init() {
            this.setupQuantityControls();
            this.setupVariantSelection();
            this.setupSizeSelection();
            this.setupImageZoom();
            this.setupSizeChartModal();
            this.setupCartPopup();
            this.setupFormSubmission();
            this.updateTotalPrice();
        }

        /**
         * Setup quantity increase/decrease controls
         */
        setupQuantityControls() {
            if (!this.elements.quantityDecrease || !this.elements.quantityIncrease) return;

            this.elements.quantityDecrease.addEventListener('click', () => {
                const current = parseInt(this.elements.quantityInput.value) || 1;
                if (current > 1) {
                    this.elements.quantityInput.value = current - 1;
                    this.state.quantity = current - 1;
                    this.updateTotalPrice();
                }
            });

            this.elements.quantityIncrease.addEventListener('click', () => {
                const current = parseInt(this.elements.quantityInput.value) || 1;
                if (current < 10) {
                    this.elements.quantityInput.value = current + 1;
                    this.state.quantity = current + 1;
                    this.updateTotalPrice();
                }
            });
        }

        /**
         * Setup variant (color) selection with image preview
         */
        setupVariantSelection() {
            if (!this.elements.variantRadios.length) return;

            this.elements.variantRadios.forEach(radio => {
                radio.addEventListener('change', () => {
                    if (radio.checked) {
                        this.state.selectedVariant = radio.value;
                        const imageSrc = radio.getAttribute('data-image');
                        this.updateProductImage(imageSrc);
                        this.updateVariantCardActiveState(radio);
                    }

                });
            });
        }

        /**
         * Update main product image when variant is selected
         * Uses lazy loading if image loader is available
         */
        updateProductImage(imageSrc) {
            if (!this.elements.mainProductImage || !imageSrc) return;

            // Hide placeholder, show image container
            if (this.elements.imagePlaceholder) {
                this.elements.imagePlaceholder.style.display = 'none';
            }
            this.elements.mainProductImage.style.display = 'block';

            // Check if image is already loaded by lazy loader
            if (window.imageLoader && window.imageLoader.loadedImages.has(imageSrc)) {
                // Image already loaded, display immediately
                this.elements.mainProductImage.src = imageSrc;
                this.elements.mainProductImage.classList.add('loaded');
                if (this.elements.zoomHint) {
                    this.elements.zoomHint.style.display = 'block';
                }
            } else {
                // Set up lazy loading
                this.elements.mainProductImage.dataset.src = imageSrc;
                this.elements.mainProductImage.classList.add('loading');

                if (window.imageLoader) {
                    // Use lazy loader
                    window.imageLoader.loadImage(this.elements.mainProductImage);
                    // Show zoom hint after image loads
                    const checkLoaded = setInterval(() => {
                        if (this.elements.mainProductImage.classList.contains('loaded')) {
                            if (this.elements.zoomHint) {
                                this.elements.zoomHint.style.display = 'block';
                            }
                            clearInterval(checkLoaded);
                        }
                    }, 100);
                } else {
                    // Fallback if lazy loader not ready
                    const img = new Image();
                    img.onload = () => {
                        this.elements.mainProductImage.src = imageSrc;
                        this.elements.mainProductImage.classList.remove('loading');
                        this.elements.mainProductImage.classList.add('loaded');
                        if (this.elements.zoomHint) {
                            this.elements.zoomHint.style.display = 'block';
                        }
                    };
                    img.src = imageSrc;
                }
            }
        }

        /**
         * Update active state of variant cards
         */
        updateVariantCardActiveState(selectedRadio) {
            document.querySelectorAll('.variant-card').forEach(card => {
                card.classList.remove('variant-card--active');
            });
            if (selectedRadio) {
                selectedRadio.closest('.variant-card')?.classList.add('variant-card--active');
            }
        }

        /**
         * Setup size selection
         */
        setupSizeSelection() {
            if (!this.elements.sizeRadios.length) return;

            this.elements.sizeRadios.forEach(radio => {
                radio.addEventListener('change', () => {
                    if (radio.checked) {
                        this.state.selectedSize = radio.value;
                        this.updateTotalPrice();
                    }
                });
            });
        }

        /**
         * Calculate and update total price display
         */
        updateTotalPrice() {
            if (!this.elements.totalPriceDisplay) return;

            const quantity = parseInt(this.elements.quantityInput?.value) || 1;
            const selectedSize = document.querySelector('input[name="size"]:checked');
            const sizeAddon = selectedSize ? (this.config.sizeAddonPrices[selectedSize.value] || 0) : 0;
            const itemPrice = this.config.pricePerShirt + sizeAddon;
            const total = itemPrice * quantity;

            this.elements.totalPriceDisplay.textContent = total.toFixed(2);

            // Update original price total if strikethrough is shown
            if (this.config.showOriginalPrice && this.elements.originalTotalPriceDisplay) {
                const originalTotal = this.config.originalPrice * quantity;
                this.elements.originalTotalPriceDisplay.textContent = originalTotal.toFixed(2);
            }
        }

        /**
         * Setup image zoom modal
         */
        setupImageZoom() {
            if (!this.elements.productImageWrapper || !this.elements.imageZoomModal) return;

            this.elements.productImageWrapper.addEventListener('click', () => {
                if (this.elements.mainProductImage?.src) {
                    this.elements.zoomedImage.src = this.elements.mainProductImage.src;
                    this.elements.imageZoomModal.classList.add('image-zoom-modal--active');
                    document.body.style.overflow = 'hidden';
                }
            });

            if (this.elements.imageZoomClose) {
                this.elements.imageZoomClose.addEventListener('click', () => {
                    this.closeImageZoom();
                });
            }

            const overlay = this.elements.imageZoomModal?.querySelector('.image-zoom-overlay');
            if (overlay) {
                overlay.addEventListener('click', () => {
                    this.closeImageZoom();
                });
            }
        }

        /**
         * Close image zoom modal
         */
        closeImageZoom() {
            if (this.elements.imageZoomModal) {
                this.elements.imageZoomModal.classList.remove('image-zoom-modal--active');
                document.body.style.overflow = '';
            }
        }

        /**
         * Setup size chart modal
         */
        setupSizeChartModal() {
            if (!this.elements.sizeChartBtn || !this.elements.sizeChartModal) return;

            this.elements.sizeChartBtn.addEventListener('click', () => {
                this.elements.sizeChartModal.classList.add('size-chart-modal--active');
                document.body.style.overflow = 'hidden';
            });

            if (this.elements.sizeChartClose) {
                this.elements.sizeChartClose.addEventListener('click', () => {
                    this.closeSizeChart();
                });
            }

            const overlay = this.elements.sizeChartModal?.querySelector('.size-chart-overlay');
            if (overlay) {
                overlay.addEventListener('click', () => {
                    this.closeSizeChart();
                });
            }
        }

        /**
         * Close size chart modal
         */
        closeSizeChart() {
            if (this.elements.sizeChartModal) {
                this.elements.sizeChartModal.classList.remove('size-chart-modal--active');
                document.body.style.overflow = '';
            }
        }

        /**
         * Setup cart popup
         */
        setupCartPopup() {
            if (!this.elements.cartFab || !this.elements.cartPopup) return;

            this.elements.cartFab.addEventListener('click', () => {
                this.elements.cartPopup.classList.add('cart-popup--active');
                document.body.style.overflow = 'hidden';
            });

            if (this.elements.cartPopupClose) {
                this.elements.cartPopupClose.addEventListener('click', () => {
                    this.closeCartPopup();
                });
            }

            const overlay = this.elements.cartPopup?.querySelector('.cart-popup-overlay');
            if (overlay) {
                overlay.addEventListener('click', () => {
                    this.closeCartPopup();
                });
            }
        }

        /**
         * Close cart popup
         */
        closeCartPopup() {
            if (this.elements.cartPopup) {
                this.elements.cartPopup.classList.remove('cart-popup--active');
                document.body.style.overflow = '';
            }
        }

        /**
         * Setup form submission with validation and loading state
         */
        setupFormSubmission() {
            if (!this.elements.orderForm) return;

            this.elements.orderForm.addEventListener('submit', (e) => {
                // Basic validation
                const variantSelected = document.querySelector('input[name="variant"]:checked');
                const sizeSelected = document.querySelector('input[name="size"]:checked');

                if (!variantSelected || !sizeSelected) {
                    e.preventDefault();
                    return;
                }

                // Prevent double submission
                if (this.elements.addToCartBtn?.disabled) {
                    e.preventDefault();
                    return;
                }

                // Show loading state
                if (this.elements.addToCartBtn) {
                    this.elements.addToCartBtn.disabled = true;
                }
                if (this.elements.btnText) {
                    this.elements.btnText.style.display = 'none';
                }
                if (this.elements.btnLoader) {
                    this.elements.btnLoader.style.display = 'inline-block';
                }
            });
        }
    }

    /**
     * CartManager - Handles cart item removal via AJAX
     */
    class CartManager {
        /**
         * Remove item from cart via AJAX
         */
        static removeFromCart(itemId) {
            if (!confirm('Adakah anda pasti mahu membuang item ini?')) {
                return;
            }

            const orderForm = document.getElementById('orderForm');
            if (orderForm) {
                orderForm.style.pointerEvents = 'none';
                orderForm.style.opacity = '0.6';
            }

            // Get CSRF token from page config
            const configScript = document.getElementById('orderPageConfig');
            let csrfToken = '';
            if (configScript) {
                try {
                    const config = JSON.parse(configScript.textContent);
                    csrfToken = config.csrfToken || '';
                } catch (e) {
                    console.error('Error parsing config for CSRF token:', e);
                }
            }

            const formData = new FormData();
            formData.append('action', 'remove_from_cart');
            formData.append('item_id', itemId);
            formData.append('csrf_token', csrfToken);

            fetch('order-baju.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    CartManager.handleRemoveSuccess(itemId, data, orderForm);
                } else {
                    CartManager.handleRemoveError(orderForm);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error removing item. Please try again.');
                CartManager.handleRemoveError(orderForm);
            });
        }

        /**
         * Handle successful cart item removal
         */
        static handleRemoveSuccess(itemId, data, orderForm) {
            const itemElement = document.querySelector(`[data-item-id="${itemId}"]`);
            if (itemElement) {
                itemElement.style.animation = 'fadeOut 0.3s ease-out';
                setTimeout(() => {
                    itemElement.remove();
                    CartManager.updateCartDisplay(data, orderForm);
                }, 300);
            } else {
                CartManager.handleRemoveError(orderForm);
            }
        }

        /**
         * Update cart display after item removal
         */
        static updateCartDisplay(data, orderForm) {
            if (data.cart_count > 0) {
                // Update total items count
                const totalItemsElement = document.querySelector('.cart-summary-row:first-child span:last-child');
                if (totalItemsElement) {
                    totalItemsElement.textContent = data.cart_count + ' item(s)';
                }

                // Update total amount
                const totalAmountElement = document.querySelector('.cart-total-amount');
                if (totalAmountElement) {
                    totalAmountElement.textContent = 'RM ' + parseFloat(data.cart_total).toFixed(2);
                }

                // Update badge
                const cartFab = document.getElementById('cartFab');
                const cartFabBadge = document.getElementById('cartFabBadge');
                if (cartFabBadge) {
                    cartFabBadge.textContent = data.cart_count;
                } else if (cartFab) {
                    const badge = document.createElement('span');
                    badge.className = 'cart-fab-badge';
                    badge.id = 'cartFabBadge';
                    badge.textContent = data.cart_count;
                    cartFab.appendChild(badge);
                }

                if (orderForm) {
                    orderForm.style.pointerEvents = '';
                    orderForm.style.opacity = '';
                }
            } else {
                // Cart is empty
                CartManager.showEmptyCart(orderForm);
            }
        }

        /**
         * Show empty cart message
         */
        static showEmptyCart(orderForm) {
            const cartFabBadge = document.getElementById('cartFabBadge');
            if (cartFabBadge) cartFabBadge.remove();

            const cartItemsList = document.getElementById('cartItemsList');
            const cartSummary = cartItemsList?.nextElementSibling;
            const checkoutBtn = cartSummary?.nextElementSibling;

            if (cartItemsList) cartItemsList.remove();
            if (cartSummary) cartSummary.remove();
            if (checkoutBtn) checkoutBtn.remove();

            const cartPopupContent = document.querySelector('.cart-popup-content');
            if (cartPopupContent) {
                const emptyCartDiv = document.createElement('div');
                emptyCartDiv.className = 'cart-empty';
                emptyCartDiv.innerHTML = `
                    <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" opacity="0.3">
                        <path d="M9 2L7 6M17 2l2 4M3 10h18l-2 8H5l-2-8zM10 18a1 1 0 11-2 0 1 1 0 012 0zM20 18a1 1 0 11-2 0 1 1 0 012 0z"/>
                    </svg>
                    <p>Your cart is empty</p>
                `;

                const cartHeader = cartPopupContent.querySelector('.cart-popup-header');
                if (cartHeader && cartHeader.nextSibling) {
                    cartPopupContent.insertBefore(emptyCartDiv, cartHeader.nextSibling);
                } else {
                    cartPopupContent.appendChild(emptyCartDiv);
                }
            }

            setTimeout(() => {
                if (orderForm) {
                    orderForm.style.pointerEvents = '';
                    orderForm.style.opacity = '';
                }
            }, 500);
        }

        /**
         * Handle error during cart item removal
         */
        static handleRemoveError(orderForm) {
            if (orderForm) {
                orderForm.style.pointerEvents = '';
                orderForm.style.opacity = '';
            }
        }
    }

    // Make removeFromCart available globally for onclick handlers
    window.removeFromCart = CartManager.removeFromCart;

    // Initialize when DOM is ready
    function init() {
        // Get configuration from PHP (injected in HTML)
        const configScript = document.getElementById('orderPageConfig');
        let config = {
            pricePerShirt: 50.00,
            originalPrice: 120.00,
            sizeAddonPrices: {},
            showOriginalPrice: false
        };

        if (configScript) {
            try {
                config = JSON.parse(configScript.textContent);
            } catch (e) {
                console.error('Error parsing order page config:', e);
            }
        }

        // Initialize order page controller
        window.orderPageController = new OrderPageController(config);
    }

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
