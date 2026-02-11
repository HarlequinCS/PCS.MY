/**
 * Admin Panel JavaScript
 */

let currentFilter = 'pending';

// Initialize
document.addEventListener('DOMContentLoaded', () => {
    loadComments('pending');
    setupEventListeners();
    setupMobileMenu();
});

// Mobile Menu Setup
function setupMobileMenu() {
    const mobileMenuToggle = document.getElementById('mobileMenuToggle');
    const sidebar = document.getElementById('sidebar');
    const sidebarOverlay = document.getElementById('sidebarOverlay');
    
    if (!mobileMenuToggle || !sidebar || !sidebarOverlay) {
        console.error('Mobile menu elements not found');
        return;
    }
    
    // Toggle sidebar - use 'open' class to match CSS
    const toggleMenu = function(e) {
        if (e) {
            e.preventDefault();
            e.stopPropagation();
        }
        const isOpen = sidebar.classList.contains('open');
        if (isOpen) {
            sidebar.classList.remove('open');
            sidebarOverlay.classList.remove('open');
            mobileMenuToggle.classList.remove('active');
        } else {
            sidebar.classList.add('open');
            sidebarOverlay.classList.add('open');
            mobileMenuToggle.classList.add('active');
        }
    };
    
    // Use multiple event types for maximum compatibility
    mobileMenuToggle.addEventListener('click', toggleMenu, true);
    mobileMenuToggle.addEventListener('touchend', function(e) {
        e.preventDefault();
        toggleMenu(e);
    }, { passive: false });
    mobileMenuToggle.addEventListener('touchstart', function(e) {
        // Prevent double-firing, but allow touch
        e.stopPropagation();
    }, { passive: true });
    
    // Close sidebar when clicking overlay
    const closeMenu = function(e) {
        if (e) {
            e.preventDefault();
            e.stopPropagation();
        }
        sidebar.classList.remove('open');
        sidebarOverlay.classList.remove('open');
        mobileMenuToggle.classList.remove('active');
    };
    
    sidebarOverlay.addEventListener('click', closeMenu);
    sidebarOverlay.addEventListener('touchend', function(e) {
        e.preventDefault();
        closeMenu(e);
    }, { passive: false });
    
    // Close sidebar when clicking outside on mobile
    document.addEventListener('click', function(e) {
        if (window.innerWidth <= 768) {
            if (!sidebar.contains(e.target) && 
                !mobileMenuToggle.contains(e.target) && 
                sidebar.classList.contains('open')) {
                closeMenu(e);
            }
        }
    });
    
    // Handle window resize
    window.addEventListener('resize', function() {
        if (window.innerWidth > 768) {
            sidebar.classList.remove('open');
            sidebarOverlay.classList.remove('open');
            mobileMenuToggle.classList.remove('active');
        }
    });
    
    // Force button to be interactive
    mobileMenuToggle.style.pointerEvents = 'auto';
    mobileMenuToggle.style.cursor = 'pointer';
    
    // Mobile menu button initialized
}

function setupEventListeners() {
    // Tab buttons
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const filter = btn.dataset.filter;
            switchFilter(filter);
        });
    });
}

function switchFilter(filter) {
    currentFilter = filter;
    
    // Update active tab
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.classList.remove('active');
        if (btn.dataset.filter === filter) {
            btn.classList.add('active');
        }
    });
    
    loadComments(filter);
}

async function loadComments(filter = 'pending') {
    const container = document.getElementById('commentsContainer');
    container.innerHTML = '<div class="loading-spinner"><div class="spinner"></div><p>Loading comments...</p></div>';
    
    try {
        const response = await fetch(`/admin/api/get_comments.php?filter=${filter}`);
        const data = await response.json();
        
        if (data.success) {
            updateStats(data.counts);
            displayComments(data.comments, filter);
        } else {
            container.innerHTML = '<div class="empty-state"><div class="empty-state-icon">⚠️</div><h3>Error</h3><p>Failed to load comments</p></div>';
        }
    } catch (error) {
        console.error('Error loading comments:', error);
        container.innerHTML = '<div class="empty-state"><div class="empty-state-icon">⚠️</div><h3>Error</h3><p>Network error. Please try again.</p></div>';
    }
}

function updateStats(counts) {
    document.getElementById('pendingCount').textContent = counts.pending;
    document.getElementById('approvedCount').textContent = counts.approved;
    document.getElementById('totalCount').textContent = counts.total;
}

