<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/functions.php';
if (session_status() == PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['role'] ?? '', ['admin','super_admin'])) {
    header('Location: login.php');
    exit;
}

$isSuperAdmin = ($_SESSION['user']['role'] === 'super_admin');

// Dashboard Data
function get_dashboard_data($pdo) {
    return [
        'orders'    => $pdo->query("SELECT COUNT(*) FROM orders WHERE status NOT IN ('completed','cancelled') AND DATE(created_at)=CURDATE()")->fetchColumn(),
        'revenue'   => $pdo->query("SELECT SUM(total_amount) FROM orders WHERE status NOT IN ('completed','cancelled') AND DATE(created_at)=CURDATE()")->fetchColumn() ?: 0,
        'products'  => $pdo->query("SELECT COUNT(*) FROM products")->fetchColumn(),
        'customers' => $pdo->query("SELECT COUNT(*) FROM users WHERE role='customer'")->fetchColumn(),
        'visits'    => $pdo->query("SELECT COUNT(*) FROM visits WHERE DATE(visited_at)=CURDATE()")->fetchColumn()
    ];
}
$stats = get_dashboard_data($pdo);

// 7-Day Chart Data
$dates = $revenue_data = $orders_data = $visits_data = [];
for ($i = 6; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i days"));
    $dates[] = date('D', strtotime($d));
    $revenue_data[] = $pdo->query("SELECT SUM(total_amount) FROM orders WHERE DATE(created_at)='$d' AND status NOT IN ('completed','cancelled')")->fetchColumn() ?: 0;
    $orders_data[]  = $pdo->query("SELECT COUNT(*) FROM orders WHERE DATE(created_at)='$d' AND status NOT IN ('completed','cancelled')")->fetchColumn() ?: 0;
    $visits_data[]  = $pdo->query("SELECT COUNT(*) FROM visits WHERE DATE(visited_at)='$d'")->fetchColumn() ?: 0;
}

