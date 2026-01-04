<?php
/**
 * Orders Management Page
 */

require_once __DIR__ . '/config.php';
requireAdminLogin();

define('DB_CONFIG_LOADED', true);
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/sidebar_helper.php';

$pdo = getDBConnection();
$currentPage = basename(__FILE__);

// Get orders with filters
$statusFilter = $_GET['status'] ?? '';
$searchQuery = $_GET['search'] ?? '';

$sql = "SELECT * FROM orders WHERE 1=1";
$params = [];

if ($statusFilter) {
    $sql .= " AND status = :status";
    $params[':status'] = $statusFilter;
}

if ($searchQuery) {
    $sql .= " AND (email LIKE :search OR fullname LIKE :search OR name LIKE :search OR billExternalReferenceNo LIKE :search)";
    $params[':search'] = '%' . $searchQuery . '%';
}

$sql .= " ORDER BY created_at DESC LIMIT 200";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$orders = $stmt->fetchAll();

// Group orders by billExternalReferenceNo (same order)
$orderGroups = [];
foreach ($orders as $order) {
    $refNo = $order['billExternalReferenceNo'];
    if (!isset($orderGroups[$refNo])) {
        $orderGroups[$refNo] = [];
    }
    $orderGroups[$refNo][] = $order;
}

// Handle logout
if (isset($_GET['logout'])) {
    adminLogout();
    header('Location: /admin/');
    exit;
}

