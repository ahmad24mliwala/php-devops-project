<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

log_visit($pdo);

// Redirect if not logged in
if (!is_logged_in()) {
    flash('error', 'Please log in to access your account.');
    redirect('login.php');
}

// Fetch current user
$user = current_user();
if (!$user) {   // Protect against null
    session_destroy();
    redirect('login.php');
}

// Fetch user orders
$orders = get_user_orders($user['id']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>My Account - PickleHub</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.1/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="assets/style.css" rel="stylesheet">
</head>
<body>

<?php include __DIR__ . '/includes/header.php'; ?>

<div class="container my-5">
    <h2>Welcome, <?= h($user['name']) ?></h2>

    <div class="d-flex justify-content-between align-items-center mt-4">
        <h4>Your Orders</h4>
        <a href="my-account.php" class="btn btn-sm btn-secondary">Refresh</a>
    </div>

    <?php if(empty($orders)): ?>
        <p class="mt-3">You have no orders yet. <a href="products.php">Shop Now</a></p>
    <?php else: ?>
        <table class="table table-striped mt-3">
            <thead>
                <tr>
                    <th>Order ID</th>
                    <th>Date</th>
                    <th>Total</th>
                    <th>Status</th>
                    <th>Invoice</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($orders as $order): 
                    $status = $order['status'];
                    $badge = [
                        'pending' => 'warning',
                        'processing' => 'primary',
                        'shipped' => 'info',
                        'completed' => 'success',
                        'cancelled' => 'danger'
                    ][$status] ?? 'secondary';
                ?>
                <tr>
                    <td><?= h($order['id']) ?></td>
                    <td><?= h($order['created_at']) ?></td>
                    <td>₹<?= number_format($order['total_amount'], 2) ?></td>
                    <td><span class="badge bg-<?= $badge ?>"><?= h(ucfirst($status)) ?></span></td>
                    <td>
                        <a href="invoice.php?order_id=<?= h($order['id']) ?>" class="btn btn-sm btn-primary" target="_blank">Download Invoice</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
