<?php
/**
 * ToyyibPay SANDBOX / Staging Payment Return URL Handler
 * Displays payment status to user after redirect from ToyyibPay
 * 
 * IMPORTANT: Page ini hanya untuk user experience sahaja
 * Jangan guna status_id dari sini untuk validate payment
 * Gunakan callback.php sebagai sumber kebenaran sebenar
 */

// ============================================
// STEP 1: Start Session
// ============================================
session_start();

// ============================================
// STEP 2: Get Payment Status from GET Parameter
// ============================================
// ToyyibPay akan redirect user ke sini dengan status_id
$statusId = isset($_GET['status_id']) ? (int)$_GET['status_id'] : 0;

// ============================================
// STEP 3: Clear Cart if Payment Successful
// ============================================
// Clear cart session after successful payment
if ($statusId == 1 && isset($_SESSION['cart'])) {
    unset($_SESSION['cart']);
    $_SESSION['cart'] = [];
}

// Get additional parameters if available
$billCode = isset($_GET['billcode']) ? htmlspecialchars($_GET['billcode']) : '';
$orderId = isset($_GET['order_id']) ? htmlspecialchars($_GET['order_id']) : '';

// ============================================
// STEP 2: Determine Status Message
// ============================================
// Status ID: 1 = Success, 2 = Pending, 3 = Failed
$statusMessage = '';
$statusClass = '';
$statusIcon = '';
$statusDescription = '';

switch ($statusId) {
    case 1:
        $statusMessage = 'Payment Successful!';
        $statusClass = 'success';
        $statusIcon = '✅';
        $statusDescription = 'Your order has been confirmed and payment has been processed successfully.';
        break;
    case 2:
        $statusMessage = 'Payment Pending';
        $statusClass = 'pending';
        $statusIcon = '⏳';
        $statusDescription = 'Your payment is being processed. Please wait for confirmation.';
        break;
    case 3:
        $statusMessage = 'Payment Failed';
        $statusClass = 'failed';
        $statusIcon = '❌';
        $statusDescription = 'Unfortunately, your payment could not be processed. Please try again.';
        break;
    default:
        $statusMessage = 'Payment Status Unknown';
        $statusClass = 'unknown';
        $statusIcon = '❓';
        $statusDescription = 'Unable to determine payment status. Please contact support.';
        break;
}

// ============================================
// STEP 3: Include Version Configuration
// ============================================
require_once __DIR__ . '/config/version.php';

// ============================================
// STEP 4: Display HTML Page
// ============================================
?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">
    <title>Payment Status - <?php echo htmlspecialchars($statusMessage); ?> | Projek Cuti Sem</title>
    <link rel="icon" type="image/x-icon" href="https://projectcutisem.com/favicon.ico">
    <link rel="shortcut icon" type="image/x-icon" href="https://projectcutisem.com/favicon.ico">
    <link rel="apple-touch-icon" href="https://projectcutisem.com/apple-touch-icon.png">
    
    <!-- Custom CSS: Order Baju Styles -->
    <link rel="stylesheet" href="<?php echo getVersionedAsset('css/order-baju.css'); ?>">
    
    <!-- Custom CSS: Order Button Animation -->
    <link rel="stylesheet" href="<?php echo getVersionedAsset('css/orderbutton.css'); ?>">
    
    <!-- Return Page Specific Styles -->
    <link rel="stylesheet" href="<?php echo getVersionedAsset('css/return.css'); ?>">
    