// Get statistics
$statsStmt = $pdo->query("
    SELECT 
        status,
        COUNT(*) as count,
        SUM(final_amount) as total
    FROM orders
    GROUP BY status
");
$stats = $statsStmt->fetchAll(PDO::FETCH_ASSOC);
$statusCounts = [];
foreach ($stats as $stat) {
    $statusCounts[$stat['status']] = [
        'count' => $stat['count'],
        'total' => $stat['total']
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Orders Management - Admin Panel | Projek Cuti Sem</title>
    <link rel="stylesheet" href="/admin/css/admin.css?v=30">
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <style>
        .orders-table { width: 100%; border-collapse: collapse; margin-top: 1.5rem; }
        .orders-table th, .orders-table td { padding: 0.75rem; border: 1px solid var(--border-color); text-align: left; }
        .orders-table th { background: var(--secondary-color); color: #fff; font-weight: 600; }
        .orders-table tr:hover { background: #f9fafb; }
        .status-select, .stock-select { padding: 0.375rem 0.75rem; border: 1px solid var(--border-color); border-radius: 4px; font-size: 0.875rem; }
        .filters { background: var(--card-bg); padding: 1.5rem; border-radius: 8px; margin-bottom: 1.5rem; }
        .filters form { display: flex; gap: 1rem; flex-wrap: wrap; align-items: end; }
        .filters .form-group { flex: 1; min-width: 200px; }
        .filters label { display: block; margin-bottom: 0.5rem; font-weight: 600; }
        .filters input, .filters select { width: 100%; padding: 0.5rem; border: 1px solid var(--border-color); border-radius: 4px; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 1.5rem; }
        .stat-card { background: var(--card-bg); padding: 1rem; border-radius: 8px; border-left: 4px solid var(--primary-color); }
        .stat-card h3 { margin: 0 0 0.5rem 0; font-size: 0.875rem; color: var(--text-secondary); }
        .stat-card .stat-value { font-size: 1.5rem; font-weight: 700; color: var(--text-primary); }
        .price-adjust-link { color: var(--primary-color); text-decoration: none; }
        .price-adjust-link:hover { text-decoration: underline; }
    </style>
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
            <h1>Orders Management</h1>
        </header>
        
        <!-- Statistics -->
        <div class="stats-grid">
            <?php 
            $totalOrders = array_sum(array_column($statusCounts, 'count'));
            $totalRevenue = array_sum(array_column($statusCounts, 'total'));
            ?>
            <div class="stat-card">
                <h3>Total Orders</h3>
                <div class="stat-value"><?php echo number_format($totalOrders); ?></div>
            </div>
            <div class="stat-card">
                <h3>Total Revenue</h3>
                <div class="stat-value">RM <?php echo number_format($totalRevenue, 2); ?></div>
            </div>
            <div class="stat-card">
                <h3>Pending</h3>
                <div class="stat-value"><?php echo number_format($statusCounts['pending']['count'] ?? 0); ?></div>
            </div>
            <div class="stat-card">
                <h3>Paid</h3>
                <div class="stat-value"><?php echo number_format($statusCounts['paid']['count'] ?? 0); ?></div>
            </div>
        </div>
        
        <!-- Filters -->
        <div class="filters">
            <form method="GET">
                <div class="form-group">
                    <label>Status Filter:</label>
                    <select name="status">
                        <option value="">All Statuses</option>
                        <option value="pending" <?php echo $statusFilter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="packing" <?php echo $statusFilter === 'packing' ? 'selected' : ''; ?>>Packing</option>
                        <option value="ready" <?php echo $statusFilter === 'ready' ? 'selected' : ''; ?>>Ready</option>
                        <option value="shipped" <?php echo $statusFilter === 'shipped' ? 'selected' : ''; ?>>Shipped</option>
                        <option value="paid" <?php echo $statusFilter === 'paid' ? 'selected' : ''; ?>>Paid</option>
                        <option value="failed" <?php echo $statusFilter === 'failed' ? 'selected' : ''; ?>>Failed</option>
                        <option value="cancelled" <?php echo $statusFilter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Search:</label>
                    <input type="text" name="search" placeholder="Email, name, or order ref..." 
                           value="<?php echo htmlspecialchars($searchQuery); ?>">
                </div>
                <div class="form-group">
                    <button type="submit" class="btn btn-primary">Filter</button>
                    <a href="?" class="btn btn-secondary">Clear</a>
                </div>
            </form>
        </div>
        
        <!-- Orders List -->
        <div class="table-container">
            <table class="orders-table">
                <thead>
                    <tr>
                        <th>Order Ref</th>
                        <th>Customer</th>
                        <th>Items</th>
                        <th>Total</th>
                        <th>Status</th>
                        <th>Stock Status</th>
                        <th>Order Type</th>
                        <th>Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($orderGroups)): ?>
                        <tr>
                            <td colspan="9" style="text-align: center; padding: 2rem; color: #6b7280;">
                                No orders found.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($orderGroups as $refNo => $items): 
                            $firstItem = $items[0];
                            $totalAmount = array_sum(array_column($items, 'final_amount'));
                            $allStatuses = array_unique(array_column($items, 'status'));
                            $primaryStatus = $firstItem['status'];
                        ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($refNo); ?></strong>
                                    <?php if (count($allStatuses) > 1): ?>
                                        <br><small style="color: #f59e0b;">Mixed statuses</small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($firstItem['fullname'] ?: $firstItem['name']); ?><br>
                                    <small><?php echo htmlspecialchars($firstItem['email']); ?></small><br>
                                    <small><?php echo htmlspecialchars($firstItem['phone']); ?></small>
                                </td>
                                <td>
                                    <?php foreach ($items as $item): ?>
                                        <div style="margin-bottom: 0.5rem;">
                                            <?php 
                                            $variantNames = [
                                                'hitamdepan' => 'Classic Black',
                                                'hijaufront' => 'Forest Green',
                                                'merahdepan' => 'Royal Red'
                                            ];
                                            $variantName = $variantNames[$item['variant']] ?? $item['variant'];
                                            ?>
                                            <strong><?php echo htmlspecialchars($variantName); ?></strong> - 
                                            <?php echo htmlspecialchars($item['size']); ?> x<?php echo $item['quantity']; ?><br>
                                            <small>RM <?php echo number_format($item['final_amount'], 2); ?></small>
                                        </div>
                                    <?php endforeach; ?>
                                </td>
                                <td>
                                    <strong>RM <?php echo number_format($totalAmount, 2); ?></strong>
                                    <?php if ($firstItem['delivery_fee'] > 0): ?>
                                        <br><small>Delivery: RM <?php echo number_format($firstItem['delivery_fee'], 2); ?></small>
                                    <?php endif; ?>
                                    <?php if ($firstItem['discount_amount'] > 0): ?>
                                        <br><small style="color: #10b981;">Discount: -RM <?php echo number_format($firstItem['discount_amount'], 2); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php foreach ($items as $item): ?>
                                        <select class="order-status-select" 
                                                data-order-id="<?php echo $item['id']; ?>" 
                                                data-current-status="<?php echo htmlspecialchars($item['status']); ?>"
                                                style="margin-bottom: 0.5rem; width: 100%;">
                                            <option value="pending" <?php echo $item['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                            <option value="packing" <?php echo $item['status'] === 'packing' ? 'selected' : ''; ?>>Packing</option>
                                            <option value="ready" <?php echo $item['status'] === 'ready' ? 'selected' : ''; ?>>Ready</option>
                                            <option value="shipped" <?php echo $item['status'] === 'shipped' ? 'selected' : ''; ?>>Shipped</option>
                                            <option value="paid" <?php echo $item['status'] === 'paid' ? 'selected' : ''; ?>>Paid</option>
                                            <option value="failed" <?php echo $item['status'] === 'failed' ? 'selected' : ''; ?>>Failed</option>
                                            <option value="cancelled" <?php echo $item['status'] === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                        </select>
                                    <?php endforeach; ?>
                                </td>
                                <td>
                                    <?php foreach ($items as $item): ?>
                                        <select class="stock-status-select" 
                                                data-order-id="<?php echo $item['id']; ?>" 
                                                data-current-stock="<?php echo htmlspecialchars($item['stock_status'] ?? 'ready'); ?>"
                                                style="margin-bottom: 0.5rem; width: 100%;">
                                            <option value="ready" <?php echo ($item['stock_status'] ?? 'ready') === 'ready' ? 'selected' : ''; ?>>Ready</option>
                                            <option value="out_of_stock" <?php echo ($item['stock_status'] ?? 'ready') === 'out_of_stock' ? 'selected' : ''; ?>>Out of Stock</option>
                                            <option value="pre_order" <?php echo ($item['stock_status'] ?? 'ready') === 'pre_order' ? 'selected' : ''; ?>>Pre-Order</option>
                                        </select>
                                    <?php endforeach; ?>
                                </td>
                                <td>
                                    <?php echo ucfirst($firstItem['order_type'] ?? 'delivery'); ?>
                                    <?php if ($firstItem['order_type'] === 'delivery'): ?>
                                        <br><small><?php echo htmlspecialchars($firstItem['address1'] ?? ''); ?></small>
                                        <br><small><?php echo htmlspecialchars($firstItem['district'] ?? ''); ?>, <?php echo htmlspecialchars($firstItem['state'] ?? ''); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><?php 
                                    // Load timezone helper if not already loaded
                                    if (!function_exists('formatDateTime')) {
                                        require_once __DIR__ . '/../config/timezone.php';
                                    }
                                    echo formatDateTime(dbToAppTime($firstItem['created_at']), 'Y-m-d H:i'); 
                                ?></td>
                                <td>
                                    <a href="#" onclick="openPriceAdjust(<?php echo $firstItem['id']; ?>, <?php echo $totalAmount; ?>); return false;" 
                                       class="price-adjust-link">Adjust Price</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>
    
    <!-- Price Adjustment Modal -->
    <div id="priceAdjustModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center;">
        <div style="background: white; padding: 2rem; border-radius: 8px; max-width: 500px; width: 90%;">
            <h2>Adjust Order Price</h2>
            <p>Current Total: <strong id="currentTotal">RM 0.00</strong></p>
            
            <form id="priceAdjustForm">
                <input type="hidden" name="order_id" id="adjustOrderId">
                
                <div style="margin-bottom: 1rem;">
                    <label>Adjustment Type:</label>
                    <select name="adjustment_type" id="adjustmentType" required>
                        <option value="override">Set New Total (Override)</option>
                        <option value="add">Add Amount</option>
                        <option value="subtract">Subtract Amount</option>
                    </select>
                </div>
                
                <div style="margin-bottom: 1rem;">
                    <label>Amount (RM):</label>
                    <input type="number" name="adjustment_amount" step="0.01" min="0" required>
                    <small id="adjustmentHint">Enter the new total amount</small>
                </div>
                
                <div style="margin-bottom: 1rem;">
                    <label>Reason (Optional):</label>
                    <textarea name="reason" rows="3" placeholder="E.g., Special discount, correction..."></textarea>
                </div>
                
                <div id="adjustmentPreview" style="margin-top: 1rem; padding: 1rem; background: #f5f5f5; display: none;">
                    <strong>Preview:</strong>
                    <span id="previewText"></span>
                </div>
                
                <div style="margin-top: 1.5rem; display: flex; gap: 1rem;">
                    <button type="submit" class="btn btn-primary">Apply Adjustment</button>
                    <button type="button" class="btn btn-secondary" onclick="closePriceAdjustModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>
    
    <script src="/admin/js/admin.js"></script>
    <script src="/admin/js/orders.js"></script>
</body>
</html>

