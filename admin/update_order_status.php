<?php
require '../includes/db.php';
require '../includes/mail.php';
header('Content-Type: application/json');

$order_id = intval($_POST['order_id'] ?? 0);
$status = trim($_POST['status'] ?? '');
$valid_statuses = ['pending', 'processing', 'shipped', 'completed', 'cancelled'];

if ($order_id <= 0 || !in_array($status, $valid_statuses)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
    exit;
}

try {
    $pdo->beginTransaction();

    // ✅ Update order status
    $stmt = $pdo->prepare("UPDATE orders SET status = ? WHERE id = ?");
    $stmt->execute([$status, $order_id]);

    // ✅ Log the change
    $stmt = $pdo->prepare("INSERT INTO order_status_logs (order_id, status) VALUES (?, ?)");
    $stmt->execute([$order_id, $status]);

    // ✅ Fetch customer for email
    $stmt = $pdo->prepare("SELECT name, email FROM orders WHERE id = ? LIMIT 1");
    $stmt->execute([$order_id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($order && filter_var($order['email'], FILTER_VALIDATE_EMAIL)) {
        $subject = "Your PickleHub Order #$order_id is now " . ucfirst($status);
        $body = "
            <h2 style='color:#28a745;'>Hi " . htmlspecialchars($order['name']) . ",</h2>
            <p>Your order <strong>#$order_id</strong> status has been updated to:</p>
            <h3 style='color:#007bff;'>" . ucfirst($status) . "</h3>
            <p>Thank you for shopping with <strong>PickleHub</strong> 🥒.</p>
            <br><p>— Team PickleHub</p>
        ";
        send_email($order['email'], $subject, $body);
    }

    $pdo->commit();
    echo json_encode(['status' => 'success']);
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
