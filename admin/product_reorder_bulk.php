<?php
session_start();
require '../includes/db.php';
require '../includes/functions.php';
require __DIR__ . '/clear_home_cache.php';

is_admin();

$data = json_decode(file_get_contents("php://input"), true);

if (!hash_equals($_SESSION['csrf_token'], $data['csrf_token'] ?? '')) {
    echo json_encode(['success' => false]);
    exit;
}

$pdo->beginTransaction();

try {
    foreach ($data['order'] as $row) {
        $stmt = $pdo->prepare("UPDATE products SET sort_order=? WHERE id=?");
        $stmt->execute([
            (int)$row['position'],
            (int)$row['id']
        ]);
    }

    $pdo->commit();
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false]);
}
