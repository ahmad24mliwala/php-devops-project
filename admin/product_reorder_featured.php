<?php
// admin/product_reorder_featured.php

ini_set('display_errors', 1);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require '../includes/db.php';
require '../includes/functions.php';

is_admin();

/* ==========================
   READ JSON INPUT
========================== */
$data = json_decode(file_get_contents('php://input'), true);

if (
    empty($data) ||
    empty($data['order']) ||
    !is_array($data['order'])
) {
    echo json_encode(['success' => false, 'message' => 'Invalid payload']);
    exit;
}

/* ==========================
   CSRF VALIDATION
========================== */
if (
    empty($_SESSION['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'], $data['csrf_token'] ?? '')
) {
    echo json_encode(['success' => false, 'message' => 'CSRF failed']);
    exit;
}

/* ==========================
   UPDATE ORDER
========================== */
try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare(
        "UPDATE products 
         SET featured_order = ? 
         WHERE id = ? AND is_featured = 1"
    );

    foreach ($data['order'] as $row) {
        if (!isset($row['id'], $row['position'])) continue;

        $stmt->execute([
            (int)$row['position'],
            (int)$row['id']
        ]);
    }

    $pdo->commit();

    // Clear homepage cache AFTER successful update
    require __DIR__ . '/clear_home_cache.php';
    clear_home_cache();

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode([
        'success' => false,
        'message' => 'DB error'
    ]);
}
