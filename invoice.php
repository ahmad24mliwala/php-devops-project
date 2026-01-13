<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/mail.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Logging helper – FIXED PATH
$logFile = __DIR__ . '/invoice_debug.txt';
function log_invoice($msg) {
    global $logFile;
    file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] $msg\n", FILE_APPEND);
}
log_invoice("🧾 invoice.php loaded");

$order_id = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;

// Try session-based fallback
if (!$order_id && isset($_SESSION['just_order_id'])) {
    $order_id = (int)$_SESSION['just_order_id'];
    log_invoice("✅ Found order_id in session: $order_id");
}

// Try fetch latest order for logged user
if (!$order_id && isset($_SESSION['user']['id'])) {
    $stmt = $pdo->prepare("SELECT id FROM orders WHERE user_id=? ORDER BY created_at DESC LIMIT 1");
    $stmt->execute([$_SESSION['user']['id']]);
    if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $order_id = (int)$row['id'];
        log_invoice("✅ Found latest order for user: $order_id");
    }
}

if (!$order_id) {
    log_invoice("❌ No order_id found.");
    die("<p style='color:red;text-align:center;margin-top:50px;'>Invalid or missing order ID.</p>");
}

// Fetch order
$stmt = $pdo->prepare("SELECT * FROM orders WHERE id=? LIMIT 1");
$stmt->execute([ $order_id ]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    log_invoice("❌ Order not found: $order_id");
    die("<p style='color:red;text-align:center;margin-top:50px;'>Order not found.</p>");
}

// Ownership validation (allow admin if needed)
if (isset($_SESSION['user']['role']) && $_SESSION['user']['role'] === 'admin') {
    // Admin allowed
} else {
    if (isset($_SESSION['user']['id']) && $order['user_id'] != $_SESSION['user']['id']) {
        log_invoice("⚠ Unauthorized user {$_SESSION['user']['id']} tried to view order $order_id");
        die("<p style='color:red;text-align:center;margin-top:50px;'>Unauthorized access.</p>");
    }
}

// Fetch order items
$stmt_items = $pdo->prepare("
    SELECT oi.*, COALESCE(p.name, oi.product_name) AS name
    FROM order_items oi
    LEFT JOIN products p ON oi.product_id = p.id
    WHERE oi.order_id = ?
");
$stmt_items->execute([$order_id]);
$items = $stmt_items->fetchAll(PDO::FETCH_ASSOC) ?: [];

$invoiceDate = date('d M Y, h:i A', strtotime($order['created_at']));

// Prevent duplicate email
if (!isset($_SESSION['invoice_sent'])) $_SESSION['invoice_sent'] = [];
$shouldSendEmail = !isset($_SESSION['invoice_sent'][$order_id]);
$_SESSION['invoice_sent'][$order_id] = true;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Invoice #<?=h($order['id'])?> - Avoji Foods</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.1/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
body { background:#f4f6f8;font-family:'Segoe UI',sans-serif;padding:15px; }
.invoice-container { max-width:900px;margin:auto;background:#fff;border-radius:10px;padding:25px;box-shadow:0 2px 10px rgba(0,0,0,0.08);}
h2 { text-align:center;color:#28a745;font-weight:600;margin-bottom:25px; }
.invoice-details p { margin:3px 0;font-size:15px; }
.table { width:100%;margin-top:20px;font-size:15px; }
.table th { background:#198754;color:#fff;text-align:center;vertical-align:middle; }
.table td { text-align:center;vertical-align:middle; }
tfoot td { font-weight:bold; }
.no-print { text-align:center;margin-top:20px; }
@media print { .no-print{display:none;} }
@media (max-width:768px){
  body{padding:5px;}
  .invoice-container{padding:15px;border-radius:5px;}
  h2{font-size:20px;}
  .table{font-size:13px;}
}
</style>
</head>
<body>
<div class="invoice-container">
  <h2>Invoice - Avoji Foods</h2>

  <div class="invoice-details mb-4">
    <p><strong>Order #:</strong> <?=h($order['id'])?></p>
    <p><strong>Date:</strong> <?=h($invoiceDate)?></p>
    <p><strong>Customer:</strong> <?=h($order['name'])?> (<?=h($order['email'])?>)</p>
    <p><strong>Phone:</strong> <?=h($order['phone'])?></p>
    <p><strong>Address:</strong><br><?=nl2br(h($order['shipping_address']))?></p>
    <p><strong>Payment:</strong> <?=ucfirst(h($order['payment_method']))?></p>
    <p><strong>Status:</strong> <?=ucfirst(h($order['status']))?></p>
  </div>

  <?php if ($items): ?>
  <div class="table-responsive">
    <table class="table table-bordered align-middle">
      <thead><tr><th>Product</th><th>Qty</th><th>Unit Price (₹)</th><th>Total (₹)</th></tr></thead>
      <tbody>
      <?php $grand_total=0; foreach($items as $i): $line=$i['price']*$i['quantity']; $grand_total+=$line; ?>
      <tr>
        <td><?=h($i['name'])?></td>
        <td><?=h($i['quantity'])?></td>
        <td><?=number_format($i['price'],2)?></td>
        <td><?=number_format($line,2)?></td>
      </tr>
      <?php endforeach; ?>
      </tbody>
      <tfoot><tr><td colspan="3" class="text-end">Grand Total</td><td><strong>₹<?=number_format($grand_total,2)?></strong></td></tr></tfoot>
    </table>
  </div>
  <?php else: ?>
    <p class="text-danger text-center">No items found for this order.</p>
  <?php endif; ?>

  <div class="no-print">
    <button class="btn btn-success" onclick="window.print()">🖨️ Print Invoice</button>
    <a href="./index.php" class="btn btn-secondary">🏠 Back to Home</a>
  </div>
</div>

<?php if ($shouldSendEmail): ?>
<script>
fetch('send_invoice_email.php?order_id=<?= (int)$order_id ?>', {
    method:'GET',
    cache:'no-store'
})
.then(r => r.json())
.then(j => console.log('Invoice email sent:', j))
.catch(err => console.warn('Async mail error', err));
</script>
<?php endif; ?>

</body>
</html>
