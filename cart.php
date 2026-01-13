<?php
// public/cart.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
log_visit($pdo);

// Debug log – FIXED PATH
$logFile = __DIR__ . '/cart_debug.txt';
file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] 🧾 cart.php loaded\n", FILE_APPEND);

// 🔹 Handle quantity updates
if (isset($_GET['action']) && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    if ($id > 0 && isset($_SESSION['cart'][$id])) {
        switch ($_GET['action']) {
            case 'increase':
                $_SESSION['cart'][$id]['qty']++;
                break;
            case 'decrease':
                $_SESSION['cart'][$id]['qty']--;
                if ($_SESSION['cart'][$id]['qty'] <= 0) {
                    unset($_SESSION['cart'][$id]);
                }
                break;
            case 'remove':
                unset($_SESSION['cart'][$id]);
                break;
        }
    }
    header("Location: cart.php");
    exit;
}

// 🔹 Redirect if cart is empty
if (empty($_SESSION['cart'])) {
    file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] ⚠️ Cart empty, redirecting\n", FILE_APPEND);
    header('Location: products.php');
    exit;
}

// 🔹 Fetch product details
$cart_items = [];
$total = 0;

// FIX: Safe ID list
$ids_arr = array_map('intval', array_keys($_SESSION['cart']));
$ids = implode(',', $ids_arr);

if (!$ids) {
    header("Location: products.php");
    exit;
}

$stmt = $pdo->query("SELECT id,name,price,image FROM products WHERE id IN ($ids)");
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($products as $prod) {
    $qty = $_SESSION['cart'][$prod['id']]['qty'] ?? 1;
    $prod['qty'] = $qty;
    $prod['subtotal'] = $prod['price'] * $qty;

    // FIX: Correct placeholder path
    $prod['image_path'] = $prod['image']
        ? 'image.php?file=' . urlencode($prod['image'])
        : 'image.php?file=product_placeholder.jpg';

    $total += $prod['subtotal'];
    $cart_items[] = $prod;
}

$order_placed = false;

// 🔹 Handle order submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name  = trim($_POST['name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $address = trim($_POST['address']);
    $city = trim($_POST['city']);
    $state = trim($_POST['state']);
    $zip = trim($_POST['zip']);
    $payment_method = $_POST['payment_method'] ?? 'cod';

    $shipping_address = "$address, $city, $state - $zip";
    $status = ($payment_method === 'cod') ? 'pending' : 'processing';
    $user_id = $_SESSION['user']['id'] ?? null;

    try {
        $pdo->beginTransaction();
        file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] 🔵 Transaction started\n", FILE_APPEND);

        // Insert order
        $sql = "INSERT INTO orders 
            (user_id, name, email, phone, shipping_address, total_amount, payment_method, status, order_date, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$user_id, $name, $email, $phone, $shipping_address, $total, $payment_method, $status]);
        $order_id = $pdo->lastInsertId();

        file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] ✅ Order inserted | ID: $order_id\n", FILE_APPEND);

        // Insert order items
        $has_product_name = false;
        $check = $pdo->query("SHOW COLUMNS FROM order_items LIKE 'product_name'");
        if ($check->rowCount() > 0) {
            $has_product_name = true;
        }

        if ($has_product_name) {
            $stmt_item = $pdo->prepare("
                INSERT INTO order_items (order_id, product_id, product_name, quantity, price)
                VALUES (?, ?, ?, ?, ?)
            ");
            foreach ($cart_items as $item) {
                $stmt_item->execute([$order_id, $item['id'], $item['name'], $item['qty'], $item['price']]);
            }
        } else {
            $stmt_item = $pdo->prepare("
                INSERT INTO order_items (order_id, product_id, quantity, price)
                VALUES (?, ?, ?, ?)
            ");
            foreach ($cart_items as $item) {
                $stmt_item->execute([$order_id, $item['id'], $item['qty'], $item['price']]);
            }
        }

        file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] 🧾 Order items inserted successfully\n", FILE_APPEND);

        $pdo->commit();

        $_SESSION['cart'] = [];
        $_SESSION['just_order_id'] = $order_id;
        $order_placed = true;

        header("Location: invoice.php?order_id=$order_id");
        exit;

    } catch (Exception $e) {
        $pdo->rollBack();
        $errorMsg = "❌ ORDER FAILED: " . $e->getMessage();
        file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] $errorMsg\n", FILE_APPEND);
        die("<pre style='background:#fee;padding:10px;border:1px solid red;'>$errorMsg</pre>");
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Checkout - PickleHub</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.1/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
.cart-table img { width: 80px; height: 80px; object-fit: cover; }
@media(max-width:768px){ .cart-table img { width:60px; height:60px; } }
</style>
</head>
<body>
<?php include __DIR__ . '/includes/header.php'; ?>