</head>
<body>
    <!-- Main Content -->
    <main class="return-main">
        <!-- Logo -->
        <div class="return-logo">
            <a href="/" class="nav-logo-link">
                <figure class="nav-logo">
                    <img src="images/PCS logo.svg" alt="Projek Cuti Sem" width="40" height="40" fetchpriority="high">
                </figure>
                <span class="nav-brand-text">PROJEK CUTI SEM</span>
            </a>
        </div>
        <div class="return-container">
            <div class="status-card">
        <!-- Status Icon -->
        <div class="status-icon"><?php echo $statusIcon; ?></div>
        
        <!-- Status Message -->
        <h1 class="status-message <?php echo $statusClass; ?>">
            <?php echo htmlspecialchars($statusMessage); ?>
        </h1>
        
        <!-- Status Description -->
        <p class="status-description">
            <?php echo htmlspecialchars($statusDescription); ?>
        </p>
        
        <!-- Order Details (only show if status is success and we have order info) -->
        <?php if ($statusId == 1 && ($billCode || $orderId)): ?>
        <div class="order-details">
            <h3>Order Information</h3>
            <?php if ($orderId): ?>
            <div class="detail-row">
                <span class="detail-label">Order ID:</span>
                <span class="detail-value"><?php echo $orderId; ?></span>
            </div>
            <?php endif; ?>
            <?php if ($billCode): ?>
            <div class="detail-row">
                <span class="detail-label">Bill Code:</span>
                <span class="detail-value"><?php echo $billCode; ?></span>
            </div>
            <?php endif; ?>
            <div class="detail-row">
                <span class="detail-label">Status:</span>
                <span class="detail-value" style="color: #10b981;">Confirmed</span>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Complete Order Button (only show for success status) -->
        <?php if ($statusId == 1): ?>
        <div class="button-container">
            <button class="order">
                <span class="default">Complete Order</span>
                <span class="success">Order Placed
                    <svg viewbox="0 0 12 10">
                        <polyline points="1.5 6 4.5 9 10.5 1"></polyline>
                    </svg>
                </span>
                <div class="box"></div>
                <div class="truck">
                    <div class="back">
                        <div class="logo">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1449.37 877.28">
                                <defs>
                                    <style>
                                        .cls-1 {
                                            fill: #fff;
                                        }
                                        .cls-2 {
                                            fill: red;
                                        }
                                    </style>
                                </defs>
                                <g id="Front_design" data-name="Front design">
                                    <g>
                                        <g>
                                            <path class="cls-1" d="M226.14,877.28C101.45,877.28,0,777.59,0,655.05V222.23C0,99.69,101.45,0,226.14,0h997.09c124.69,0,226.14,99.69,226.14,222.23v432.83c0,122.53-101.45,222.22-226.14,222.22H226.14Z"/>
                                            <path class="cls-2" d="M1223.23,844.34H226.14c-106.38,0-192.62-84.74-192.62-189.28V222.23c0-104.54,86.24-189.28,192.62-189.28h997.09c106.38,0,192.62,84.74,192.62,189.28v432.83c0,104.54-86.24,189.28-192.62,189.28Z"/>
                                        </g>
                                        <g>
                                            <path class="cls-1" d="M188.93,558.39c-4.03,0-7.85-1.79-10.4-4.86-2.55-3.08-3.55-7.11-2.73-10.99l78.39-371.63c1.29-6.12,6.77-10.5,13.13-10.5h152.02c20.98,0,39.04,2.2,53.66,6.55,14.85,4.42,27.45,10.35,37.46,17.64,10.29,7.49,18.37,16.19,24.04,25.89,5.36,9.18,9.3,18.63,11.71,28.09,2.32,9.13,3.7,18.24,4.09,27.09.36,8.14.54,15.34.54,21.39,0,.79,0,1.03-.84,10.51-.62,6.88-2.35,14.83-5.3,24.31-2.89,9.3-7.46,19.46-13.58,30.18-6.4,11.22-15.45,21.72-26.87,31.21-11.42,9.48-25.9,17.36-43.06,23.41-17.08,6.04-38.13,9.1-62.53,9.1h-102.44l-27.96,132.12c-1.29,6.11-6.78,10.49-13.13,10.49h-66.21ZM396.55,336.33c12.84,0,23.35-1.79,31.26-5.32,7.66-3.42,13.63-7.79,17.74-13,4.39-5.58,7.43-11.62,9.29-18.48,1.98-7.34,2.99-14.7,2.99-21.85,0-4.04-.65-8.51-1.94-13.28-1.09-4.06-3.17-7.86-6.34-11.6-2.96-3.48-7.61-6.5-13.81-8.98-6.65-2.64-15.92-3.98-27.54-3.98h-74.89l-20.38,96.48h83.62Z"/>
                                            <path class="cls-1" d="M725.95,567.24c-28.82,0-54.75-4.53-77.09-13.46-22.5-8.99-41.71-21.6-57.1-37.47-15.33-15.82-27.17-34.68-35.18-56.06-7.92-21.15-11.94-44.22-11.94-68.57,0-40.41,6.3-76.14,18.73-106.19,12.46-30.17,29.16-55.48,49.63-75.23,20.48-19.75,43.96-34.65,69.78-44.29,25.61-9.55,51.89-14.39,78.12-14.39,23.52,0,45.57,3.19,65.53,9.48,20.34,6.41,38.01,15.84,52.55,28.02,14.8,12.43,26.28,28.1,34.13,46.6,7.77,18.33,11.32,39.26,10.56,62.23-.24,7.11-6.17,12.75-13.4,12.75h-66.21c-7.41,0-13.41-5.9-13.41-13.18,0-22.43-5.81-38.7-17.76-49.74-12.01-11.1-30.22-16.72-54.11-16.72-19.99,0-37.51,4.43-52.07,13.16-15.03,9.02-27.71,21.01-37.69,35.65-10.27,15.06-18.12,32.32-23.35,51.28-5.32,19.23-8.01,38.91-8.01,58.49,0,31.2,7.94,55.74,23.59,72.93,15.49,17.01,37.35,25.28,66.81,25.28,14.56,0,26.79-2.67,36.34-7.94,10.11-5.57,18.52-12.38,25.01-20.24,6.72-8.14,11.93-16.57,15.49-25.07,3.83-9.18,6.59-16.74,8.22-22.46,1.61-5.7,6.89-9.64,12.92-9.64h66.21c4.07,0,7.91,1.81,10.45,4.92,2.55,3.11,3.52,7.19,2.65,11.09-5.57,24.87-14.13,46.86-25.43,65.36-11.37,18.62-25.17,34.35-41.02,46.73-15.88,12.41-33.72,21.75-53.03,27.75-19.08,5.93-39.24,8.94-59.92,8.94Z"/>
                                            <path class="cls-1" d="M1071.6,567.24c-23.37,0-44.92-2.71-64.05-8.05-19.81-5.53-36.94-14.31-50.89-26.11-14.17-11.95-25.06-27.47-32.37-46.11-7.15-18.2-10.39-40.06-9.63-64.98.22-7.12,6.15-12.78,13.4-12.78h63.56c3.67,0,7.18,1.48,9.71,4.09,2.53,2.61,3.86,6.13,3.69,9.73-1.19,24.06,4.33,40.8,16.4,49.75,13.44,9.98,32.63,15.03,57.06,15.03,7.86,0,16.47-.96,25.57-2.84,8.6-1.78,16.41-4.68,23.25-8.62,6.56-3.79,11.9-8.55,16.31-14.56,3.82-5.21,5.68-11.8,5.68-20.14,0-7.62-1.68-13.6-5.13-18.3-3.97-5.38-9.34-10-15.95-13.74-7.37-4.16-16.16-7.79-26.12-10.8-10.89-3.28-22.16-6.48-33.49-9.52-8.44-2.07-18.88-5.04-30.73-8.74-12.81-4.01-25.33-9.96-37.24-17.71-12.41-8.05-23.06-18.7-31.64-31.64-9-13.56-13.56-30.69-13.56-50.91,0-18.42,3.59-35.79,10.67-51.63,7.12-15.95,17.56-29.86,31.03-41.35,13.28-11.33,29.69-20.24,48.77-26.49,18.77-6.15,40.25-9.27,63.84-9.27,20.75,0,40.54,2.23,58.8,6.63,18.99,4.57,35.76,12.15,49.88,22.55,14.44,10.65,25.81,24.69,33.79,41.73,7.91,16.91,11.53,37.56,10.76,61.36-.23,7.11-6.16,12.76-13.4,12.76h-63.56c-7.33,0-13.3-5.78-13.41-12.98-.29-19.08-5.23-32.62-14.68-40.23-10.2-8.21-26.77-12.37-49.24-12.37-3.99,0-9.81.49-17.34,1.45-6.67.85-13.51,2.91-20.32,6.11-6.4,3.01-11.85,7.24-16.65,12.92-3.92,4.65-5.91,11.71-5.91,20.99,0,6.3,1.28,11.13,3.9,14.77,3.11,4.34,7.5,8.14,13.04,11.33,6.28,3.61,13.75,6.69,22.2,9.15,9.26,2.7,19.16,5.26,29.42,7.61,13.9,3.59,27.73,7.5,41.47,11.7,14.8,4.54,28.47,10.97,40.65,19.13,12.81,8.61,23.3,20,31.17,33.88,7.97,14.07,12.02,32.17,12.02,53.79,0,16.15-3.16,32.3-9.4,47.99-6.34,16-16.66,30.59-30.64,43.37-13.8,12.61-31.73,22.9-53.29,30.58-21.35,7.61-47.39,11.46-77.4,11.46Z"/>
                                        </g>
                                        <g>
                                            <path class="cls-1" d="M441.03,775.06c-3.04,0-5.7-2.01-6.48-4.91l-14.61-54.33-55.29-14.36c-2.94-.77-4.99-3.38-4.99-6.37s2.05-5.61,4.99-6.37l55.29-14.36,14.61-54.32c.78-2.89,3.44-4.91,6.48-4.91s5.7,2.01,6.48,4.91l14.61,54.32,55.28,14.36c2.94.76,4.99,3.38,4.99,6.37s-2.05,5.6-4.99,6.37l-55.28,14.36-14.61,54.33c-.78,2.89-3.44,4.91-6.48,4.91Z"/>
                                            <path class="cls-1" d="M724.69,775.06c-3.04,0-5.7-2.01-6.48-4.91l-14.61-54.33-55.28-14.36c-2.94-.77-4.99-3.38-4.99-6.37s2.05-5.61,4.99-6.37l55.28-14.36,14.61-54.32c.78-2.89,3.44-4.91,6.48-4.91s5.7,2.01,6.48,4.91l14.61,54.32,55.28,14.36c2.94.76,4.99,3.38,4.99,6.37s-2.05,5.6-4.99,6.37l-55.28,14.36-14.61,54.33c-.78,2.89-3.44,4.91-6.48,4.91Z"/>
                                            <path class="cls-1" d="M1008.34,775.06c-3.04,0-5.7-2.01-6.48-4.91l-14.61-54.33-55.29-14.36c-2.94-.77-4.99-3.38-4.99-6.37s2.05-5.61,4.99-6.37l55.29-14.36,14.61-54.32c.78-2.89,3.44-4.91,6.48-4.91s5.7,2.01,6.48,4.91l14.61,54.32,55.29,14.36c2.94.76,4.99,3.38,4.99,6.37s-2.05,5.6-4.99,6.37l-55.29,14.36-14.61,54.33c-.78,2.89-3.44,4.91-6.48,4.91h0Z"/>
                                        </g>
                                    </g>
                                </g>
                            </svg>
                        </div>
                    </div>
                    <div class="front">
                        <div class="window"></div>
                    </div>
                    <div class="light top"></div>
                    <div class="light bottom"></div>
                </div>
                <div class="lines"></div>
            </button>
        </div>
        <?php endif; ?>
        
        <!-- Info Message -->
        <div class="info-message">
            <?php if ($statusId == 1): ?>
                <strong>What's Next?</strong><br>
                You will receive an email confirmation shortly. Your order is being processed and will be shipped soon.
            <?php elseif ($statusId == 2): ?>
                <strong>Please Wait</strong><br>
                Your payment is being verified. This may take a few minutes. You will receive an email once the payment is confirmed.
            <?php elseif ($statusId == 3): ?>
                <strong>Need Help?</strong><br>
                If you continue to experience issues, please contact our support team or try again later.
            <?php else: ?>
                <strong>Contact Support</strong><br>
                If you have any questions about your order, please contact our support team with your order details.
            <?php endif; ?>
            </div>
        </div>
    </main>
    
    <!-- jQuery (must be loaded before orderbutton.js) -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
    <script>
        // Fallback if jQuery CDN fails
        if (typeof jQuery === 'undefined') {
            console.error('jQuery failed to load from CDN');
        }
    </script>
    
    <!-- Custom JavaScript: Order Button Animation -->
    <script src="<?php echo getVersionedAsset('js/orderbutton.js'); ?>"></script>
    <script>
        // Verify orderbutton.js loaded
        document.addEventListener('DOMContentLoaded', function() {
            const orderButton = document.querySelector('.order');
            if (orderButton && typeof jQuery !== 'undefined') {
                console.log('Order button and jQuery ready');
            } else {
                console.error('Order button or jQuery not available', {
                    button: !!orderButton,
                    jQuery: typeof jQuery !== 'undefined'
                });
            }
        });
    </script>
    
    <!-- Additional JavaScript for page interactions -->
    <script>
        // Console log for debugging
        // Return page loaded - status check complete
        
        // Homepage URL (adjust as needed)
        const homepageUrl = '/';
        let countdownStarted = false;
        let countdownInterval = null;
        
        // Countdown and redirect function
        function startCountdownAndRedirect(seconds) {
            if (countdownStarted) {
                return; // Prevent multiple countdowns
            }
            
            countdownStarted = true;
            const orderButton = document.querySelector('.order');
            const defaultText = orderButton.querySelector('.default');
            let countdown = seconds;
            
            // Update button text with countdown
            countdownInterval = setInterval(function() {
                if (countdown > 0) {
                    if (defaultText) {
                        defaultText.textContent = `Redirecting in ${countdown}s...`;
                    }
                    // Countdown active
                    countdown--;
                } else {
                    clearInterval(countdownInterval);
                    // Redirecting to homepage
                    // Redirect to homepage
                    window.location.href = homepageUrl;
                }
            }, 1000);
        }
        
        <?php if ($statusId == 1): ?>
        // Handle button click (both manual and auto-trigger)
        document.addEventListener('DOMContentLoaded', function() {
            const orderButton = document.querySelector('.order');
            
            if (orderButton) {
                // Listen for button click events (from orderbutton.js)
                // The orderbutton.js will add 'animate' class on click
                // We'll watch for that class change to trigger countdown
                const observer = new MutationObserver(function(mutations) {
                    mutations.forEach(function(mutation) {
                        if (mutation.type === 'attributes' && mutation.attributeName === 'class') {
                            if (orderButton.classList.contains('animate') && !countdownStarted) {
                                // Order button animation started
                                
                                // Start countdown after animation completes (10 seconds)
                                setTimeout(function() {
                                    if (!countdownStarted) {
                                        // Starting countdown before redirect
                                        startCountdownAndRedirect(3);
                                    }
                                }, 10000); // Wait for animation to complete (10 seconds)
                            }
                        }
                    });
                });
                
                // Observe the button for class changes
                observer.observe(orderButton, {
                    attributes: true,
                    attributeFilter: ['class']
                });
                
                // Auto-trigger button animation on page load
                setTimeout(function() {
                    // Auto-triggering order button animation
                    orderButton.click();
                }, 1000);
            }
        });
        <?php endif; ?>
    </script>
</body>
</html>