// Recent Orders
$recent_orders = $pdo->query("SELECT * FROM orders WHERE status NOT IN ('completed','cancelled') AND DATE(created_at)=CURDATE() ORDER BY created_at DESC LIMIT 5")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Admin Dashboard - PickleHub</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.1/dist/css/bootstrap.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
/* your styles remain unchanged */
body { background-color:#f8f9fa; font-family:'Segoe UI',sans-serif; overflow-x:hidden; }
.clickable { cursor:pointer; transition:transform .3s,box-shadow .3s; }
.clickable:hover { transform:translateY(-4px); box-shadow:0 8px 18px rgba(0,0,0,.15); }
.card-modern { border-radius:14px; color:#fff; box-shadow:0 6px 20px rgba(0,0,0,.12); position:relative;}
.badge-today{position:absolute;top:8px;right:10px;background:rgba(255,255,255,.85);color:#000;font-size:.7rem;border-radius:12px;padding:2px 6px;}
canvas{background:#fff;border-radius:12px;padding:10px;width:100%!important;height:260px!important;}

/* ✔ RESTORED GRADIENT COLORS */
.bg-primary-grad{
    background:linear-gradient(135deg,#6f42c1,#d63384);
}
.bg-success-grad{
    background:linear-gradient(135deg,#198754,#20c997);
}
.bg-info-grad{
    background:linear-gradient(135deg,#0dcaf0,#0d6efd);
}
.bg-warning-grad{
    background:linear-gradient(135deg,#ffc107,#fd7e14);
}
.bg-secondary-grad{
    background:linear-gradient(135deg,#6c757d,#adb5bd);
}
</style>
</head>
<body>

<?php include __DIR__.'/header.php'; ?>

<div class="container my-4">
  <div class="d-flex justify-content-between align-items-center flex-wrap mb-3">
    <h2 class="text-success fw-bold mb-2 mb-md-0">Admin Dashboard</h2>
    <button class="btn btn-sm btn-success" id="refreshNow">🔄 Refresh Now</button>
  </div>

  <!-- CARDS -->
  <div class="row g-3 mt-3 justify-content-center">

    <div class="col-lg-2 col-md-3 col-sm-6">
      <div id="card-orders" class="card card-modern clickable bg-primary-grad" onclick="location.href='orders.php'">
        <span class="badge-today">Today</span>
        <div class="card-body"><h5>Total Orders</h5><p class="fs-4" id="ordersCount"><?=$stats['orders']?></p></div>
      </div>
    </div>

    <div class="col-lg-2 col-md-3 col-sm-6">
      <div id="card-revenue" class="card card-modern clickable bg-success-grad" onclick="location.href='revenue.php'">
        <span class="badge-today">Today</span>
        <div class="card-body"><h5>Revenue</h5><p class="fs-4" id="revenueCount">₹<?=number_format($stats['revenue'],2)?></p></div>
      </div>
    </div>

    <div class="col-lg-2 col-md-3 col-sm-6">
      <div id="card-products" class="card card-modern clickable bg-info-grad" onclick="location.href='products.php'">
        <div class="card-body"><h5>Products</h5><p class="fs-4" id="productCount"><?=$stats['products']?></p></div>
      </div>
    </div>

    <div class="col-lg-2 col-md-3 col-sm-6">
      <div id="card-customers" class="card card-modern clickable bg-warning-grad" onclick="location.href='customers.php'">
        <div class="card-body"><h5>Customers</h5><p class="fs-4" id="customerCount"><?=$stats['customers']?></p></div>
      </div>
    </div>

    <div class="col-lg-2 col-md-3 col-sm-6">
      <div id="card-visits" class="card card-modern clickable bg-secondary-grad" onclick="location.href='visits.php'">
        <span class="badge-today">Today</span>
        <div class="card-body"><h5>Visits</h5><p class="fs-4" id="visitCount"><?=$stats['visits']?></p></div>
      </div>
    </div>

  </div>

  <!-- RECENT ORDERS -->
  <h4 class="mt-5 text-success">Recent Orders (Today)</h4>
  <div class="table-responsive mt-3">
    <table class="table table-striped align-middle">
      <thead class="table-success">
        <tr><th>ID</th><th>Customer</th><th>Total</th><th>Status</th><th>Action</th></tr>
      </thead>
      <tbody id="recentOrdersBody">
        <?php foreach($recent_orders as $order): ?>
          <tr>
            <td><?=h($order['id'])?></td>
            <td><?=h($order['name'] ?? 'N/A')?></td>
            <td>₹<?=number_format($order['total_amount'],2)?></td>
            <td><?=h(ucfirst($order['status']))?></td>
            <td><a href="orders.php" class="btn btn-sm btn-primary">Manage</a></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <!-- CHARTS -->
  <h4 class="mt-5 text-success">Analytics (Last 7 Days)</h4>
  <div class="row g-3">
    <div class="col-md-4 col-sm-12"><canvas id="chartRevenue"></canvas></div>
    <div class="col-md-4 col-sm-12"><canvas id="chartOrders"></canvas></div>
    <div class="col-md-4 col-sm-12"><canvas id="chartVisits"></canvas></div>
  </div>

</div>

<script>
const labels = <?= json_encode($dates) ?>;
let revChart, ordChart, visChart;

function createCharts(){
  revChart=new Chart(document.getElementById('chartRevenue'),{
      type:'line',
      data:{labels,datasets:[{label:'Revenue (₹)',data:<?=json_encode($revenue_data)?>,borderColor:'#28a745',backgroundColor:'rgba(40,167,69,0.2)',fill:true,tension:0.4}]},
      options:{responsive:true,maintainAspectRatio:false}
  });

  ordChart=new Chart(document.getElementById('chartOrders'),{
      type:'bar',
      data:{labels,datasets:[{label:'Orders',data:<?=json_encode($orders_data)?>,backgroundColor:'rgba(255,193,7,0.7)',borderRadius:6}]},
      options:{responsive:true,maintainAspectRatio:false}
  });

  visChart=new Chart(document.getElementById('chartVisits'),{
      type:'line',
      data:{labels,datasets:[{label:'Visits',data:<?=json_encode($visits_data)?>,borderColor:'#0d6efd',backgroundColor:'rgba(13,110,253,0.2)',fill:true,tension:0.4}]},
      options:{responsive:true,maintainAspectRatio:false}
  });
}
createCharts();
</script>

<!-- Admin JavaScript -->
<script src="/picklehub_project/admin/assets/js/admin.js" defer></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.1/dist/js/bootstrap.bundle.min.js" defer></script>

</body>
</html>
