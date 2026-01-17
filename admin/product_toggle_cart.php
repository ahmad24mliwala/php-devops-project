<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
is_admin();

header('Content-Type: application/json; charset=utf-8');

/* ==========================
   CSRF CHECK
========================== */
if (
    empty($_POST['csrf_token']) ||
    empty($_SESSION['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
) {
    echo json_encode([
        "status" => "error",
        "message" => "Invalid CSRF token"
    ]);
    exit;
}

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) {
    echo json_encode([
        "status" => "error",
        "message" => "Invalid product ID"
    ]);
    exit;
}

/* ==========================
   FETCH PRODUCT
========================== */
$stmt = $pdo->prepare("SELECT id, stock, is_cart_enabled FROM products WHERE id = ?");
$stmt->execute([$id]);
$product = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$product) {
    echo json_encode([
        "status" => "error",
        "message" => "Product not found"
    ]);
    exit;
}

/* ==========================
   🧠 AUTO STOCK LOGIC
========================== */
if ($product['stock'] <= 0) {
    $pdo->prepare("UPDATE products SET is_cart_enabled = 0 WHERE id = ?")
        ->execute([$id]);

    echo json_encode([
        "status" => "success",
        "message" => "Cart disabled (out of stock)"
    ]);
    exit;
}

/* ==========================
   TOGGLE CART
========================== */
$newState = $product['is_cart_enabled'] ? 0 : 1;

$pdo->prepare("UPDATE products SET is_cart_enabled = ? WHERE id = ?")
    ->execute([$newState, $id]);

log_admin_activity(
    'update',
    'product',
    $id,
    'Toggled cart to ' . ($newState ? 'enabled' : 'disabled')
);

echo json_encode([
    "status" => "success",
    "message" => "Cart updated successfully",
    "is_cart_enabled" => $newState
]);
exit;
