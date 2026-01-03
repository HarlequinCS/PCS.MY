<?php
/**
 * Session Flush Script
 * Private script to clear/flush PHP sessions
 * Requires password authentication
 */

// Prevent direct access without password
session_start();

// Configuration - CHANGE THIS PASSWORD!
define('FLUSH_SESSION_PASSWORD', 'PCS@dmin2025!'); // Change this to a strong password

// Security: Only allow access from localhost or specific IPs (optional)
$allowedIPs = ['127.0.0.1', '::1']; // Add your server IP if needed
// Uncomment the line below to restrict access by IP
// if (!in_array($_SERVER['REMOTE_ADDR'], $allowedIPs)) {
//     die('Access denied: IP not allowed');
// }

$message = '';
$messageType = '';

// Handle password submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedPassword = $_POST['password'] ?? '';
    
    if ($submittedPassword === FLUSH_SESSION_PASSWORD) {
        $action = $_POST['action'] ?? 'flush_all';
        
        if ($action === 'flush_all') {
            // Clear all session data
            $_SESSION = [];
            
            // Delete session cookie
            if (ini_get("session.use_cookies")) {
                $params = session_get_cookie_params();
                setcookie(session_name(), '', time() - 42000,
                    $params["path"], $params["domain"],
                    $params["secure"], $params["httponly"]
                );
            }
            
            // Destroy session
            session_destroy();
            
            $message = 'All sessions have been flushed successfully.';
            $messageType = 'success';
            
            // Start new session for this script
            session_start();
            $_SESSION['flush_authenticated'] = true;
            
        } elseif ($action === 'clear_cart_sessions') {
            // Clear cart data from all sessions (requires session file manipulation)
            $sessionPath = session_save_path();
            if (empty($sessionPath)) {
                $sessionPath = sys_get_temp_dir();
            }
            
            $clearedCount = 0;
            if (is_dir($sessionPath)) {
                $files = glob($sessionPath . '/sess_*');
                foreach ($files as $file) {
                    if (is_file($file)) {
                        $sessionData = file_get_contents($file);
                        if (strpos($sessionData, 'cart') !== false) {
                            // Clear cart from session
                            $sessionId = basename($file);
                            session_id($sessionId);
                            session_start();
                            unset($_SESSION['cart']);
                            unset($_SESSION['order_type']);
                            unset($_SESSION['promo']);
                            session_write_close();
                            $clearedCount++;
                        }
                    }
                }
            }
            
            $message = "Cleared cart data from {$clearedCount} session(s).";
            $messageType = 'success';
            
        } elseif ($action === 'show_stats') {
            // Show session statistics
            $sessionPath = session_save_path();
            if (empty($sessionPath)) {
                $sessionPath = sys_get_temp_dir();
            }
            
            $totalSessions = 0;
            $cartSessions = 0;
            
            if (is_dir($sessionPath)) {
                $files = glob($sessionPath . '/sess_*');
                $totalSessions = count($files);
                
                foreach ($files as $file) {
                    if (is_file($file)) {
                        $sessionData = file_get_contents($file);
                        if (strpos($sessionData, 'cart') !== false) {
                            $cartSessions++;
                        }
                    }
                }
            }
            
            $message = "Session Statistics: Total sessions: {$totalSessions}, Sessions with cart data: {$cartSessions}";
            $messageType = 'info';
        }
        
    } else {
        $message = 'Invalid password. Access denied.';
        $messageType = 'error';
    }
}

// Check if already authenticated in this session
$authenticated = $_SESSION['flush_authenticated'] ?? false;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Flush Sessions - PCS Admin</title>
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
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            padding: 40px;
            max-width: 500px;
            width: 100%;
        }
        
        h1 {
            color: #333;
            margin-bottom: 10px;
            font-size: 28px;
        }
        
        .subtitle {
            color: #666;
            margin-bottom: 30px;
            font-size: 14px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            color: #333;
            margin-bottom: 8px;
            font-weight: 500;
            font-size: 14px;
        }
        
        input[type="password"],
        select {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 16px;
            transition: border-color 0.3s;
        }
        
        input[type="password"]:focus,
        select:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .btn {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
        }
        
        .btn:active {
            transform: translateY(0);
        }
        
        .btn-danger {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        }
        
        .btn-danger:hover {
            box-shadow: 0 10px 20px rgba(245, 87, 108, 0.3);
        }
        
        .message {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        
        .message.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .message.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .message.info {
            background: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }
        
        .warning {
            background: #fff3cd;
            border: 1px solid #ffc107;
            color: #856404;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 13px;
        }
        
        .warning strong {
            display: block;
            margin-bottom: 5px;
        }
        
        .actions {
            display: grid;
            gap: 10px;
            margin-top: 20px;
        }
        
        .action-btn {
            padding: 12px;
            background: #f8f9fa;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .action-btn:hover {
            background: #e9ecef;
            border-color: #667eea;
        }
        
        .footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e0e0e0;
            text-align: center;
            color: #666;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔐 Session Flush</h1>
        <p class="subtitle">Private session management tool</p>
        
        <?php if ($message): ?>
            <div class="message <?php echo $messageType; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        
        <?php if (!$authenticated): ?>
            <div class="warning">
                <strong>⚠️ Warning</strong>
                This tool will clear session data. Use with caution.
            </div>
            
            <form method="POST" action="">
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required autofocus>
                </div>
                
                <div class="form-group">
                    <label for="action">Action</label>
                    <select id="action" name="action" required>
                        <option value="show_stats">Show Session Statistics</option>
                        <option value="clear_cart_sessions">Clear Cart Data from All Sessions</option>
                        <option value="flush_all">Flush All Sessions (Dangerous)</option>
                    </select>
                </div>
                
                <button type="submit" class="btn">Execute</button>
            </form>
        <?php else: ?>
            <div class="warning">
                <strong>✅ Authenticated</strong>
                You are authenticated for this session. Use the actions below.
            </div>
            
            <form method="POST" action="">
                <input type="hidden" name="password" value="<?php echo htmlspecialchars(FLUSH_SESSION_PASSWORD); ?>">
                
                <div class="actions">
                    <button type="submit" name="action" value="show_stats" class="action-btn">
                        📊 Show Session Statistics
                    </button>
                    
                    <button type="submit" name="action" value="clear_cart_sessions" class="action-btn">
                        🛒 Clear Cart Data from All Sessions
                    </button>
                    
                    <button type="submit" name="action" value="flush_all" class="action-btn btn-danger" 
                            onclick="return confirm('⚠️ WARNING: This will flush ALL sessions. Are you sure?');">
                        🗑️ Flush All Sessions (Dangerous)
                    </button>
                </div>
            </form>
        <?php endif; ?>
        
        <div class="footer">
            <p>PCS Admin Tool • Keep this script secure</p>
            <p style="margin-top: 5px; font-size: 11px; color: #999;">
                Change the password in the script file: <code>admin/flush_sessions.php</code>
            </p>
        </div>
    </div>
</body>
</html>

