<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require '../includes/db.php';
require '../includes/functions.php';
is_admin();

// ==========================
// RESET VISITS (Password Protected)
// ==========================
if (isset($_POST['confirm_reset']) && isset($_POST['admin_password'])) {
    $password = trim($_POST['admin_password']);
    $admin_id = $_SESSION['user']['id'] ?? 0;

    $stmt = $pdo->prepare("SELECT password FROM users WHERE id=? LIMIT 1");
    $stmt->execute([$admin_id]);
    $hash = $stmt->fetchColumn();

    if ($hash && password_verify($password, $hash)) {
        $pdo->exec("TRUNCATE TABLE visits");
        flash('success', "✅ All visit data has been reset successfully.");
    } else {
        flash('error', "❌ Invalid admin password. Reset aborted.");
    }

    header("Location: visit.php");
    exit;
}

// ==========================
// FILTERS & SEARCH
// ==========================
$filter = $_GET['filter'] ?? 'all';
$device_filter = $_GET['device_filter'] ?? '';
$search = $_GET['search'] ?? '';
$view_all = isset($_GET['view_all']); 

$where = [];
if ($filter == 'today') $where[] = "DATE(visited_at)=CURDATE()";
elseif ($filter == 'week') $where[] = "WEEK(visited_at)=WEEK(CURDATE())";
elseif ($filter == 'month') $where[] = "MONTH(visited_at)=MONTH(CURDATE())";

if ($device_filter) $where[] = "device_type='$device_filter'";
if ($search) $where[] = "(ip_address LIKE '%$search%' OR user_agent LIKE '%$search%')";

$where_sql = $where ? "WHERE " . implode(" AND ", $where) : '';

// Limit top 20 unless “view_all”
$limit_sql = $view_all ? "" : "LIMIT 20";
$visits = $pdo->query("SELECT * FROM visits $where_sql ORDER BY visited_at DESC $limit_sql")->fetchAll();

// Summary counts
$total_visits = $pdo->query("SELECT COUNT(*) FROM visits")->fetchColumn();
$new_visits = $pdo->query("SELECT COUNT(*) FROM visits WHERE is_new=1")->fetchColumn();
$returning_visits = $pdo->query("SELECT COUNT(*) FROM visits WHERE is_new=0")->fetchColumn();
$desktop_visits = $pdo->query("SELECT COUNT(*) FROM visits WHERE device_type='desktop'")->fetchColumn();
$mobile_visits = $pdo->query("SELECT COUNT(*) FROM visits WHERE device_type='mobile'")->fetchColumn();
$tablet_visits = $pdo->query("SELECT COUNT(*) FROM visits WHERE device_type='tablet'")->fetchColumn();
$new_today_count = $pdo->query("SELECT COUNT(*) FROM visits WHERE DATE(visited_at)=CURDATE()")->fetchColumn();

// Daily visit chart
$daily_visits = $pdo->query("SELECT DATE(visited_at) as day, COUNT(*) as count FROM visits GROUP BY day ORDER BY day ASC")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Website Visits - Admin</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.1/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
body {
  background: #f8fafc;
  font-family: 'Poppins', sans-serif;
}
h2 {
  font-weight: 600;
  color: #333;
}
.card-summary { 
  color:white; 
  text-align:center; 
  padding:20px; 
  border-radius:18px; 
  box-shadow:0 6px 20px rgba(0,0,0,0.15);
  transition: all 0.3s ease;
  backdrop-filter: blur(10px);
  border: 1px solid rgba(255,255,255,0.2);
}
.card-summary:hover {
  transform:translateY(-6px);
  box-shadow:0 8px 25px rgba(0,0,0,0.2);
}
.card-summary h4 { margin:0; font-size:1.8rem; font-weight:700; }
.card-summary h6 { margin:0; font-weight:500; opacity:0.9; }

