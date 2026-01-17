<?php
require '../includes/db.php';
require '../includes/functions.php';

if (session_status() === PHP_SESSION_NONE) session_start();
is_admin();

if (!verify_csrf($_POST['csrf_token'] ?? '')) {
    echo json_encode(['status'=>'error','message'=>'Invalid CSRF']);
    exit;
}

$id  = (int)($_POST['id'] ?? 0);
$dir = $_POST['dir'] ?? '';

if (!$id || !in_array($dir, ['up','down'])) {
    echo json_encode(['status'=>'error']);
    exit;
}

/* Get current product */
$stmt = $pdo->prepare("SELECT id, sort_order FROM products WHERE id=?");
$stmt->execute([$id]);
$current = $stmt->fetch();

if (!$current) exit;

/* Find swap target */
$op = $dir === 'up' ? '<' : '>';
$order = $dir === 'up' ? 'DESC' : 'ASC';

$stmt = $pdo->prepare("
    SELECT id, sort_order
    FROM products
    WHERE sort_order $op ?
    ORDER BY sort_order $order
    LIMIT 1
");
$stmt->execute([$current['sort_order']]);
$swap = $stmt->fetch();

if ($swap) {
    $pdo->beginTransaction();

    $pdo->prepare("UPDATE products SET sort_order=? WHERE id=?")
        ->execute([$swap['sort_order'], $current['id']]);

    $pdo->prepare("UPDATE products SET sort_order=? WHERE id=?")
        ->execute([$current['sort_order'], $swap['id']]);

    $pdo->commit();
}

echo json_encode(['status'=>'success']);
