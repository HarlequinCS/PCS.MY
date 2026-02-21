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

// Get distinct paid order count for gateway fee calculation
$paidOrderCountStmt = $pdo->query("
    SELECT COUNT(DISTINCT billExternalReferenceNo) as order_count
    FROM orders
    WHERE status = 'paid'
");
$paidOrderCountResult = $paidOrderCountStmt->fetch(PDO::FETCH_ASSOC);
$paidOrderCount = (int)($paidOrderCountResult['order_count'] ?? 0);

// Get quantity by color/variant (excluding cancelled)
$variantStatsStmt = $pdo->query("
    SELECT 
        variant,
        SUM(quantity) as total_quantity
    FROM orders
    WHERE status != 'cancelled'
    GROUP BY variant
    ORDER BY total_quantity DESC
");
$variantStats = $variantStatsStmt->fetchAll(PDO::FETCH_ASSOC);
$variantCounts = [];
$variantNames = [
    'hitamdepan' => 'Classic Black',
    'hijaufront' => 'Forest Green',
    'merahdepan' => 'Royal Red'
];
foreach ($variantStats as $stat) {
    $variantName = $variantNames[$stat['variant']] ?? ucfirst($stat['variant']);
    $variantCounts[$variantName] = $stat['total_quantity'];
}

// Get quantity by size (excluding cancelled)
$sizeStatsStmt = $pdo->query("
    SELECT 
        size,
        SUM(quantity) as total_quantity
    FROM orders
    WHERE status != 'cancelled'
    GROUP BY size
    ORDER BY 
        CASE size
            WHEN 'XS' THEN 1
            WHEN 'S' THEN 2
            WHEN 'M' THEN 3
            WHEN 'L' THEN 4
            WHEN 'XL' THEN 5
            WHEN 'XXL' THEN 6
            ELSE 7
        END
");
$sizeStats = $sizeStatsStmt->fetchAll(PDO::FETCH_ASSOC);
$sizeCounts = [];
foreach ($sizeStats as $stat) {
    $sizeCounts[$stat['size']] = $stat['total_quantity'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Orders Management - Admin Panel | Projek Cuti Sem</title>
    <link rel="stylesheet" href="<?php echo getAdminAsset('css/admin.css'); ?>">
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
        .category-stats { background: var(--card-bg); padding: 1.5rem; border-radius: 8px; margin-bottom: 1.5rem; }
        .category-stats h2 { margin: 0 0 1.5rem 0; font-size: 1.25rem; color: var(--text-primary); }
        .category-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem; }
        .category-section { background: #f9fafb; padding: 1rem; border-radius: 8px; }
        .category-section h3 { margin: 0 0 1rem 0; font-size: 1rem; color: var(--text-primary); font-weight: 600; }
        .category-item { display: flex; justify-content: space-between; align-items: center; padding: 0.5rem 0; border-bottom: 1px solid #e5e7eb; }
        .category-item:last-child { border-bottom: none; }
        .category-item-label { color: var(--text-secondary); font-size: 0.875rem; }
        .category-item-value { font-weight: 700; color: var(--text-primary); font-size: 1.125rem; }
        .download-receipt-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: #ffffff;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 600;
            font-size: 0.875rem;
            transition: all 0.2s ease;
            margin-bottom: 0.5rem;
            box-shadow: 0 2px 4px rgba(16, 185, 129, 0.3);
        }
        .download-receipt-btn:hover {
            background: linear-gradient(135deg, #059669 0%, #047857 100%);
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(16, 185, 129, 0.4);
            color: #ffffff;
        }
        .download-receipt-btn::before {
            content: "📄";
            font-size: 1rem;
        }
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
            // Calculate totals excluding cancelled orders
            $totalOrders = 0;
            $totalRevenue = 0;
            foreach ($statusCounts as $status => $data) {
                if ($status !== 'cancelled') {
                    $totalOrders += $data['count'];
                    $totalRevenue += $data['total'];
                }
            }
            // Calculate net revenue after gateway fees (RM1 per paid order)
            $totalGatewayFees = $paidOrderCount * 1.00;
            $netRevenue = max(0, $totalRevenue - $totalGatewayFees);
            ?>
            <div class="stat-card">
                <h3>Total Orders</h3>
                <div class="stat-value"><?php echo number_format($totalOrders); ?></div>
                <small style="color: #6b7280; font-size: 0.75rem;">(Excluding cancelled)</small>
            </div>
            <div class="stat-card">
                <h3>Total Revenue</h3>
                <div class="stat-value">RM <?php echo number_format($totalRevenue, 2); ?></div>
                <small style="color: #6b7280; font-size: 0.75rem;">(Excluding cancelled)</small>
            </div>
            <div class="stat-card" style="border-left-color: #059669;">
                <h3>Net Revenue (After Gateway Fee)</h3>
                <div class="stat-value" style="color: #059669;">RM <?php echo number_format($netRevenue, 2); ?></div>
                <small style="color: #6b7280; font-size: 0.75rem;">Gateway Fee: -RM <?php echo number_format($totalGatewayFees, 2); ?> (<?php echo $paidOrderCount; ?> paid orders × RM1)</small>
            </div>
            <div class="stat-card">
                <h3>Pending</h3>
                <div class="stat-value"><?php echo number_format($statusCounts['pending']['count'] ?? 0); ?></div>
            </div>
            <div class="stat-card">
                <h3>Paid</h3>
                <div class="stat-value"><?php echo number_format($statusCounts['paid']['count'] ?? 0); ?></div>
            </div>
            <div class="stat-card" style="border-left-color: #ef4444;">
                <h3>Cancelled</h3>
                <div class="stat-value" style="color: #ef4444;"><?php echo number_format($statusCounts['cancelled']['count'] ?? 0); ?></div>
                <small style="color: #6b7280; font-size: 0.75rem;">Excluded from totals</small>
            </div>
        </div>
        
        <!-- Category Statistics -->
        <div class="category-stats">
            <h2>Quantity Breakdown</h2>
            <div class="category-grid">
                <!-- Quantity by Color -->
                <div class="category-section">
                    <h3>Quantity by Color</h3>
                    <?php if (empty($variantCounts)): ?>
                        <p style="color: #6b7280; font-size: 0.875rem; margin: 0;">No orders found</p>
                    <?php else: ?>
                        <?php 
                        $totalVariantQty = array_sum($variantCounts);
                        foreach ($variantCounts as $variantName => $quantity): 
                            $percentage = $totalVariantQty > 0 ? round(($quantity / $totalVariantQty) * 100, 1) : 0;
                        ?>
                            <div class="category-item">
                                <span class="category-item-label"><?php echo htmlspecialchars($variantName); ?></span>
                                <span class="category-item-value">
                                    <?php echo number_format($quantity); ?>
                                    <small style="color: #6b7280; font-size: 0.75rem; font-weight: normal; margin-left: 0.5rem;">
                                        (<?php echo $percentage; ?>%)
                                    </small>
                                </span>
                            </div>
                        <?php endforeach; ?>
                        <div class="category-item" style="margin-top: 0.5rem; padding-top: 0.75rem; border-top: 2px solid #e5e7eb; font-weight: 700;">
                            <span class="category-item-label">Total</span>
                            <span class="category-item-value"><?php echo number_format($totalVariantQty); ?></span>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Quantity by Size -->
                <div class="category-section">
                    <h3>Quantity by Size</h3>
                    <?php if (empty($sizeCounts)): ?>
                        <p style="color: #6b7280; font-size: 0.875rem; margin: 0;">No orders found</p>
                    <?php else: ?>
                        <?php 
                        $totalSizeQty = array_sum($sizeCounts);
                        foreach ($sizeCounts as $size => $quantity): 
                            $percentage = $totalSizeQty > 0 ? round(($quantity / $totalSizeQty) * 100, 1) : 0;
                        ?>
                            <div class="category-item">
                                <span class="category-item-label">Size <?php echo htmlspecialchars($size); ?></span>
                                <span class="category-item-value">
                                    <?php echo number_format($quantity); ?>
                                    <small style="color: #6b7280; font-size: 0.75rem; font-weight: normal; margin-left: 0.5rem;">
                                        (<?php echo $percentage; ?>%)
                                    </small>
                                </span>
                            </div>
                        <?php endforeach; ?>
                        <div class="category-item" style="margin-top: 0.5rem; padding-top: 0.75rem; border-top: 2px solid #e5e7eb; font-weight: 700;">
                            <span class="category-item-label">Total</span>
                            <span class="category-item-value"><?php echo number_format($totalSizeQty); ?></span>
                        </div>
                    <?php endif; ?>
                </div>
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
                            // Calculate gateway fee (RM1 per order, not per item)
                            $gatewayFee = 1.00;
                            $netAmount = max(0, $totalAmount - $gatewayFee);
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
                                    <?php if ($primaryStatus === 'paid'): ?>
                                        <br><small style="color: #ef4444;">Gateway Fee: -RM <?php echo number_format($gatewayFee, 2); ?></small>
                                        <br><strong style="color: #059669;">Net Received: RM <?php echo number_format($netAmount, 2); ?></strong>
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
                                    <?php 
                                    // Check if order is paid - show receipt button only for paid orders
                                    // Check if primary status is paid or if all items are paid
                                    $isPaid = ($primaryStatus === 'paid') || (count($allStatuses) === 1 && in_array('paid', $allStatuses));
                                    if ($isPaid): 
                                    ?>
                                        <a href="/admin/api/generate_receipt.php?ref=<?php echo urlencode($refNo); ?>" 
                                           class="download-receipt-btn" 
                                           target="_blank"
                                           title="Download Receipt (PDF)">
                                            Download Receipt
                                        </a>
                                        <br>
                                    <?php endif; ?>
                                    <a href="#" onclick="openPriceAdjust(<?php echo $firstItem['id']; ?>, <?php echo $totalAmount; ?>); return false;" 
                                       class="price-adjust-link">Adjust Price</a>
                                    <br>
                                    <button type="button" 
                                            onclick="deleteOrder('<?php echo htmlspecialchars($refNo, ENT_QUOTES); ?>'); return false;" 
                                            class="btn btn-sm btn-danger" 
                                            style="margin-top: 0.5rem; padding: 0.375rem 0.75rem; font-size: 0.875rem;">
                                        Delete Order
                                    </button>
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
    
    <script src="<?php echo getAdminAsset('js/admin.js'); ?>"></script>
    <script src="<?php echo getAdminAsset('js/orders.js'); ?>"></script>
</body>
</html>