.card-total    { background: linear-gradient(135deg, #007bff, #00a2ff); }
.card-new      { background: linear-gradient(135deg, #00c851, #10b981); }
.card-return   { background: linear-gradient(135deg, #ffbb33, #ff8800); }
.card-desktop  { background: linear-gradient(135deg, #33b5e5, #0099cc); }
.card-mobile   { background: linear-gradient(135deg, #6f42c1, #8e44ad); }
.card-tablet   { background: linear-gradient(135deg, #ff7043, #ff9800); }

.table thead th {
  color: #000 !important;
  background-color: #f8f9fa !important;
  font-weight: 600;
  border-bottom: 2px solid #dee2e6;
}
.table tbody tr:hover {
  background-color: rgba(0,0,0,0.04);
  transition: 0.2s;
}
.btn-reset-small {
  padding: 0.4rem 0.8rem;
  font-size: 0.8rem;
  border-radius: 8px;
  box-shadow: 0 3px 6px rgba(220,53,69,0.2);
}
.btn-reset-small:hover {
  transform: scale(1.05);
  box-shadow: 0 4px 10px rgba(220,53,69,0.3);
}
.card {
  border-radius: 14px;
  box-shadow: 0 4px 15px rgba(0,0,0,0.1);
}
.card h5 {
  font-weight: 600;
}
@media (max-width: 768px) {
  .card-summary { padding: 15px; }
  .card-summary h4 { font-size: 1.4rem; }
  .card-summary h6 { font-size: 0.9rem; }
  h2 { font-size: 1.4rem; }
  .table { font-size: 0.85rem; }
  .btn-reset-small { width: 100%; }
}
</style>
</head>
<body>
<?php include 'header.php'; ?>

<div class="container my-4">
  <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap">
    <h2>Website Visits Overview</h2>
  </div>

  <?php if ($msg = flash('success')): ?>
      <div class="alert alert-success shadow-sm"><?= h($msg) ?></div>
  <?php elseif ($msg = flash('error')): ?>
      <div class="alert alert-danger shadow-sm"><?= h($msg) ?></div>
  <?php endif; ?>

  <!-- Summary Cards -->
  <div class="row g-3 mb-4">
    <div class="col-6 col-md-2"><div class="card-summary card-total"><h6>Total Visits</h6><h4><?= $total_visits ?></h4></div></div>
    <div class="col-6 col-md-2"><div class="card-summary card-new"><h6>New Visits</h6><h4><?= $new_visits ?></h4></div></div>
    <div class="col-6 col-md-2"><div class="card-summary card-return"><h6>Returning</h6><h4><?= $returning_visits ?></h4></div></div>
    <div class="col-6 col-md-2"><div class="card-summary card-desktop"><h6>Desktop</h6><h4><?= $desktop_visits ?></h4></div></div>
    <div class="col-6 col-md-2"><div class="card-summary card-mobile"><h6>Mobile</h6><h4><?= $mobile_visits ?></h4></div></div>
    <div class="col-6 col-md-2"><div class="card-summary card-tablet"><h6>Tablet</h6><h4><?= $tablet_visits ?></h4></div></div>
  </div>

  <!-- Reset Button Below Cards -->
  <div class="text-end mb-3">
      <button type="button" class="btn btn-sm btn-danger btn-reset-small" data-bs-toggle="modal" data-bs-target="#confirmResetModal">
          Reset Visits
      </button>
  </div>

  <!-- Analytics Charts -->
  <div class="row my-4">
    <div class="col-md-6 mb-3">
      <div class="card p-3">
        <h5 class="text-center mb-3">Visits by Device</h5>
        <canvas id="deviceChart"></canvas>
      </div>
    </div>
    <div class="col-md-6 mb-3">
      <div class="card p-3">
        <h5 class="text-center mb-3">Daily Visits Trend</h5>
        <canvas id="dailyChart"></canvas>
      </div>
    </div>
  </div>

  <!-- Filters -->
  <div class="d-flex align-items-center mb-3 gap-2 flex-wrap">
    <form class="d-flex gap-2 flex-wrap" method="GET">
      <input type="text" name="search" class="form-control form-control-sm" placeholder="Search IP/User Agent" value="<?=h($search)?>">
      <select name="filter" class="form-select form-select-sm">
        <option value="all" <?= ($filter=='all')?'selected':'' ?>>All Dates</option>
        <option value="today" <?= ($filter=='today')?'selected':'' ?>>Today</option>
        <option value="week" <?= ($filter=='week')?'selected':'' ?>>This Week</option>
        <option value="month" <?= ($filter=='month')?'selected':'' ?>>This Month</option>
      </select>
      <select name="device_filter" class="form-select form-select-sm">
        <option value="">All Devices</option>
        <option value="desktop" <?= ($device_filter=='desktop')?'selected':'' ?>>Desktop</option>
        <option value="mobile" <?= ($device_filter=='mobile')?'selected':'' ?>>Mobile</option>
        <option value="tablet" <?= ($device_filter=='tablet')?'selected':'' ?>>Tablet</option>
      </select>
      <button type="submit" class="btn btn-sm btn-primary shadow-sm">Filter</button>
    </form>

    <a href="?download=1&filter=<?= $filter ?>&device_filter=<?= $device_filter ?>&search=<?= urlencode($search) ?>" 
       class="btn btn-sm btn-success ms-auto shadow-sm">Download Report</a>
  </div>

  <!-- Visits Table -->
  <div class="card shadow-sm">
    <div class="card-body">
      <h5 class="mb-3">Recent Visits</h5>
      <div class="table-responsive">
        <table class="table table-striped align-middle">
          <thead>
            <tr>
              <th>ID</th><th>IP Address</th><th>User Agent</th><th>Device Type</th><th>New/Returning</th><th>Date</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($visits as $v): ?>
            <tr>
              <td><?= $v['id'] ?></td>
              <td><?= htmlspecialchars($v['ip_address']) ?></td>
              <td class="text-break"><?= htmlspecialchars($v['user_agent']) ?></td>
              <td><?= ucfirst($v['device_type']) ?></td>
              <td><?= $v['is_new'] ? 'New' : 'Returning' ?></td>
              <td><?= $v['visited_at'] ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($visits)): ?>
            <tr><td colspan="6" class="text-center">No visit data available.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

      <!-- View More / Show Less -->
      <?php if (!$view_all && $total_visits > 20): ?>
      <div class="text-center mt-3">
        <a href="?<?= http_build_query(array_merge($_GET, ['view_all' => 1])) ?>" class="btn btn-outline-primary btn-sm">
          View More (<?= $total_visits - 20 ?> more)
        </a>
      </div>
      <?php elseif ($view_all && $total_visits > 20): ?>
      <div class="text-center mt-3">
        <a href="visit.php" class="btn btn-outline-secondary btn-sm">Show Less</a>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- 🔐 Confirm Reset Modal -->
<div class="modal fade" id="confirmResetModal" tabindex="-1">
  <div class="modal-dialog">
    <form method="POST" class="modal-content">
      <div class="modal-header bg-danger text-white">
        <h5 class="modal-title">Confirm Reset Visits</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <p>This will permanently delete <strong>all visit records</strong>.<br>
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
// Device Chart
new Chart(document.getElementById('deviceChart'), {
  type: 'doughnut',
  data: {
    labels: ['Desktop', 'Mobile', 'Tablet'],
    datasets: [{
      data: [<?= $desktop_visits ?>, <?= $mobile_visits ?>, <?= $tablet_visits ?>],
      backgroundColor: ['#0d6efd','#6f42c1','#fd7e14'],
      borderWidth: 0
    }]
  },
  options: { responsive:true, plugins:{ legend:{ position:'bottom' } } }
});

// Daily Chart
new Chart(document.getElementById('dailyChart'), {
  type: 'line',
  data: {
    labels: <?= json_encode(array_column($daily_visits, 'day')) ?>,
    datasets: [{
      label: 'Visits',
      data: <?= json_encode(array_column($daily_visits, 'count')) ?>,
      borderColor: '#198754',
      backgroundColor: 'rgba(25,135,84,0.15)',
      fill: true,
      tension: 0.35,
      borderWidth: 2
    }]
  },
  options: { responsive: true, plugins: { legend: { display: false } } }
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

