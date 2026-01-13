<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require '../includes/db.php';
require '../includes/functions.php';
is_admin();

// ==========================
// RESET REVENUE DATA (Password Protected)
// ==========================
if (isset($_POST['confirm_reset']) && isset($_POST['admin_password'])) {
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

// ==========================
// FILTERS
// ==========================
$filter = $_GET['filter'] ?? 'all';
$statusFilter = $_GET['status'] ?? 'all';
$view_all = isset($_GET['view_all']);
$where = [];
$prevWhere = [];

if($filter=='today') { 
    $where[] = "DATE(created_at)=CURDATE()"; 
    $prevWhere[] = "DATE(created_at)=CURDATE()-INTERVAL 1 DAY";
}
elseif($filter=='week') { 
    $where[] = "WEEK(created_at)=WEEK(CURDATE())"; 
    $prevWhere[] = "WEEK(created_at)=WEEK(CURDATE())-1";
}
elseif($filter=='month') { 
    $where[] = "MONTH(created_at)=MONTH(CURDATE())"; 
    $prevWhere[] = "MONTH(created_at)=MONTH(CURDATE())-1";
}

// Status filter
if($statusFilter!='all') $where[] = "status='".$statusFilter."'";
$whereSQL = $where ? "WHERE ".implode(" AND ", $where) : "";
$prevWhereSQL = $prevWhere ? "WHERE ".implode(" AND ", $prevWhere) : "";

// ==========================
// FETCH REVENUE DATA
// ==========================
$limit_sql = $view_all ? "" : "LIMIT 20";
$revenue = $pdo->query("SELECT SUM(total_amount) as total, DATE(created_at) as date 
                        FROM orders $whereSQL 
                        GROUP BY DATE(created_at) 
                        ORDER BY DATE(created_at) DESC $limit_sql")->fetchAll();

$prevRevenue = $pdo->query("SELECT SUM(total_amount) as total FROM orders $prevWhereSQL")->fetchColumn();
$totalOrders = $pdo->query("SELECT COUNT(*) FROM orders $whereSQL")->fetchColumn();

$totalRevenue = array_sum(array_column($revenue,'total'));
$percentChange = $prevRevenue ? round((($totalRevenue - $prevRevenue)/$prevRevenue)*100,2) : 0;

// ==========================
// DOWNLOAD CSV
// ==========================
if(isset($_GET['download'])){
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename=revenue.csv');
    $output = fopen('php://output','w');
    fputcsv($output,['Date','Revenue']);
    foreach($revenue as $r) fputcsv($output,[$r['date'],$r['total']]);
    fclose($output);
    exit;
}

$labels = array_column($revenue,'date');
$data = array_column($revenue,'total');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Revenue Analytics - Admin</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.1/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
body {
  background: #ffffff;
  font-family: 'Poppins', sans-serif;
}
h2 { font-weight: 600; color: #222; }

.floating-summary {
  position:sticky; top:10px; z-index:10;
  background: #fff; border-radius:12px;
  padding:10px 15px;
  box-shadow:0 4px 20px rgba(0,0,0,0.08);
}

.card-summary {
  color:white; text-align:center; padding:18px;
  border-radius:18px; box-shadow:0 4px 10px rgba(0,0,0,0.1);
  transition: all 0.3s ease; font-weight:500;
}
.card-summary:hover { transform:translateY(-6px); }
.card-summary h4 { margin:0; font-size:1.6rem; font-weight:700; }
.card-summary h6 { margin:0; opacity:0.95; }

.card-revenue { background: linear-gradient(135deg, #28a745, #20c997); }
.card-orders  { background: linear-gradient(135deg, #007bff, #00b4d8); }
.card-growth  { background: linear-gradient(135deg, #ff8c00, #ffa500); }

.btn-gradient {
  background: linear-gradient(135deg, #0d6efd, #00bfff);
  border:none; color:white;
  font-weight:500; border-radius:8px;
  transition:0.3s;
}
.btn-gradient:hover { transform: scale(1.05); background: linear-gradient(135deg, #0078ff, #0099ff); }

.btn-reset-small {
  background: linear-gradient(135deg, #dc3545, #ff4d6d);
  color:white; border:none;
  font-size:0.8rem; border-radius:8px;
  padding:0.4rem 0.8rem; transition:0.3s;
}
.btn-reset-small:hover { background: linear-gradient(135deg, #c82333, #ff1e56); }

.card { border-radius: 12px; box-shadow: 0 3px 15px rgba(0,0,0,0.1); }
.table th { background: #f8f9fa; font-weight:600; }

/* Colored Filter Buttons */
.btn-filter {
  border:none; color:white; font-weight:500;
  border-radius:8px; transition:0.3s;
}
.btn-today    { background: linear-gradient(135deg, #16a34a, #22c55e); }
.btn-week     { background: linear-gradient(135deg, #3b82f6, #60a5fa); }
.btn-month    { background: linear-gradient(135deg, #f59e0b, #fbbf24); }
.btn-all      { background: linear-gradient(135deg, #6b7280, #9ca3af); }

.btn-filter:hover { opacity:0.85; transform:scale(1.05); }

@media (max-width:768px){
  .card-summary h4 { font-size:1.3rem; }
  .card-summary h6 { font-size:0.9rem; }
  .table { font-size:0.85rem; }
  h2 { font-size:1.3rem; }
}
</style>
</head>
<body>
<?php include 'header.php'; ?>

<div class="container my-4">

  <!-- Floating Summary -->
  <div class="floating-summary mb-4">
    <div class="row text-center g-3">
      <div class="col-6 col-md-3">
        <div class="card-summary card-revenue">
          <h6>Total Revenue</h6>
          <h4>₹<?= number_format($totalRevenue,2) ?></h4>
        </div>
      </div>
      <div class="col-6 col-md-3">
        <div class="card-summary card-orders">
          <h6>Total Orders</h6>
          <h4><?= $totalOrders ?></h4>
        </div>
      </div>
      <div class="col-6 col-md-3">
        <div class="card-summary card-growth">
          <h6>Growth</h6>
          <h4><?= $percentChange>=0?'+':'' ?><?= $percentChange ?>%</h4>
        </div>
      </div>
      <div class="col-6 col-md-3">
        <button type="button" class="btn btn-reset-small mt-2" data-bs-toggle="modal" data-bs-target="#confirmResetModal">
          Reset Revenue
        </button>
      </div>
    </div>
  </div>

  <h2 class="mb-3">Revenue Analytics</h2>

  <?php if ($msg = flash('success')): ?>
    <div class="alert alert-success"><?= h($msg) ?></div>
  <?php elseif ($msg = flash('error')): ?>
    <div class="alert alert-danger"><?= h($msg) ?></div>
  <?php endif; ?>

  <!-- Filters -->
  <div class="d-flex align-items-center mb-3 gap-2 flex-wrap">
    <a href="?filter=today&status=<?= $statusFilter ?>" class="btn btn-sm btn-filter btn-today <?= $filter=='today'?'shadow':'' ?>">Today</a>
    <a href="?filter=week&status=<?= $statusFilter ?>" class="btn btn-sm btn-filter btn-week <?= $filter=='week'?'shadow':'' ?>">This Week</a>
    <a href="?filter=month&status=<?= $statusFilter ?>" class="btn btn-sm btn-filter btn-month <?= $filter=='month'?'shadow':'' ?>">This Month</a>
    <a href="?filter=all&status=<?= $statusFilter ?>" class="btn btn-sm btn-filter btn-all <?= $filter=='all'?'shadow':'' ?>">All</a>

    <form method="GET" class="d-flex align-items-center gap-2 flex-wrap ms-auto">
      <input type="hidden" name="filter" value="<?= $filter ?>">
      <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
        <option value="all" <?= $statusFilter=='all'?'selected':'' ?>>All</option>
        <option value="pending" <?= $statusFilter=='pending'?'selected':'' ?>>Pending</option>
        <option value="processing" <?= $statusFilter=='processing'?'selected':'' ?>>Processing</option>
        <option value="shipped" <?= $statusFilter=='shipped'?'selected':'' ?>>Shipped</option>
        <option value="completed" <?= $statusFilter=='completed'?'selected':'' ?>>Completed</option>
        <option value="cancelled" <?= $statusFilter=='cancelled'?'selected':'' ?>>Cancelled</option>
      </select>
    </form>

    <a href="?download=1&filter=<?= $filter ?>&status=<?= $statusFilter ?>" class="btn btn-sm btn-gradient shadow-sm">
      ⬇️ Download CSV
    </a>
  </div>

  <!-- Revenue Table and Chart -->
  <div class="row">
    <div class="col-md-6 mb-4">
      <div class="card shadow-sm">
        <div class="card-header bg-light"><strong>Revenue Table</strong></div>
        <div class="card-body">
          <div class="table-responsive">
            <table class="table table-bordered align-middle">
              <thead>
                <tr><th>Date</th><th>Revenue (₹)</th></tr>
              </thead>
              <tbody>
                <?php foreach($revenue as $r): ?>
                <tr>
                  <td><?= h($r['date']) ?></td>
                  <td>₹<?= number_format($r['total'],2) ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if(empty($revenue)): ?>
                <tr><td colspan="2" class="text-center">No revenue data available.</td></tr>
                <?php endif; ?>
              </tbody>
              <tfoot>
                <tr><th>Total Revenue</th><th>₹<?= number_format($totalRevenue,2) ?></th></tr>
              </tfoot>
            </table>
          </div>

          <!-- View More / Show Less -->
          <?php if (!$view_all && count($revenue) >= 20): ?>
          <div class="text-center mt-3">
            <a href="?<?= http_build_query(array_merge($_GET, ['view_all' => 1])) ?>" class="btn btn-outline-primary btn-sm">View More</a>
          </div>
          <?php elseif ($view_all && count($revenue) > 20): ?>
          <div class="text-center mt-3">
            <a href="revenue.php" class="btn btn-outline-secondary btn-sm">Show Less</a>
          </div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <div class="col-md-6 mb-4">
      <div class="card shadow-sm">
        <div class="card-header bg-light"><strong>Revenue Chart</strong></div>
        <div class="card-body">
          <canvas id="revenueChart" height="300"></canvas>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- 🔐 Confirm Reset Modal -->
<div class="modal fade" id="confirmResetModal" tabindex="-1">
  <div class="modal-dialog">
    <form method="POST" class="modal-content">
      <div class="modal-header bg-danger text-white">
        <h5 class="modal-title">Confirm Reset Revenue</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <p>This will permanently delete <strong>all orders and revenue data</strong>.<br>
        Please enter your admin password to confirm.</p>
        <input type="password" name="admin_password" class="form-control" placeholder="Enter admin password" required>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" name="confirm_reset" class="btn btn-sm btn-danger">Confirm Reset</button>
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
    datasets: [{
      label: 'Revenue',
      data: <?= json_encode($data) ?>,
      backgroundColor: 'rgba(25, 135, 84, 0.8)',
      borderRadius: 6
    }]
  },
  options: {
    responsive:true,
    plugins:{ legend:{ display:false } },
    scales:{ y:{ beginAtZero:true, grid:{ color:'#eee' } }, x:{ grid:{ display:false } } }
  }
});
</script>

</main>
</div>
</div>

<script>
/* ================= DARK MODE ================= */
(function(){
    const body = document.body;
    const toggle = document.getElementById("themeToggle");
    const icon = document.getElementById("themeIcon");

    let dark = document.cookie.includes("admin_dark=1");

    if(!document.cookie.includes("admin_dark=")){
        dark = window.matchMedia("(prefers-color-scheme: dark)").matches;
    }

    applyTheme(dark);

    function applyTheme(d){
        body.classList.toggle("dark-mode", d);
        icon.classList.replace(d ? "bi-moon-stars" : "bi-sun-fill", d ? "bi-sun-fill" : "bi-moon-stars");
    }

    toggle.addEventListener("click", ()=>{
        dark = !dark;
        applyTheme(dark);
        document.cookie = "admin_dark="+(dark?1:0)+"; path=/; max-age=31536000";
    });
})();

/* ================= SIDEBAR ================= */
(function(){
    const sidebar = document.getElementById("adminSidebar");
    const overlay = document.getElementById("sidebarOverlay");
    const toggle = document.getElementById("sidebarToggle");
    const mobileOpen = document.getElementById("mobileOpen");

    toggle.addEventListener("click", ()=> sidebar.classList.toggle("collapsed"));
    mobileOpen.addEventListener("click", ()=>{
        sidebar.classList.add("open");
        overlay.classList.add("show");
    });

    overlay.addEventListener("click", ()=>{
        sidebar.classList.remove("open");
        overlay.classList.remove("show");
    });

    document.addEventListener("click",(e)=>{
        if(window.innerWidth <= 991 &&
           !sidebar.contains(e.target) &&
           !mobileOpen.contains(e.target)){
            sidebar.classList.remove("open");
            overlay.classList.remove("show");
        }
    });
})();

/* ================= SWIPE TO OPEN ================= */
(function(){
    let startX = 0;
    window.addEventListener("touchstart",(e)=> startX = e.touches[0].clientX);
    window.addEventListener("touchend",(e)=>{
        if(startX < 40 && e.changedTouches[0].clientX > 120){
            document.getElementById("adminSidebar").classList.add("open");
            document.getElementById("sidebarOverlay").classList.add("show");
        }
    });
})();

/* ================= QUICK ACTION BUTTON ================= */
(function(){
    const btn = document.createElement("div");
    btn.id = "quickBtn";
    btn.innerHTML = '<i class="bi bi-lightning-charge-fill"></i> Quick Actions';
    document.body.appendChild(btn);
    btn.onclick = ()=> alert("Add custom actions here!");
})();

/* ================= THEME COLOR PICKER ================= */
(function(){
    const pick = document.createElement("input");
    pick.type="color";
    pick.id="themePicker";
    pick.value="#198754";
    document.body.appendChild(pick);

    pick.addEventListener("input",(e)=>{
        document.documentElement.style.setProperty("--brand-1",e.target.value);
        document.documentElement.style.setProperty("--brand-2",e.target.value);
    });
})();
</script>

<!-- Admin JavaScript -->
<script src="/picklehub_project/admin/assets/js/admin.js" defer></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.1/dist/js/bootstrap.bundle.min.js" defer></script>
</body>
</html>


<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

