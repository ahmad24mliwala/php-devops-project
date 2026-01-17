<?php
declare(strict_types=1);

ini_set('display_errors', '0'); // ❗ hide in production
ini_set('log_errors', '1');

require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/functions.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

/* ==========================
   SECURITY HEADERS
========================== */
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

/* ==========================
   AUTH
========================== */
is_admin();

$isSuperAdmin = isset($_SESSION['user']['role']) && $_SESSION['user']['role'] === 'super_admin';

/* ==========================
   DATE RANGE (SAFE)
========================== */
$todayStart = date('Y-m-d 00:00:00');
$todayEnd   = date('Y-m-d 23:59:59');

/* ==========================
   DASHBOARD STATS (SAFE PDO)
========================== */
$stats = [];

/* Orders */
$stmt = $pdo->prepare("
    SELECT COUNT(*) 
    FROM orders 
    WHERE status!='cancelled' 
    AND created_at BETWEEN ? AND ?
");
$stmt->execute([$todayStart, $todayEnd]);
$stats['orders'] = (int)$stmt->fetchColumn();

/* Revenue */
$stmt = $pdo->prepare("
    SELECT COALESCE(SUM(total_amount),0)
    FROM orders
    WHERE status='completed'
    AND created_at BETWEEN ? AND ?
");
$stmt->execute([$todayStart, $todayEnd]);
$stats['revenue'] = (float)$stmt->fetchColumn();

/* Products */
$stats['products'] = (int)$pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();

/* Customers */
$stats['customers'] = (int)$pdo
    ->query("SELECT COUNT(*) FROM users WHERE role='customer'")
    ->fetchColumn();

/* Visits */
$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM visits
    WHERE visited_at BETWEEN ? AND ?
");
$stmt->execute([$todayStart, $todayEnd]);
$stats['visits'] = (int)$stmt->fetchColumn();

/* ==========================
   7 DAY ANALYTICS
========================== */
$days = [];
for ($i = 6; $i >= 0; $i--) {
    $days[] = date('Y-m-d', strtotime("-$i days"));
}

/* Visits */
$visitRows = $pdo->query("
    SELECT DATE(visited_at) d, COUNT(*) c
    FROM visits
    WHERE visited_at >= CURDATE() - INTERVAL 6 DAY
    GROUP BY d
")->fetchAll(PDO::FETCH_KEY_PAIR);

/* Orders */
$orderRows = $pdo->query("
    SELECT DATE(created_at) d, COUNT(*) c
    FROM orders
    WHERE created_at >= CURDATE() - INTERVAL 6 DAY
    AND status!='cancelled'
    GROUP BY d
")->fetchAll(PDO::FETCH_KEY_PAIR);

/* Revenue */
$revenueRows = $pdo->query("
    SELECT DATE(created_at) d, SUM(total_amount) s
    FROM orders
    WHERE created_at >= CURDATE() - INTERVAL 6 DAY
    AND status='completed'
    GROUP BY d
")->fetchAll(PDO::FETCH_KEY_PAIR);

/* Normalize */
$labels = $visitsData = $ordersData = $revenueData = [];
foreach ($days as $d) {
    $labels[]      = date('D', strtotime($d));
    $visitsData[]  = (int)($visitRows[$d] ?? 0);
    $ordersData[]  = (int)($orderRows[$d] ?? 0);
    $revenueData[] = (float)($revenueRows[$d] ?? 0);
}

/* ==========================
   RECENT ORDERS
========================== */
$stmt = $pdo->prepare("
    SELECT o.*, u.name
    FROM orders o
    LEFT JOIN users u ON u.id=o.user_id
    WHERE o.status!='cancelled'
    AND o.created_at BETWEEN ? AND ?
    ORDER BY o.created_at DESC
    LIMIT 5
");
$stmt->execute([$todayStart, $todayEnd]);
$recent_orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Admin Dashboard</title>
<meta name="viewport" content="width=device-width, initial-scale=1">

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.1/dist/css/bootstrap.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
body{background:#f8f9fa;font-family:'Segoe UI',sans-serif}
.card-modern{
  border-radius:14px;
  color:#fff;
  box-shadow:0 6px 20px rgba(0,0,0,.12);
  transition:transform .25s, box-shadow .25s;
}
.card-modern:hover{
  transform:translateY(-4px);
  box-shadow:0 12px 28px rgba(0,0,0,.18);
}
.badge-today{
  position:absolute;top:8px;right:10px;
  background:#fff;color:#000;
  font-size:.7rem;border-radius:12px;padding:2px 6px
}
canvas{background:#fff;border-radius:12px;padding:10px;height:260px!important}

.bg-primary-grad{background:linear-gradient(135deg,#6f42c1,#d63384)}
.bg-success-grad{background:linear-gradient(135deg,#198754,#20c997)}
.bg-info-grad{background:linear-gradient(135deg,#0dcaf0,#0d6efd)}
.bg-warning-grad{background:linear-gradient(135deg,#ffc107,#fd7e14)}
.bg-secondary-grad{background:linear-gradient(135deg,#6c757d,#adb5bd)}
</style>
</head>
<body>

<?php include __DIR__.'/header.php'; ?>

<div class="container my-4">

<div class="d-flex justify-content-between align-items-center mb-3">
  <h2 class="text-success fw-bold">Admin Dashboard</h2>
  <button class="btn btn-sm btn-success" onclick="location.reload()">🔄 Refresh</button>
</div>

<!-- KPI CARDS -->
<div class="row g-3 justify-content-center">

  <div class="col-lg-2 col-sm-6">
    <a href="orders.php" class="text-decoration-none">
      <div class="card card-modern bg-primary-grad position-relative">
        <span class="badge-today">Today</span>
        <div class="card-body">
          <h6>Orders</h6>
          <h4><?=$stats['orders']?></h4>
        </div>
      </div>
    </a>
  </div>

  <div class="col-lg-2 col-sm-6">
    <a href="revenue.php" class="text-decoration-none">
      <div class="card card-modern bg-success-grad position-relative">
        <span class="badge-today">Today</span>
        <div class="card-body">
          <h6>Revenue</h6>
          <h4>₹<?=number_format($stats['revenue'],2)?></h4>
        </div>
      </div>
    </a>
  </div>

  <div class="col-lg-2 col-sm-6">
    <a href="products.php" class="text-decoration-none">
      <div class="card card-modern bg-info-grad">
        <div class="card-body">
          <h6>Products</h6>
          <h4><?=$stats['products']?></h4>
        </div>
      </div>
    </a>
  </div>

  <div class="col-lg-2 col-sm-6">
    <a href="customers.php" class="text-decoration-none">
      <div class="card card-modern bg-warning-grad">
        <div class="card-body">
          <h6>Customers</h6>
          <h4><?=$stats['customers']?></h4>
        </div>
      </div>
    </a>
  </div>

  <div class="col-lg-2 col-sm-6">
    <a href="visits.php" class="text-decoration-none">
      <div class="card card-modern bg-secondary-grad position-relative">
        <span class="badge-today">Today</span>
        <div class="card-body">
          <h6>Visits</h6>
          <h4><?=$stats['visits']?></h4>
        </div>
      </div>
    </a>
  </div>

</div>

<!-- RECENT ORDERS -->
<h4 class="mt-5 text-success">Recent Orders (Today)</h4>
<table class="table table-striped mt-3">
<thead class="table-success">
<tr><th>ID</th><th>Customer</th><th>Total</th><th>Status</th></tr>
</thead>
<tbody>
<?php foreach($recent_orders as $o): ?>
<tr>
<td><?=h($o['id'])?></td>
<td><?=h($o['name'] ?? 'Guest')?></td>
<td>₹<?=number_format($o['total_amount'],2)?></td>
<td><?=ucfirst($o['status'])?></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>

<!-- CHARTS -->
<h4 class="mt-5 text-success">Analytics (Last 7 Days)</h4>
<div class="row g-3">
  <div class="col-md-4"><canvas id="revChart"></canvas></div>
  <div class="col-md-4"><canvas id="ordChart"></canvas></div>
  <div class="col-md-4"><canvas id="visChart"></canvas></div>
</div>

</div>

<script>
const labels = <?=json_encode($labels)?>;

new Chart(revChart,{
  type:'line',
  data:{labels,datasets:[{
    label:'Revenue',
    data:<?=json_encode($revenueData)?>,
    borderColor:'#28a745',
    backgroundColor:'rgba(40,167,69,.2)',
    fill:true,tension:.4
  }]}
});

new Chart(ordChart,{
  type:'bar',
  data:{labels,datasets:[{
    label:'Orders',
    data:<?=json_encode($ordersData)?>,
    backgroundColor:'rgba(255,193,7,.8)',
    borderRadius:6
  }]}
});

new Chart(visChart,{
  type:'line',
  data:{labels,datasets:[{
    label:'Visits',
    data:<?=json_encode($visitsData)?>,
    borderColor:'#0d6efd',
    backgroundColor:'rgba(13,110,253,.2)',
    fill:true,tension:.4
  }]}
});
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
