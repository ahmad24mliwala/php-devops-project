<?php
require __DIR__ . '/../includes/db.php';
if (session_status() == PHP_SESSION_NONE) session_start();

// Ensure user is admin or super admin
if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['role'] ?? '', ['admin','super_admin'])) {
    http_response_code(403);
    exit;
}

// Prepare analytics for last 7 days
$dates = $revenue_data = $orders_data = $visits_data = [];

for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $dates[] = date('D', strtotime($date));

    $stmt = $pdo->prepare("SELECT SUM(total_amount) FROM orders WHERE DATE(created_at)=?");
    $stmt->execute([$date]);
    $revenue_data[] = $stmt->fetchColumn() ?: 0;

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE DATE(created_at)=?");
    $stmt->execute([$date]);
    $orders_data[] = $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM visits WHERE DATE(visited_at)=?");
    $stmt->execute([$date]);
    $visits_data[] = $stmt->fetchColumn();
}

echo json_encode([
    'dates' => $dates,
    'revenue' => $revenue_data,
    'orders' => $orders_data,
    'visits' => $visits_data
]);
