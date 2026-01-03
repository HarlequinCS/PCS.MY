<?php
/**
 * Update Product Variant Names (Web Accessible)
 * Changes variant names from old Malay names to new English names
 * 
 * SECURITY: Add password protection or restrict access by IP
 */

// Simple password protection
session_start();
$requiredPassword = 'PCS@dmin2025!'; // Change this password!

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';
    
    if ($password === $requiredPassword) {
        // Database configuration
        require_once __DIR__ . '/../config/database.php';
        require_once __DIR__ . '/../includes/db.php';
        
        try {
            $pdo = getDBConnection();
            
            // Variant name mapping
            $variantNameUpdates = [
                'hitamdepan' => 'Classic Black',
                'hijaufront' => 'Forest Green',
                'merahdepan' => 'Royal Red'
            ];
            
            $pdo->beginTransaction();
            
            $updatedCount = 0;
            foreach ($variantNameUpdates as $variantKey => $newName) {
                // Update variant name
                $stmt = $pdo->prepare("
                    UPDATE product_variants 
                    SET variant_name = :new_name 
                    WHERE variant_key = :variant_key
                ");
                
                $stmt->execute([
                    ':new_name' => $newName,
                    ':variant_key' => $variantKey
                ]);
                
                $affectedRows = $stmt->rowCount();
                $updatedCount += $affectedRows;
            }
            
            $pdo->commit();
            
            $message = "Successfully updated {$updatedCount} variant name(s)!";
            $messageType = 'success';
            
            // Verify updates
            $verifyStmt = $pdo->query("SELECT variant_key, variant_name FROM product_variants ORDER BY variant_key");
            $variants = $verifyStmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $message = "ERROR: " . $e->getMessage();
            $messageType = 'error';
        }
    } else {
        $message = 'Invalid password!';
        $messageType = 'error';
    }
}

// Get current variant names for display
$currentVariants = [];
if (isset($pdo)) {
    try {
        $stmt = $pdo->query("SELECT variant_key, variant_name FROM product_variants ORDER BY variant_key");
        $currentVariants = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        // Ignore
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Update Variant Names - PCS Admin</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
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
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            padding: 40px;
            max-width: 600px;
            width: 100%;
        }
        h1 { color: #333; margin-bottom: 10px; }
        .subtitle { color: #666; margin-bottom: 30px; font-size: 14px; }
        .form-group { margin-bottom: 20px; }
        label { display: block; color: #333; margin-bottom: 8px; font-weight: 500; }
        input[type="password"] {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 16px;
        }
        input[type="password"]:focus {
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
        }
        .btn:hover { opacity: 0.9; }
        .message {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
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
        .variants-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        .variants-table th,
        .variants-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e0e0e0;
        }
        .variants-table th {
            background: #f8f9fa;
            font-weight: 600;
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
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 Update Variant Names</h1>
        <p class="subtitle">Update product variant names in database</p>
        
        <?php if ($message): ?>
            <div class="message <?php echo $messageType; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        
        <div class="warning">
            <strong>⚠️ Warning</strong><br>
            This will update variant names in the database:<br>
            • Hitam → Classic Black<br>
            • Hijau → Forest Green<br>
            • Merah → Royal Red
        </div>
        
        <?php if (empty($currentVariants) && !isset($pdo)): ?>
            <form method="POST">
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required autofocus>
                </div>
                <button type="submit" class="btn">Update Variant Names</button>
            </form>
        <?php elseif (!empty($currentVariants)): ?>
            <table class="variants-table">
                <thead>
                    <tr>
                        <th>Variant Key</th>
                        <th>Current Name</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($currentVariants as $variant): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($variant['variant_key']); ?></td>
                            <td><?php echo htmlspecialchars($variant['variant_name']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
        
        <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #e0e0e0; text-align: center; color: #666; font-size: 12px;">
            <p>After updating, clear browser cache and sessions to see changes.</p>
        </div>
    </div>
</body>
</html>

