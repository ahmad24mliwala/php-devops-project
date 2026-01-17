<?php
// admin/product_update.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

is_admin(); // 🔐 Admin only

header('Content-Type: application/json; charset=utf-8');

try {

    /* ------------------------------------
       BASIC VALIDATION
    ------------------------------------ */
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) {
        throw new Exception('Invalid product ID.');
    }

    $exists = $pdo->prepare("SELECT image FROM products WHERE id = ?");
    $exists->execute([$id]);
    $existingProduct = $exists->fetch(PDO::FETCH_ASSOC);

    if (!$existingProduct) {
        throw new Exception('Product not found.');
    }

    /* ------------------------------------
       REQUIRED FIELDS
    ------------------------------------ */
    $name = trim($_POST['name'] ?? '');
    if ($name === '') {
        throw new Exception('Product name is required.');
    }

    $price       = (float)($_POST['price'] ?? 0);
    $stock       = (int)($_POST['stock'] ?? 0);   // ✅ FIX
    $weight      = trim($_POST['weight'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $category_id = (int)($_POST['category_id'] ?? 0);

    if ($category_id <= 0) {
        throw new Exception('Please select a category.');
    }

    /* ------------------------------------
       CHECKBOXES
    ------------------------------------ */
    $is_cart     = isset($_POST['is_cart_enabled']) ? 1 : 0;
    $is_price    = isset($_POST['price_enabled']) ? 1 : 0;
    $is_featured = isset($_POST['is_featured']) ? 1 : 0;

    /* 🧠 AUTO DISABLE CART WHEN STOCK = 0 */
    if ($stock <= 0) {
        $is_cart = 0;
    }

    /* ------------------------------------
       IMAGE UPLOAD (OPTIONAL)
    ------------------------------------ */
    $newImage  = null;
    $uploadDir = realpath(__DIR__ . '/../uploads') . '/';

    if (!empty($_FILES['image']['tmp_name'])) {

        $img = $_FILES['image'];

        if ($img['size'] > 2 * 1024 * 1024) {
            throw new Exception('Image too large. Max 2MB allowed.');
        }

        $ext = strtolower(pathinfo($img['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'webp'];

        if (!in_array($ext, $allowed, true)) {
            throw new Exception('Invalid image format.');
        }

        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $newImage = time() . '_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $img['name']);

        move_uploaded_file($img['tmp_name'], $uploadDir . $newImage);

        if (!empty($existingProduct['image'])) {
            $oldPath = $uploadDir . $existingProduct['image'];
            if (is_file($oldPath)) {
                @unlink($oldPath);
            }
        }
    }

    /* ------------------------------------
       UPDATE QUERY
    ------------------------------------ */
    $fields = [
        'name = ?',
        'price = ?',
        'stock = ?',              // ✅ FIX
        'weight = ?',
        'description = ?',
        'category_id = ?',
        'is_cart_enabled = ?',
        'price_enabled = ?',
        'is_featured = ?'
    ];

    $params = [
        $name,
        $price,
        $stock,
        $weight,
        $description,
        $category_id,
        $is_cart,
        $is_price,
        $is_featured
    ];

    if ($newImage !== null) {
        $fields[] = 'image = ?';
        $params[] = $newImage;
    }

    $params[] = $id;

    $sql = "UPDATE products SET " . implode(', ', $fields) . " WHERE id = ?";
    $pdo->prepare($sql)->execute($params);

    log_admin_activity('update', 'product', $id, "Product updated: {$name}");

    echo json_encode([
        'status' => 'success',
        'message' => 'Product updated successfully'
    ]);
    exit;

} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
    exit;
}
