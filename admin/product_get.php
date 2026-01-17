<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
is_admin();

header('Content-Type: application/json; charset=utf-8');

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0) {
    echo json_encode([]);
    exit;
}

$stmt = $pdo->prepare("
    SELECT id, name, price, weight, description, category_id,
           is_cart_enabled, price_enabled, is_featured
    FROM products
    WHERE id = ?
    LIMIT 1
");
$stmt->execute([$id]);

$product = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$product) {
    echo json_encode([]);
    exit;
}

echo json_encode($product);
exit;
