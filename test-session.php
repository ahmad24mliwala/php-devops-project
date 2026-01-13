<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$stmt = $pdo->query("SELECT COUNT(*) AS total_orders FROM orders");
$row = $stmt->fetch(PDO::FETCH_ASSOC);
echo "<h2>✅ Connected to DB '{$pdo->query('SELECT DATABASE()')->fetchColumn()}'</h2>";
echo "<p>Total orders found: {$row['total_orders']}</p>";
?>