function displayComments(comments, filter) {
    const container = document.getElementById('commentsContainer');
    
    if (!comments || comments.length === 0) {
        const message = filter === 'pending' ? 'No pending comments' : 
                       filter === 'approved' ? 'No approved comments' : 
                       'No comments found';
        container.innerHTML = `
            <div class="empty-state">
                <div class="empty-state-icon">📝</div>
                <h3>${message}</h3>
                <p>All caught up!</p>
            </div>
        `;
        return;
    }
    
    container.innerHTML = comments.map(comment => {
        const date = new Date(comment.created_at);
        const formattedDate = date.toLocaleString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
        
        const statusClass = comment.status || 'pending';
        const statusLabel = (comment.status || 'pending').charAt(0).toUpperCase() + (comment.status || 'pending').slice(1);
        
        let actionButtons = '';
        if (comment.status === 'pending') {
            actionButtons = `
                <button class="btn btn-success btn-sm" onclick="approveComment(${comment.comment_id})">
                    ✓ Approve
                </button>
                <button class="btn btn-danger btn-sm" onclick="rejectComment(${comment.comment_id})">
                    ✗ Reject
                </button>
            `;
        } else if (comment.status === 'approved') {
            actionButtons = `
                <button class="btn btn-danger btn-sm" onclick="rejectComment(${comment.comment_id})">
                    ✗ Reject
                </button>
            `;
        }
        
        return `
            <div class="comment-card ${statusClass}" id="comment-${comment.comment_id}">
                <div class="comment-header">
                    <div class="comment-meta">
                        <div class="comment-id">Comment #${comment.comment_id}</div>
                        <div class="comment-date">🕒 ${formattedDate}</div>
                    </div>
                    <span class="comment-status ${statusClass}">${statusLabel}</span>
                </div>
                <div class="comment-text">${escapeHtml(comment.comment)}</div>
                <div class="comment-footer">
                    <div class="comment-ip">IP: ${comment.ip_address || 'N/A'}</div>
                    <div class="comment-actions">
                        ${actionButtons}
                    </div>
                </div>
            </div>
        `;
    }).join('');
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

async function approveComment(commentId) {
    if (!confirm('Are you sure you want to approve this comment?')) {
        return;
    }
    
    try {
        const response = await fetch('/admin/api/approve_comment.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                comment_id: commentId,
                action: 'approve'
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            // Remove from view if on pending filter
            if (currentFilter === 'pending') {
                const card = document.getElementById(`comment-${commentId}`);
                if (card) {
                    card.style.opacity = '0';
                    card.style.transform = 'translateX(-20px)';
                    setTimeout(() => {
                        loadComments(currentFilter);
                    }, 300);
                }
            } else {
                loadComments(currentFilter);
            }
            
            showNotification('Comment approved successfully!', 'success');
        } else {
            showNotification(data.error || 'Failed to approve comment', 'error');
        }
    } catch (error) {
        console.error('Error approving comment:', error);
        showNotification('Network error. Please try again.', 'error');
    }
}

async function rejectComment(commentId) {
    if (!confirm('Are you sure you want to reject this comment?')) {
        return;
    }
    
    try {
        const response = await fetch('/admin/api/approve_comment.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                comment_id: commentId,
                action: 'reject'
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            // Remove from view
            const card = document.getElementById(`comment-${commentId}`);
            if (card) {
                card.style.opacity = '0';
                card.style.transform = 'translateX(20px)';
                setTimeout(() => {
                    loadComments(currentFilter);
                }, 300);
            }
            
            showNotification('Comment rejected', 'success');
        } else {
            showNotification(data.error || 'Failed to reject comment', 'error');
        }
    } catch (error) {
        console.error('Error rejecting comment:', error);
        showNotification('Network error. Please try again.', 'error');
    }
}

function showNotification(message, type) {
    // Create notification element
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.textContent = message;
    
    // Responsive positioning for mobile
    const isMobile = window.innerWidth <= 768;
    const topPosition = isMobile ? '15px' : '20px';
    const rightPosition = isMobile ? '15px' : '20px';
    const padding = isMobile ? '12px 16px' : '15px 20px';
    const fontSize = isMobile ? '0.9rem' : '1rem';
    
    notification.style.cssText = `
        position: fixed;
        top: ${topPosition};
        right: ${rightPosition};
        left: ${isMobile ? '15px' : 'auto'};
        padding: ${padding};
        background: ${type === 'success' ? '#10b981' : '#ef4444'};
        color: white;
        border-radius: 8px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        z-index: 10000;
        font-size: ${fontSize};
        max-width: ${isMobile ? 'calc(100% - 30px)' : '400px'};
        animation: slideInRight 0.3s ease-out;
    `;
    
    document.body.appendChild(notification);
    
    setTimeout(() => {
        notification.style.opacity = '0';
        notification.style.transform = 'translateX(100px)';
        setTimeout(() => {
            notification.remove();
        }, 300);
    }, 3000);
}

// Auto-refresh every 30 seconds
setInterval(() => {
    if (currentFilter === 'pending') {
        loadComments('pending');
    }
}, 30000);

