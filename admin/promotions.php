<?php
/**
 * Promotion Code Management Page
 */

// Set timezone for all date/time operations (MANDATORY)
date_default_timezone_set('Asia/Kuala_Lumpur');

require_once __DIR__ . '/config.php';
requireAdminLogin();

define('DB_CONFIG_LOADED', true);
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/datetime_helper.php';
require_once __DIR__ . '/sidebar_helper.php';

$pdo = getDBConnection();
$currentPage = basename(__FILE__);

// Handle actions
$action = $_GET['action'] ?? 'list';
$message = '';
$messageType = '';

// Create/Update Promotion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $actionPost = $_POST['action'];
    
    if ($actionPost === 'create' || $actionPost === 'update' || $actionPost === 'edit') {
        $code = strtoupper(trim($_POST['code'] ?? ''));
        $type = $_POST['type'] ?? 'percentage';
        $value = floatval($_POST['value'] ?? 0);
        $active = isset($_POST['active']) ? 1 : 0;
        $minPurchase = floatval($_POST['min_purchase'] ?? 0);
        $maxDiscount = !empty($_POST['max_discount']) ? floatval($_POST['max_discount']) : null;
        $usageLimit = !empty($_POST['usage_limit']) ? intval($_POST['usage_limit']) : null;
        
        // Normalize datetime inputs: convert HTML datetime-local to MySQL DATETIME format
        // Empty inputs become NULL (not empty strings)
        $validFrom = normalizeDateTime($_POST['valid_from'] ?? '');
        $validUntil = normalizeDateTime($_POST['valid_until'] ?? '');
        
        // Validation
        if (empty($code)) {
            $message = 'Promo code is required';
            $messageType = 'error';
        } elseif (!in_array($type, ['percentage', 'fixed'])) {
            $message = 'Invalid discount type';
            $messageType = 'error';
        } elseif ($value <= 0) {
            $message = 'Discount value must be greater than 0';
            $messageType = 'error';
        } else {
            try {
                if ($actionPost === 'create') {
                    // Check if code already exists
                    $checkStmt = $pdo->prepare("SELECT id FROM promotions WHERE code = :code");
                    $checkStmt->execute([':code' => $code]);
                    if ($checkStmt->fetch()) {
                        $message = 'Promo code already exists';
                        $messageType = 'error';
                    } else {
                        // New promotions must always start with used_count = 0
                        $stmt = $pdo->prepare("
                            INSERT INTO promotions (
                                code, type, value, active, min_purchase, max_discount,
                                usage_limit, valid_from, valid_until, used_count
                            ) VALUES (
                                :code, :type, :value, :active, :min_purchase, :max_discount,
                                :usage_limit, :valid_from, :valid_until, 0
                            )
                        ");
                        $stmt->execute([
                            ':code' => $code,
                            ':type' => $type,
                            ':value' => round(floatval($value), 2),
                            ':active' => $active,
                            ':min_purchase' => round(floatval($minPurchase), 2),
                            ':max_discount' => $maxDiscount !== null ? round(floatval($maxDiscount), 2) : null,
                            ':usage_limit' => $usageLimit,
                            ':valid_from' => $validFrom ?: null,
                            ':valid_until' => $validUntil ?: null
                        ]);
                        $message = 'Promotion code created successfully';
                        $messageType = 'success';
                        $action = 'list'; // Redirect to list
                    }
                } else { // update or edit
                    $id = intval($_POST['id']);
                    $stmt = $pdo->prepare("
                        UPDATE promotions SET
                            code = :code,
                            type = :type,
                            value = :value,
                            active = :active,
                            min_purchase = :min_purchase,
                            max_discount = :max_discount,
                            usage_limit = :usage_limit,
                            valid_from = :valid_from,
                            valid_until = :valid_until,
                            updated_at = NOW()
                        WHERE id = :id
                    ");
                    // Update promotion (used_count is NOT updated here - it's read-only, auto-incremented on usage)
                    $stmt->execute([
                        ':code' => $code,
                        ':type' => $type,
                        ':value' => round(floatval($value), 2),
                        ':active' => $active,
                        ':min_purchase' => round(floatval($minPurchase), 2),
                        ':max_discount' => $maxDiscount !== null ? round(floatval($maxDiscount), 2) : null,
                        ':usage_limit' => $usageLimit,
                        ':valid_from' => $validFrom ?: null,
                        ':valid_until' => $validUntil ?: null,
                        ':id' => $id
                    ]);
                    $message = 'Promotion code updated successfully';
                    $messageType = 'success';
                    $action = 'list'; // Redirect to list
                }
            } catch (PDOException $e) {
                error_log('Promotion save error: ' . $e->getMessage());
                $message = 'Error saving promotion code: ' . $e->getMessage();
                $messageType = 'error';
            }
        }
    } elseif ($actionPost === 'delete') {
        $id = intval($_POST['id'] ?? 0);
        if ($id > 0) {
            try {
                $stmt = $pdo->prepare("DELETE FROM promotions WHERE id = :id");
                $stmt->execute([':id' => $id]);
                $message = 'Promotion code deleted successfully';
                $messageType = 'success';
            } catch (PDOException $e) {
                error_log('Promotion delete error: ' . $e->getMessage());
                $message = 'Error deleting promotion code';
                $messageType = 'error';
            }
        }
    }
}

// Get promotion for editing
$promotion = null;
if ($action === 'edit' && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $stmt = $pdo->prepare("SELECT * FROM promotions WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $promotion = $stmt->fetch();
    if (!$promotion) {
        $action = 'list';
    }
}

// Get all promotions
$promotions = [];
$stmt = $pdo->query("SELECT * FROM promotions ORDER BY created_at DESC");
$promotions = $stmt->fetchAll();

// Handle logout
if (isset($_GET['logout'])) {
    adminLogout();
    header('Location: /admin/');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Promotion Codes - Admin Panel | Projek Cuti Sem</title>
    <link rel="stylesheet" href="/admin/css/admin.css?v=30">
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
</head>
<body class="admin-dashboard">
    <!-- Mobile Menu Toggle -->
    <button class="mobile-menu-toggle" id="mobileMenuToggle" aria-label="Toggle menu">☰</button>
    
    <!-- Sidebar Overlay -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    
    <!-- Dynamic Sidebar - Automatically generated from admin directory -->
    <?php echo generateAdminSidebar($currentPage); ?>
    
    <!-- Main Content -->
    <main class="main-content">
        <header class="content-header">
            <h1>Promotion Code Management</h1>
        </header>
        
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType === 'success' ? 'success' : 'error'; ?>" style="margin-bottom: 1.5rem;">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($action === 'list' || $action === 'edit'): ?>
            <div style="margin-bottom: 1.5rem;">
                <a href="?action=create" class="btn btn-primary">+ Create New Promotion</a>
            </div>
            
            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>Type</th>
                            <th>Value</th>
                            <th>Min Purchase</th>
                            <th>Max Discount</th>
                            <th>Usage Limit</th>
                            <th>Used</th>
                            <th>Valid From</th>
                            <th>Valid Until</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($promotions)): ?>
                            <tr>
                                <td colspan="11" style="text-align: center; padding: 2rem; color: #6b7280;">
                                    No promotion codes found. <a href="?action=create">Create one</a>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($promotions as $promo): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($promo['code']); ?></strong></td>
                                    <td><?php echo ucfirst($promo['type']); ?></td>
                                    <td>
                                        <?php 
                                        if ($promo['type'] === 'percentage') {
                                            echo number_format($promo['value'], 0) . '%';
                                        } else {
                                            echo 'RM ' . number_format($promo['value'], 2);
                                        }
                                        ?>
                                    </td>
                                    <td>RM <?php echo number_format($promo['min_purchase'] ?? 0, 2); ?></td>
                                    <td><?php echo $promo['max_discount'] ? 'RM ' . number_format($promo['max_discount'], 2) : '-'; ?></td>
                                    <td><?php echo $promo['usage_limit'] ?? 'Unlimited'; ?></td>
                                    <td><?php echo $promo['used_count']; ?></td>
                                    <td><?php echo $promo['valid_from'] ? date('Y-m-d H:i', strtotime($promo['valid_from'])) : '-'; ?></td>
                                    <td><?php echo $promo['valid_until'] ? date('Y-m-d H:i', strtotime($promo['valid_until'])) : '-'; ?></td>
                                    <td>
                                        <span class="status-badge <?php echo $promo['active'] ? 'status-active' : 'status-inactive'; ?>">
                                            <?php echo $promo['active'] ? 'Active' : 'Inactive'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="?action=edit&id=<?php echo $promo['id']; ?>" class="btn btn-sm btn-primary">Edit</a>
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Delete this promotion code? This action cannot be undone.');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?php echo $promo['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
        
        <?php if ($action === 'create' || $action === 'edit'): ?>
            <div class="form-container">
                <h2><?php echo $action === 'create' ? 'Create' : 'Edit'; ?> Promotion Code</h2>
                <form method="POST" class="admin-form">
                    <input type="hidden" name="action" value="<?php echo $action; ?>">
                    <?php if ($action === 'edit'): ?>
                        <input type="hidden" name="id" value="<?php echo $promotion['id']; ?>">
                    <?php endif; ?>
                    
                    <div class="form-group">
                        <label for="code">Promo Code <span class="required">*</span></label>
                        <input type="text" name="code" id="code" 
                               value="<?php echo htmlspecialchars($promotion['code'] ?? ''); ?>" 
                               required style="text-transform: uppercase;"
                               placeholder="e.g., PCS10OFF">
                        <small>Code will be automatically converted to uppercase</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="type">Discount Type <span class="required">*</span></label>
                        <select name="type" id="type" required>
                            <option value="percentage" <?php echo ($promotion['type'] ?? '') === 'percentage' ? 'selected' : ''; ?>>Percentage</option>
                            <option value="fixed" <?php echo ($promotion['type'] ?? '') === 'fixed' ? 'selected' : ''; ?>>Fixed Amount</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="value">Discount Value <span class="required">*</span></label>
                        <input type="number" name="value" id="value" step="0.01" min="0.01" 
                               value="<?php echo $promotion['value'] ?? ''; ?>" required>
                        <small>For percentage: enter number (e.g., 10 for 10%). For fixed: enter amount in RM.</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="min_purchase">Minimum Purchase (RM)</label>
                        <input type="number" name="min_purchase" step="0.01" min="0" 
                               value="<?php echo $promotion['min_purchase'] ?? 0; ?>">
                        <small>Minimum purchase amount required to use this code</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="max_discount">Max Discount (RM) - Optional</label>
                        <input type="number" name="max_discount" step="0.01" min="0" 
                               value="<?php echo $promotion['max_discount'] ?? ''; ?>">
                        <small>Only applies to percentage discounts. Leave empty for no limit.</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="usage_limit">Usage Limit - Optional</label>
                        <input type="number" name="usage_limit" min="1" 
                               value="<?php echo $promotion['usage_limit'] ?? ''; ?>">
                        <small>Maximum number of times this code can be used. Leave empty for unlimited.</small>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="valid_from">Valid From (Optional)</label>
                            <input type="datetime-local" name="valid_from" 
                                   value="<?php echo $promotion['valid_from'] ? date('Y-m-d\TH:i', strtotime($promotion['valid_from'])) : ''; ?>">
                            <small>Leave empty if you don't want a start date (Please Choose The Date A Day Earlier Than The Current Date To Avoid Confusion)</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="valid_until">Valid Until (Optional)</label>
                            <input type="datetime-local" name="valid_until" 
                                   value="<?php echo $promotion['valid_until'] ? date('Y-m-d\TH:i', strtotime($promotion['valid_until'])) : ''; ?>">
                            <small>Leave empty if you don't want an expiry date</small>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>
                            <input type="checkbox" name="active" value="1" 
                                   <?php echo ($promotion['active'] ?? 1) ? 'checked' : ''; ?>>
                            Active (promotion code is enabled)
                        </label>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-success">Save Promotion</button>
                        <a href="?" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        <?php endif; ?>
    </main>
    
    <script src="/admin/js/admin.js"></script>
</body>
</html>

