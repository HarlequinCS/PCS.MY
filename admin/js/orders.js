/**
 * Orders Management JavaScript
 * Handles order status updates, stock status updates, and price adjustments
 */

document.addEventListener('DOMContentLoaded', function() {
    // Order Status Update
    const statusSelects = document.querySelectorAll('.order-status-select');
    
    statusSelects.forEach(select => {
        select.addEventListener('change', function() {
            const orderId = this.dataset.orderId;
            const newStatus = this.value;
            const currentStatus = this.dataset.currentStatus;
            
            // Confirm change
            if (!confirm(`Change order #${orderId} status from "${currentStatus}" to "${newStatus}"?`)) {
                this.value = currentStatus; // Revert
                return;
            }
            
            // Disable select during update
            this.disabled = true;
            
            // AJAX request
            const formData = new FormData();
            formData.append('order_id', orderId);
            formData.append('new_status', newStatus);
            
            fetch('/admin/ajax/update_order_status.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update UI
                    this.dataset.currentStatus = newStatus;
                    showNotification('Order status updated successfully', 'success');
                } else {
                    // Revert on error
                    this.value = currentStatus;
                    showNotification(data.error || 'Failed to update status', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                this.value = currentStatus;
                showNotification('Error updating status', 'error');
            })
            .finally(() => {
                this.disabled = false;
            });
        });
    });
    
    // Stock Status Update
    const stockSelects = document.querySelectorAll('.stock-status-select');
    
    stockSelects.forEach(select => {
        select.addEventListener('change', function() {
            const orderId = this.dataset.orderId;
            const newStockStatus = this.value;
            const currentStock = this.dataset.currentStock;
            
            // Confirm change
            if (!confirm(`Change order #${orderId} stock status from "${currentStock}" to "${newStockStatus}"?`)) {
                this.value = currentStock; // Revert
                return;
            }
            
            // Disable select during update
            this.disabled = true;
            
            // AJAX request
            const formData = new FormData();
            formData.append('order_id', orderId);
            formData.append('new_stock_status', newStockStatus);
            
            fetch('/admin/ajax/update_stock_status.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update UI
                    this.dataset.currentStock = newStockStatus;
                    showNotification('Stock status updated successfully', 'success');
                } else {
                    // Revert on error
                    this.value = currentStock;
                    showNotification(data.error || 'Failed to update stock status', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                this.value = currentStock;
                showNotification('Error updating stock status', 'error');
            })
            .finally(() => {
                this.disabled = false;
            });
        });
    });
    
    // Price Adjustment Form
    const adjustmentType = document.getElementById('adjustmentType');
    const adjustmentAmount = document.querySelector('input[name="adjustment_amount"]');
    const adjustmentHint = document.getElementById('adjustmentHint');
    
    if (adjustmentType) {
        adjustmentType.addEventListener('change', function() {
            if (this.value === 'override') {
                adjustmentHint.textContent = 'Enter the new total amount';
            } else if (this.value === 'add') {
                adjustmentHint.textContent = 'Enter amount to add';
            } else {
                adjustmentHint.textContent = 'Enter amount to subtract';
            }
        });
        
        if (adjustmentAmount) {
            adjustmentAmount.addEventListener('input', function() {
                updatePricePreview();
            });
        }
    }
    
    const priceAdjustForm = document.getElementById('priceAdjustForm');
    if (priceAdjustForm) {
        priceAdjustForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const orderId = formData.get('order_id');
            const currentTotal = parseFloat(document.getElementById('currentTotal').textContent.replace('RM ', '').replace(',', ''));
            const adjustmentType = formData.get('adjustment_type');
            const adjustmentAmount = parseFloat(formData.get('adjustment_amount'));
            
            // Calculate preview
            let newTotal = currentTotal;
            if (adjustmentType === 'add') {
                newTotal = currentTotal + adjustmentAmount;
            } else if (adjustmentType === 'subtract') {
                newTotal = currentTotal - adjustmentAmount;
            } else {
                newTotal = adjustmentAmount;
            }
            
            if (newTotal < 0) {
                alert('Final amount cannot be negative');
                return;
            }
            
            if (!confirm(`Adjust order #${orderId} price to RM ${newTotal.toFixed(2)}?`)) {
                return;
            }
            
            // Submit
            fetch('/admin/ajax/adjust_order_price.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification('Price adjusted successfully', 'success');
                    setTimeout(() => {
                        location.reload();
                    }, 1000);
                } else {
                    showNotification(data.error || 'Failed to adjust price', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('Error adjusting price', 'error');
            });
        });
    }
});

