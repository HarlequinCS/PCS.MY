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
// STEP 1: Get Payment Status from GET Parameter
// ============================================
// ToyyibPay akan redirect user ke sini dengan status_id
$statusId = isset($_GET['status_id']) ? (int)$_GET['status_id'] : 0;

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
// STEP 3: Display HTML Page
// ============================================
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Status - Order Confirmation</title>
    
    <!-- Meyer Reset CSS -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/meyer-reset/2.0/reset.min.css">
    
    <!-- Google Fonts: Roboto -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Custom CSS: Order Baju Styles -->
    <link rel="stylesheet" href="css/order-baju.css">
    
    <!-- Custom CSS: Order Button Animation -->
    <link rel="stylesheet" href="css/orderbutton.css">
    
    <!-- Additional Styles for Return Page -->
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Roboto', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
            position: relative;
            overflow-x: hidden;
        }
        
        /* Animated Background Elements */
        body::before,
        body::after {
            content: '';
            position: absolute;
            width: 300px;
            height: 300px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.1);
            animation: float 20s infinite ease-in-out;
        }
        
        body::before {
            top: -150px;
            left: -150px;
            animation-delay: 0s;
        }
        
        body::after {
            bottom: -150px;
            right: -150px;
            animation-delay: 10s;
        }
        
        @keyframes float {
            0%, 100% {
                transform: translate(0, 0) scale(1);
            }
            50% {
                transform: translate(50px, 50px) scale(1.1);
            }
        }
        
        /* Success Card Container */
        .success-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            padding: 50px 40px;
            max-width: 600px;
            width: 100%;
            text-align: center;
            position: relative;
            z-index: 1;
            animation: slideUp 0.6s ease-out;
        }
        
        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        /* Status Icon */
        .status-icon {
            font-size: 80px;
            margin-bottom: 20px;
            display: block;
            animation: scaleIn 0.5s ease-out 0.2s both;
        }
        
        @keyframes scaleIn {
            from {
                transform: scale(0);
            }
            to {
                transform: scale(1);
            }
        }
        
        /* Status Message */
        .status-message {
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 15px;
            color: #1a202c;
            animation: fadeIn 0.6s ease-out 0.3s both;
        }
        
        .status-message.success {
            color: #10b981;
        }
        
        .status-message.pending {
            color: #f59e0b;
        }
        
        .status-message.failed {
            color: #ef4444;
        }
        
        .status-message.unknown {
            color: #6b7280;
        }
        
        @keyframes fadeIn {
            from {
                opacity: 0;
            }
            to {
                opacity: 1;
            }
        }
        
        /* Status Description */
        .status-description {
            font-size: 16px;
            color: #6b7280;
            line-height: 1.6;
            margin-bottom: 40px;
            animation: fadeIn 0.6s ease-out 0.4s both;
        }
        
        /* Order Details Section */
        .order-details {
            background: #f9fafb;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 40px;
            text-align: left;
            animation: fadeIn 0.6s ease-out 0.5s both;
        }
        
        .order-details h3 {
            font-size: 18px;
            font-weight: 600;
            color: #1a202c;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #e5e7eb;
        }
        
        .detail-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .detail-row:last-child {
            border-bottom: none;
        }
        
        .detail-label {
            font-weight: 500;
            color: #6b7280;
            font-size: 14px;
        }
        
        .detail-value {
            font-weight: 600;
            color: #1a202c;
            font-size: 14px;
        }
        
        /* Button Container */
        .button-container {
            display: flex;
            justify-content: center;
            align-items: center;
            margin-top: 30px;
            animation: fadeIn 0.6s ease-out 0.6s both;
        }
        
        /* Info Message */
        .info-message {
            margin-top: 30px;
            padding-top: 25px;
            border-top: 1px solid #e5e7eb;
            font-size: 14px;
            color: #6b7280;
            line-height: 1.6;
            animation: fadeIn 0.6s ease-out 0.7s both;
        }
        
        .info-message strong {
            color: #1a202c;
        }
        
        /* Responsive Design */
        @media (max-width: 768px) {
            .success-card {
                padding: 40px 30px;
                margin: 10px;
            }
            
            .status-icon {
                font-size: 60px;
            }
            
            .status-message {
                font-size: 24px;
            }
            
            .status-description {
                font-size: 14px;
            }
            
            .order-details {
                padding: 20px;
            }
            
            .detail-row {
                flex-direction: column;
                gap: 5px;
            }
        }
        
        @media (max-width: 480px) {
            .success-card {
                padding: 30px 20px;
            }
            
            .status-message {
                font-size: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="success-card">
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
                    <div class="back"></div>
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
    
    <!-- jQuery (must be loaded before orderbutton.js) -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
    
    <!-- Custom JavaScript: Order Button Animation -->
    <script src="js/orderbutton.js"></script>
    
    <!-- Additional JavaScript for page interactions -->
    <script>
        // Console log for debugging
        console.log('✅ Return page loaded');
        console.log('Status ID:', <?php echo $statusId; ?>);
        console.log('Status:', '<?php echo $statusClass; ?>');
        
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
                    console.log('⏱️ Countdown:', countdown);
                    countdown--;
                } else {
                    clearInterval(countdownInterval);
                    console.log('🏠 Redirecting to homepage');
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
                                console.log('🚚 Order button animation started');
                                
                                // Start countdown after animation completes (10 seconds)
                                setTimeout(function() {
                                    if (!countdownStarted) {
                                        console.log('⏱️ Starting 3 second countdown before redirect');
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
                    console.log('🚚 Auto-triggering order button animation');
                    orderButton.click();
                }, 1000);
            }
        });
        <?php endif; ?>
    </script>
</body>
</html>
