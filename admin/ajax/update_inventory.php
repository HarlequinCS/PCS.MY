<?php
/**
 * AJAX Handler: Update Product Inventory
 * POST parameters: variant_id, size, quantity
 */

require_once __DIR__ . '/../config.php';
requireAdminLogin();

define('DB_CONFIG_LOADED', true);
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$variantId = intval($_POST['variant_id'] ?? 0);
$size = trim($_POST['size'] ?? '');
$quantity = intval($_POST['quantity'] ?? 0);

// Validation
$validSizes = ['S', 'M', 'L', 'XL', 'XXL'];
if (!in_array($size, $validSizes)) {
    echo json_encode(['success' => false, 'error' => 'Invalid size']);
    exit;
}

if ($variantId <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid variant ID']);
    exit;
}

if ($quantity < 0) {
    echo json_encode(['success' => false, 'error' => 'Quantity cannot be negative']);
    exit;
}

try {
    $pdo = getDBConnection();

    // Verify product is in_stock (inventory only managed for in_stock products)
    $productStmt = $pdo->prepare("
        SELECT p.stock_status, p.name as product_name, pv.variant_name
        FROM products p
        JOIN product_variants pv ON p.id = pv.product_id
        WHERE pv.id = :variant_id
    ");
    $productStmt->execute([':variant_id' => $variantId]);
    $product = $productStmt->fetch();

    if (!$product) {
        echo json_encode(['success' => false, 'error' => 'Variant not found']);
        exit;
    }

    if ($product['stock_status'] !== 'in_stock') {
        echo json_encode(['success' => false, 'error' => 'Inventory can only be managed when product status is "in_stock"']);
        exit;
    }

    // Get current quantity
    $currentStmt = $pdo->prepare("SELECT quantity FROM product_inventory WHERE variant_id = :variant_id AND size = :size");
    $currentStmt->execute([':variant_id' => $variantId, ':size' => $size]);
    $currentInventory = $currentStmt->fetch();
    $oldQuantity = $currentInventory ? intval($currentInventory['quantity']) : 0;

    // Check if 'updated_at' column exists
    $columns = $pdo->query("SHOW COLUMNS FROM product_inventory LIKE 'updated_at'")->fetch();

    // Insert or update inventory (INSERT ON DUPLICATE KEY UPDATE)
    if ($columns) {
        $updateSql = "
            INSERT INTO product_inventory (variant_id, size, quantity, created_at, updated_at)
            VALUES (:variant_id, :size, :quantity, NOW(), NOW())
            ON DUPLICATE KEY UPDATE 
                quantity = :quantity,
                updated_at = NOW()
        ";
    } else {
        $updateSql = "
            INSERT INTO product_inventory (variant_id, size, quantity)
            VALUES (:variant_id, :size, :quantity)
            ON DUPLICATE KEY UPDATE quantity = :quantity
        ";
    }

    $stmt = $pdo->prepare($updateSql);
    $stmt->execute([
        ':variant_id' => $variantId,
        ':size' => $size,
        ':quantity' => $quantity
    ]);

    // Log the change
    error_log(sprintf(
        "Admin Action: User %s updated inventory for product '%s', variant '%s', size '%s' from %d to %d",
        $_SESSION['admin_username'] ?? 'unknown',
        $product['product_name'],
        $product['variant_name'],
        $size,
        $oldQuantity,
        $quantity
    ));

    echo json_encode([
        'success' => true,
        'message' => 'Inventory updated successfully',
        'variant_id' => $variantId,
        'size' => $size,
        'old_quantity' => $oldQuantity,
        'new_quantity' => $quantity
    ]);

} catch (PDOException $e) {
    error_log('Inventory update error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Database error']);
}

