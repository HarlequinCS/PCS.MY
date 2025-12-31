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

// ============================================
// STEP 2: Determine Status Message
// ============================================
// Status ID: 1 = Success, 2 = Pending, 3 = Failed
$statusMessage = '';
$statusClass = '';

switch ($statusId) {
    case 1:
        $statusMessage = 'Payment Successful 🎉';
        $statusClass = 'success';
        break;
    case 2:
        $statusMessage = 'Payment Pending ⏳';
        $statusClass = 'pending';
        break;
    case 3:
        $statusMessage = 'Payment Failed ❌';
        $statusClass = 'failed';
        break;
    default:
        $statusMessage = 'Unknown Payment Status';
        $statusClass = 'unknown';
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
    <title>Payment Status</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        .container {
            background: white;
            border-radius: 12px;
            padding: 40px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            text-align: center;
            max-width: 500px;
            width: 100%;
        }
        .status-icon {
            font-size: 64px;
            margin-bottom: 20px;
        }
        .status-message {
            font-size: 24px;
            font-weight: 600;
            margin-bottom: 20px;
            color: #333;
        }
        .status-success {
            color: #10b981;
        }
        .status-pending {
            color: #f59e0b;
        }
        .status-failed {
            color: #ef4444;
        }
        .status-unknown {
            color: #6b7280;
        }
        .info {
            color: #6b7280;
            font-size: 14px;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #e5e7eb;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="status-icon"><?php 
            echo $statusId == 1 ? '✅' : ($statusId == 2 ? '⏳' : ($statusId == 3 ? '❌' : '❓'));
        ?></div>
        <div class="status-message status-<?php echo $statusClass; ?>">
            <?php echo htmlspecialchars($statusMessage); ?>
        </div>
        <div class="info">
            Status ID: <?php echo $statusId; ?><br>
            <small>Please wait for payment confirmation via email.</small>
        </div>
    </div>
</body>
</html>

