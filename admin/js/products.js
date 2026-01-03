/**
 * Product Management JavaScript
 */

document.addEventListener('DOMContentLoaded', function() {
    
    // Update Product Price
    const updatePriceBtn = document.getElementById('updatePriceBtn');
    const productPriceInput = document.getElementById('productPrice');
    const priceMessage = document.getElementById('priceMessage');

    if (updatePriceBtn && productPriceInput) {
        updatePriceBtn.addEventListener('click', function() {
            const productId = productPriceInput.dataset.productId;
            const newPrice = parseFloat(productPriceInput.value);

            if (isNaN(newPrice) || newPrice <= 0 || newPrice > 99999.99) {
                priceMessage.textContent = 'Price must be between 0.01 and 99999.99';
                priceMessage.style.color = 'var(--danger-color)';
                return;
            }

            updatePriceBtn.disabled = true;
            updatePriceBtn.textContent = 'Updating...';
            priceMessage.textContent = '';
            priceMessage.style.color = '';

            const formData = new FormData();
            formData.append('product_id', productId);
            formData.append('new_price', newPrice.toFixed(2));

            fetch('ajax/update_product_price.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    priceMessage.textContent = 'Price updated successfully!';
                    priceMessage.style.color = 'var(--success-color)';
                    setTimeout(() => {
                        priceMessage.textContent = '';
                    }, 3000);
                } else {
                    priceMessage.textContent = 'Error: ' + (data.error || 'Unknown error');
                    priceMessage.style.color = 'var(--danger-color)';
                }
            })
            .catch(error => {
                priceMessage.textContent = 'Network error. Please try again.';
                priceMessage.style.color = 'var(--danger-color)';
            })
            .finally(() => {
                updatePriceBtn.disabled = false;
                updatePriceBtn.textContent = 'Update Price';
            });
        });

        // Allow Enter key to update price
        productPriceInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                updatePriceBtn.click();
            }
        });
    }

    // Update Product Status
    const productStatusSelect = document.getElementById('productStatus');
    const statusMessage = document.getElementById('statusMessage');

    if (productStatusSelect) {
        productStatusSelect.addEventListener('change', function() {
            const productId = this.dataset.productId;
            const newStatus = this.value;
            const oldStatus = this.dataset.currentStatus;

            if (!confirm(`Change product status from "${oldStatus}" to "${newStatus}"?`)) {
                this.value = oldStatus; // Revert selection
                return;
            }

            this.disabled = true;
            statusMessage.textContent = 'Updating...';
            statusMessage.style.color = '';

            const formData = new FormData();
            formData.append('product_id', productId);
            formData.append('new_status', newStatus);

            fetch('ajax/update_product_status.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    statusMessage.textContent = 'Status updated successfully!';
                    statusMessage.style.color = 'var(--success-color)';
                    this.dataset.currentStatus = newStatus;
                    
                    // Reload page to show/hide inventory section
                    setTimeout(() => {
                        location.reload();
                    }, 1000);
                } else {
                    statusMessage.textContent = 'Error: ' + (data.error || 'Unknown error');
                    statusMessage.style.color = 'var(--danger-color)';
                    this.value = oldStatus; // Revert on error
                }
            })
            .catch(error => {
                statusMessage.textContent = 'Network error. Please try again.';
                statusMessage.style.color = 'var(--danger-color)';
                this.value = oldStatus; // Revert on error
            })
            .finally(() => {
                this.disabled = false;
            });
        });
    }

    // Update Inventory Quantity
    const updateInventoryButtons = document.querySelectorAll('.update-inventory-btn');
    
    updateInventoryButtons.forEach(btn => {
        btn.addEventListener('click', function() {
            const variantId = this.dataset.variantId;
            const size = this.dataset.size;
            const variantName = this.dataset.variantName;
            const input = document.getElementById(`qty_${variantId}_${size}`);
            const messageEl = document.getElementById(`msg_${variantId}_${size}`);
            
            if (!input) return;

            const quantity = parseInt(input.value);

            if (isNaN(quantity) || quantity < 0) {
                messageEl.textContent = 'Quantity must be 0 or greater';
                messageEl.style.color = 'var(--danger-color)';
                return;
            }

            this.disabled = true;
            this.textContent = 'Updating...';
            messageEl.textContent = '';
            messageEl.style.color = '';

            const formData = new FormData();
            formData.append('variant_id', variantId);
            formData.append('size', size);
            formData.append('quantity', quantity);

            fetch('ajax/update_inventory.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    messageEl.textContent = 'Updated!';
                    messageEl.style.color = 'var(--success-color)';
                    setTimeout(() => {
                        messageEl.textContent = '';
                    }, 3000);
                } else {
                    messageEl.textContent = 'Error: ' + (data.error || 'Unknown error');
                    messageEl.style.color = 'var(--danger-color)';
                    // Revert to old value
                    input.value = data.old_quantity !== undefined ? data.old_quantity : 0;
                }
            })
            .catch(error => {
                messageEl.textContent = 'Network error. Please try again.';
                messageEl.style.color = 'var(--danger-color)';
            })
            .finally(() => {
                this.disabled = false;
                this.textContent = 'Update';
            });
        });
    });

    // Allow Enter key on inventory inputs
    document.querySelectorAll('.inventory-qty-input').forEach(input => {
        input.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                const variantId = this.dataset.variantId;
                const size = this.dataset.size;
                const btn = document.querySelector(`.update-inventory-btn[data-variant-id="${variantId}"][data-size="${size}"]`);
                if (btn) {
                    btn.click();
                }
            }
        });
    });
});

