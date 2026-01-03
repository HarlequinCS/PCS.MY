<?php
/**
 * AJAX Handler: Update Product Price
 * POST parameters: product_id, new_price
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

$productId = intval($_POST['product_id'] ?? 0);
$newPrice = floatval($_POST['new_price'] ?? 0);

// Validation
if ($productId <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid product ID']);
    exit;
}

if ($newPrice <= 0 || $newPrice > 99999.99) {
    echo json_encode(['success' => false, 'error' => 'Price must be between 0.01 and 99999.99']);
    exit;
}

try {
    $pdo = getDBConnection();

    // Get current price for logging
    $currentStmt = $pdo->prepare("SELECT price, name FROM products WHERE id = :id");
    $currentStmt->execute([':id' => $productId]);
    $currentProduct = $currentStmt->fetch();

    if (!$currentProduct) {
        echo json_encode(['success' => false, 'error' => 'Product not found']);
        exit;
    }

    $oldPrice = floatval($currentProduct['price']);

    // Check if 'updated_at' column exists
    $columns = $pdo->query("SHOW COLUMNS FROM products LIKE 'updated_at'")->fetch();
    $updateSql = "UPDATE products SET price = :price";
    $params = [':price' => $newPrice, ':id' => $productId];

    if ($columns) {
        $updateSql .= ", updated_at = NOW()";
    }
    $updateSql .= " WHERE id = :id";

    // Update product price
    $stmt = $pdo->prepare($updateSql);
    $stmt->execute($params);

    if ($stmt->rowCount() > 0) {
        // Log the change
        error_log(sprintf(
            "Admin Action: User %s updated product #%d (%s) price from RM %.2f to RM %.2f",
            $_SESSION['admin_username'] ?? 'unknown',
            $productId,
            $currentProduct['name'],
            $oldPrice,
            $newPrice
        ));

        echo json_encode([
            'success' => true,
            'message' => 'Product price updated successfully',
            'product_id' => $productId,
            'old_price' => $oldPrice,
            'new_price' => $newPrice
        ]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Product not found or no changes made']);
    }

} catch (PDOException $e) {
    error_log('Product price update error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Database error']);
}

