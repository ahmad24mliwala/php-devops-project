<?php
require '../includes/db.php';
header('Content-Type: application/json');

$valid_statuses = ['pending', 'processing', 'shipped', 'completed', 'cancelled'];

try {
    $total_orders = $pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn();
    $new_today_count = $pdo->query("SELECT COUNT(*) FROM orders WHERE DATE(created_at)=CURDATE()")->fetchColumn();

    $status_counts = [];
    foreach ($valid_statuses as $s) {
        $status_counts[$s] = $pdo->query("SELECT COUNT(*) FROM orders WHERE status='$s'")->fetchColumn();
    }

    echo json_encode([
        'status' => 'success',
        'total' => $total_orders,
        'today' => $new_today_count,
        'status_counts' => $status_counts
    ]);
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
