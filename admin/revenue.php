<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require '../includes/db.php';
require '../includes/functions.php';
is_admin();

/* ==========================
   RESET REVENUE DATA
========================== */
if (isset($_POST['confirm_reset'], $_POST['admin_password'])) {
    $password = trim($_POST['admin_password']);
    $admin_id = $_SESSION['user']['id'] ?? 0;

    $stmt = $pdo->prepare("SELECT password FROM users WHERE id=? LIMIT 1");
    $stmt->execute([$admin_id]);
    $hash = $stmt->fetchColumn();

    if ($hash && password_verify($password, $hash)) {
        $pdo->exec("DELETE FROM orders");
        $pdo->exec("DELETE FROM order_status_logs");
        flash('success', "✅ All revenue data has been reset successfully.");
    } else {
        flash('error', "❌ Invalid admin password. Reset aborted.");
    }

    header("Location: revenue.php");
    exit;
}

/* ==========================
   FILTERS
========================== */
$filter = $_GET['filter'] ?? 'all';
$statusFilter = $_GET['status'] ?? 'all';
$view_all = isset($_GET['view_all']);

$where = [];
$prevWhere = [];

if ($filter == 'today') {
    $where[] = "DATE(created_at)=CURDATE()";
    $prevWhere[] = "DATE(created_at)=CURDATE()-INTERVAL 1 DAY";
} elseif ($filter == 'week') {
    $where[] = "WEEK(created_at)=WEEK(CURDATE())";
    $prevWhere[] = "WEEK(created_at)=WEEK(CURDATE())-1";
} elseif ($filter == 'month') {
    $where[] = "MONTH(created_at)=MONTH(CURDATE())";
    $prevWhere[] = "MONTH(created_at)=MONTH(CURDATE())-1";
}

if ($statusFilter != 'all') {
    $where[] = "status='".$statusFilter."'";
}

$whereSQL = $where ? "WHERE ".implode(" AND ", $where) : "";
$prevWhereSQL = $prevWhere ? "WHERE ".implode(" AND ", $prevWhere) : "";

/* ==========================
   FETCH DATA
========================== */
$limit_sql = $view_all ? "" : "LIMIT 20";

$revenue = $pdo->query("
    SELECT SUM(total_amount) AS total, DATE(created_at) AS date
    FROM orders
    $whereSQL
    GROUP BY DATE(created_at)
    ORDER BY DATE(created_at) DESC
    $limit_sql
")->fetchAll();

$prevRevenue = $pdo->query("SELECT SUM(total_amount) FROM orders $prevWhereSQL")->fetchColumn();
$totalOrders = $pdo->query("SELECT COUNT(*) FROM orders $whereSQL")->fetchColumn();

$totalRevenue = array_sum(array_column($revenue, 'total'));
$percentChange = $prevRevenue ? round((($totalRevenue - $prevRevenue) / $prevRevenue) * 100, 2) : 0;

/* ==========================
   DOWNLOAD CSV
========================== */
if (isset($_GET['download'])) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename=revenue.csv');

    $out = fopen('php://output', 'w');
    fputcsv($out, ['Date', 'Revenue']);

    foreach ($revenue as $r) {
        fputcsv($out, [$r['date'], $r['total']]);
    }
    fclose($out);
    exit;
}

$labels = array_column($revenue, 'date');
$data   = array_column($revenue, 'total');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Revenue Analytics - Admin</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.1/dist/css/bootstrap.min.css" rel="stylesheet">

