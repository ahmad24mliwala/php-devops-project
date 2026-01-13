<?php
require '../includes/db.php';
header('Content-Type: text/html');

$orders = $pdo->query("
    SELECT * FROM orders 
    WHERE status NOT IN ('completed','cancelled') 
      AND DATE(created_at)=CURDATE() 
    ORDER BY created_at DESC LIMIT 5
")->fetchAll();

if (!$orders) {
    echo '<tr><td colspan="5" class="text-center text-muted">No recent orders.</td></tr>';
    exit;
}

foreach ($orders as $order) {
    echo "<tr>
        <td>".htmlspecialchars($order['id'])."</td>
        <td>".htmlspecialchars($order['name'] ?? 'N/A')."</td>
        <td>₹".number_format($order['total_amount'], 2)."</td>
        <td>".htmlspecialchars(ucfirst($order['status']))."</td>
        <td><a href='orders.php' class='btn btn-sm btn-primary'>Manage</a></td>
    </tr>";
}
