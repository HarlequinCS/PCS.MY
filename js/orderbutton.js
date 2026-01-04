// Order Button Animation Handler
// Waits for jQuery to be available before executing
(function() {
    function initOrderButton() {
        // Check if jQuery is available
        if (typeof jQuery === 'undefined' || typeof $ === 'undefined') {
            // Retry after 100ms if jQuery not loaded yet
            setTimeout(initOrderButton, 100);
            return;
        }
        
        // Use jQuery ready to ensure DOM is loaded
        jQuery(document).ready(function($) {
            $('.order').click(function (e) {
                let button = $(this);
                
                if (!button.hasClass('animate')) {
                    button.addClass('animate');
                    setTimeout(() => {
                        button.removeClass('animate');
                    }, 10000);
                }
            });
        });
    }
    
    // Start initialization
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initOrderButton);
    } else {
        initOrderButton();
    }
})();