<div class="container my-5">
    <h2 class="mb-4 text-center">Checkout</h2>

    <?php if($order_placed): ?>
        <div class="alert alert-success text-center">
            Your order has been placed successfully! Thank you for shopping with PickleHub.<br>
            <a href="products.php" class="btn btn-success mt-3">Shop More</a>
            <a href="invoice.php?order_id=<?= $_SESSION['just_order_id'] ?? '' ?>" class="btn btn-primary mt-3">Generate Invoice</a>
        </div>
    <?php else: ?>

    <div class="row g-4">
        <!-- Cart Summary -->
        <div class="col-lg-6">
            <h4>Order Summary</h4>
            <div class="table-responsive">
                <table class="table table-hover align-middle cart-table">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th class="text-center">Qty</th>
                            <th class="text-end">Subtotal</th>
                            <th class="text-center">Remove</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($cart_items as $item): ?>
                        <tr>
                            <td>
                                <img src="<?=h($item['image_path'])?>" alt="<?=h($item['name'])?>" class="me-2 rounded">
                                <?=h($item['name'])?>
                            </td>
                            <td class="text-center">
                                <div class="d-flex justify-content-center align-items-center">
                                    <a href="cart.php?action=decrease&id=<?=h($item['id'])?>" class="btn btn-outline-danger btn-sm px-2">−</a>
                                    <span class="mx-2"><?=h($item['qty'])?></span>
                                    <a href="cart.php?action=increase&id=<?=h($item['id'])?>" class="btn btn-outline-success btn-sm px-2">+</a>
                                </div>
                            </td>
                            <td class="text-end">₹<?=number_format($item['subtotal'],2)?></td>
                            <td class="text-center">
                                <a href="cart.php?action=remove&id=<?=h($item['id'])?>" class="btn btn-outline-secondary btn-sm">🗑</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <th colspan="3" class="text-end">Total:</th>
                            <th class="text-end">₹<?=number_format($total,2)?></th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <!-- Billing Form -->
        <div class="col-lg-6">
            <h4>Billing Details</h4>
            <form method="POST" class="row g-3">
                <div class="col-12"><input type="text" name="name" class="form-control" placeholder="Full Name" required></div>
                <div class="col-12"><input type="email" name="email" class="form-control" placeholder="Email" required></div>
                <div class="col-12"><input type="tel" name="phone" class="form-control" placeholder="Phone" required></div>
                <div class="col-12"><textarea name="address" class="form-control" placeholder="Address" rows="2" required></textarea></div>
                <div class="col-md-6"><input type="text" name="city" class="form-control" placeholder="City" required></div>
                <div class="col-md-3"><input type="text" name="state" class="form-control" placeholder="State" required></div>
                <div class="col-md-3"><input type="text" name="zip" class="form-control" placeholder="ZIP/Postal Code" required></div>
                <div class="col-12">
                    <label class="form-label">Payment Method</label>
                    <select name="payment_method" class="form-select" required>
                        <option value="cod">Cash on Delivery</option>
                    </select>
                </div>
                <div class="col-12 text-end">
                    <button type="submit" class="btn btn-success btn-lg">Place Order</button>
                </div>
            </form>
        </div>
    </div>

    <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