// Price Adjustment Modal Functions
function openPriceAdjust(orderId, currentTotal) {
    const modal = document.getElementById('priceAdjustModal');
    const form = document.getElementById('priceAdjustForm');
    
    document.getElementById('adjustOrderId').value = orderId;
    document.getElementById('currentTotal').textContent = 'RM ' + currentTotal.toFixed(2);
    form.reset();
    document.getElementById('adjustmentType').value = 'override';
    document.getElementById('adjustmentHint').textContent = 'Enter the new total amount';
    document.getElementById('adjustmentPreview').style.display = 'none';
    
    modal.style.display = 'flex';
}

function closePriceAdjustModal() {
    document.getElementById('priceAdjustModal').style.display = 'none';
}

function updatePricePreview() {
    const modal = document.getElementById('priceAdjustModal');
    if (modal.style.display === 'none') return;
    
    const currentTotal = parseFloat(document.getElementById('currentTotal').textContent.replace('RM ', '').replace(',', ''));
    const adjustmentType = document.getElementById('adjustmentType').value;
    const adjustmentAmount = parseFloat(document.querySelector('input[name="adjustment_amount"]').value) || 0;
    
    let newTotal = currentTotal;
    if (adjustmentType === 'add') {
        newTotal = currentTotal + adjustmentAmount;
    } else if (adjustmentType === 'subtract') {
        newTotal = currentTotal - adjustmentAmount;
    } else {
        newTotal = adjustmentAmount;
    }
    
    const preview = document.getElementById('adjustmentPreview');
    const previewText = document.getElementById('previewText');
    
    if (adjustmentAmount > 0) {
        preview.style.display = 'block';
        previewText.textContent = `New total: RM ${newTotal.toFixed(2)} (${newTotal >= currentTotal ? '+' : ''}RM ${(newTotal - currentTotal).toFixed(2)})`;
    } else {
        preview.style.display = 'none';
    }
}

// Delete Order Function
function deleteOrder(orderRef) {
    if (!confirm(`Are you sure you want to delete order "${orderRef}"?\n\nThis action cannot be undone and will delete all items in this order.`)) {
        return;
    }
    
    // Find the button that was clicked and disable it
    const buttons = document.querySelectorAll('button[onclick*="deleteOrder"]');
    buttons.forEach(btn => {
        if (btn.getAttribute('onclick').includes(orderRef)) {
            btn.disabled = true;
            btn.textContent = 'Deleting...';
        }
    });
    
    const formData = new FormData();
    formData.append('order_ref', orderRef);
    
    fetch('/admin/ajax/delete_order.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification(`Order "${orderRef}" deleted successfully`, 'success');
            // Reload page after a short delay to show the notification
            setTimeout(() => {
                location.reload();
            }, 1000);
        } else {
            // Re-enable button on error
            buttons.forEach(btn => {
                if (btn.getAttribute('onclick').includes(orderRef)) {
                    btn.disabled = false;
                    btn.textContent = 'Delete Order';
                }
            });
            showNotification(data.error || 'Failed to delete order', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        // Re-enable button on error
        buttons.forEach(btn => {
            if (btn.getAttribute('onclick').includes(orderRef)) {
                btn.disabled = false;
                btn.textContent = 'Delete Order';
            }
        });
        showNotification('Error deleting order', 'error');
    });
}

// Notification function (simple implementation)
function showNotification(message, type) {
    // Create notification element
    const notification = document.createElement('div');
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 1rem 1.5rem;
        background: ${type === 'success' ? '#10b981' : '#ef4444'};
        color: white;
        border-radius: 8px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        z-index: 10000;
        animation: slideIn 0.3s ease-out;
    `;
    notification.textContent = message;
    
    document.body.appendChild(notification);
    
    setTimeout(() => {
        notification.style.animation = 'slideOut 0.3s ease-out';
        setTimeout(() => {
            document.body.removeChild(notification);
        }, 300);
    }, 3000);
}

// Add CSS animations
const style = document.createElement('style');
style.textContent = `
    @keyframes slideIn {
        from {
            transform: translateX(100%);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }
    @keyframes slideOut {
        from {
            transform: translateX(0);
            opacity: 1;
        }
        to {
            transform: translateX(100%);
            opacity: 0;
        }
    }
`;
document.head.appendChild(style);