<style>
body { background: #fff; font-family: 'Poppins', sans-serif; }
.card-summary {
  color:white; text-align:center; padding:18px; border-radius:18px;
  box-shadow:0 4px 10px rgba(0,0,0,0.1);
}
.card-revenue { background: linear-gradient(135deg, #28a745, #20c997); }
.card-orders  { background: linear-gradient(135deg, #007bff, #00b4d8); }
.card-growth  { background: linear-gradient(135deg, #ff8c00, #ffa500); }
.btn-reset-small { font-size:.8rem; border-radius:8px; }
</style>
</head>

<body>

<?php include 'header.php'; ?>

<div class="container my-4">

  <!-- SUMMARY -->
  <div class="row text-center g-3 mb-4">
    <div class="col-md-3"><div class="card-summary card-revenue">
        <h6>Total Revenue</h6><h4>₹<?= number_format($totalRevenue,2) ?></h4>
    </div></div>
    <div class="col-md-3"><div class="card-summary card-orders">
        <h6>Total Orders</h6><h4><?= $totalOrders ?></h4>
    </div></div>
    <div class="col-md-3"><div class="card-summary card-growth">
        <h6>Growth</h6><h4><?= $percentChange>=0?'+':'' ?><?= $percentChange ?>%</h4>
    </div></div>
    <div class="col-md-3">
      <button class="btn btn-danger btn-sm mt-3" data-bs-toggle="modal" data-bs-target="#confirmResetModal">
        Reset Revenue
      </button>
    </div>
  </div>

  <?php if ($msg = flash('success')): ?>
    <div class="alert alert-success"><?= h($msg) ?></div>
  <?php elseif ($msg = flash('error')): ?>
    <div class="alert alert-danger"><?= h($msg) ?></div>
  <?php endif; ?>

  <!-- FILTERS -->
  <div class="d-flex gap-2 mb-3 flex-wrap">
    <a href="?filter=today&status=<?= $statusFilter ?>" class="btn btn-success btn-sm">Today</a>
    <a href="?filter=week&status=<?= $statusFilter ?>" class="btn btn-primary btn-sm">Week</a>
    <a href="?filter=month&status=<?= $statusFilter ?>" class="btn btn-warning btn-sm">Month</a>
    <a href="?filter=all&status=<?= $statusFilter ?>" class="btn btn-secondary btn-sm">All</a>

    <form method="GET" class="ms-auto d-flex gap-2">
      <input type="hidden" name="filter" value="<?= $filter ?>">
      <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
        <?php foreach(['all','pending','processing','shipped','completed','cancelled'] as $s): ?>
          <option value="<?=$s?>" <?= $statusFilter==$s?'selected':'' ?>><?= ucfirst($s) ?></option>
        <?php endforeach; ?>
      </select>
    </form>

    <a href="?download=1&filter=<?= $filter ?>&status=<?= $statusFilter ?>" class="btn btn-dark btn-sm">Download CSV</a>
  </div>

  <!-- TABLE + CHART -->
  <div class="row">
    <div class="col-md-6 mb-4">
      <table class="table table-bordered">
        <thead><tr><th>Date</th><th>Revenue</th></tr></thead>
        <tbody>
        <?php foreach($revenue as $r): ?>
          <tr><td><?=h($r['date'])?></td><td>₹<?=number_format($r['total'],2)?></td></tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot><tr><th>Total</th><th>₹<?=number_format($totalRevenue,2)?></th></tr></tfoot>
      </table>
    </div>

    <div class="col-md-6 mb-4">
      <canvas id="revenueChart" height="300"></canvas>
    </div>
  </div>

</div>

<!-- RESET MODAL -->
<div class="modal fade" id="confirmResetModal" tabindex="-1">
  <div class="modal-dialog">
    <form method="POST" class="modal-content">
      <div class="modal-header bg-danger text-white">
        <h5 class="modal-title">Confirm Reset Revenue</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="password" name="admin_password" class="form-control" placeholder="Enter admin password" required>
      </div>
      <div class="modal-footer">
        <button type="submit" name="confirm_reset" class="btn btn-danger btn-sm">Confirm</button>
      </div>
    </form>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
new Chart(document.getElementById('revenueChart'), {
  type: 'bar',
  data: {
    labels: <?= json_encode($labels) ?>,
    datasets: [{ data: <?= json_encode($data) ?> }]
  },
  options: { responsive:true }
});
</script>

<script src="/picklehub_project/admin/assets/js/admin.js?v=5" defer></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.1/dist/js/bootstrap.bundle.min.js" defer></script>

</body>
</html>
