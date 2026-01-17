<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/mail.php';

is_admin(); // ensures session started and admin

// fallback for some setups
if (!isset($pdo) && isset($conn)) $pdo = $conn;

// create logs table if not exists (best-effort)
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS order_status_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            order_id INT NOT NULL,
            status ENUM('pending','processing','shipped','completed','cancelled') NOT NULL,
            changed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
} catch (Exception $e) {
    // ignore - dev envs may not allow FK
}

$valid_statuses = ['pending','processing','shipped','completed','cancelled'];

/* -----------------------------
   Handle AJAX Status Update
   (POST ajax=1 & action=update_status)
   ----------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && ($_POST['ajax'] ?? '') === '1'
    && ($_POST['action'] ?? '') === 'update_status') {

    header('Content-Type: application/json; charset=utf-8');

    $order_id = intval($_POST['order_id'] ?? 0);
    $status   = $_POST['status'] ?? '';

    if (!$order_id || !in_array($status, $valid_statuses)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid parameters']);
        exit;
    }

    try {
        // get old status (for logging clarity)
        $oldStmt = $pdo->prepare("SELECT status FROM orders WHERE id = ?");
        $oldStmt->execute([$order_id]);
        $old_status = $oldStmt->fetchColumn();

        // update status
        $stmt = $pdo->prepare("UPDATE orders SET status = ? WHERE id = ?");
        $stmt->execute([$status, $order_id]);

        // status history
        $stmtLog = $pdo->prepare("
            INSERT INTO order_status_logs (order_id, status)
            VALUES (?, ?)
        ");
        $stmtLog->execute([$order_id, $status]);

        // ✅ LOG ADMIN ACTIVITY (NOW SAFE)
        log_admin_activity(
            'update_status',
            'order',
            $order_id,
            "Order status changed from {$old_status} to {$status}"
        );

        echo json_encode(['status' => 'success']);
    } catch (Exception $ex) {
        echo json_encode(['status' => 'error', 'message' => $ex->getMessage()]);
    }
    exit;
}



/* -----------------------------
   Handle Reset Orders (POST)
   - triggered by modal form with name=reset_orders
   - verifies admin password (current logged-in user)
   ----------------------------- */
/* -----------------------------
   Handle Reset Orders (POST)
----------------------------- */
$flash_success = '';
$flash_error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_orders'])) {

    $admin_password = $_POST['admin_password'] ?? '';
    $current_user = $_SESSION['user'] ?? null;

    if (!$current_user || !isset($current_user['id'])) {
        $flash_error = "Unable to verify admin user.";
    } else {

        $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->execute([$current_user['id']]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row || !password_verify($admin_password, $row['password'])) {
            $flash_error = "Password incorrect. Reset aborted.";
        } else {

            try {
                $pdo->beginTransaction();

                $pdo->exec("DELETE FROM order_items");
                $pdo->exec("DELETE FROM order_status_logs");
                $pdo->exec("DELETE FROM orders");

                $pdo->commit();

                $flash_success = "All orders have been reset successfully.";

                // ✅ admin activity log
                log_admin_activity(
                    'reset',
                    'orders',
                    null,
                    'All orders reset by admin'
                );

            } catch (Exception $ex) {
                $pdo->rollBack();
                $flash_error = "Reset failed: " . $ex->getMessage();
            }
        }
    }
} // ✅ THIS WAS MISSING — VERY IMPORTANT




/* -----------------------------
   CSV Download (GET ?download=1)
   Uses same filters and outputs CSV and exits.
   ----------------------------- */
if (isset($_GET['download']) && $_GET['download'] == '1') {
    // Build WHERE same as page below
    $filter = $_GET['filter'] ?? 'all';
    $status_filter = $_GET['status_filter'] ?? '';
    $search = $_GET['search'] ?? '';

    $where = [];
    if ($filter == 'today') $where[] = "DATE(created_at)=CURDATE()";
    elseif ($filter == 'week') $where[] = "WEEK(created_at)=WEEK(CURDATE())";
    elseif ($filter == 'month') $where[] = "MONTH(created_at)=MONTH(CURDATE())";

    if ($status_filter && in_array($status_filter, $valid_statuses)) $where[] = "status='".addslashes($status_filter)."'";
    $params = [];
    if ($search) {
        $where[] = "(id LIKE :search OR name LIKE :search OR email LIKE :search)";
        $params[':search'] = "%{$search}%";
    }
    $where_sql = $where ? "WHERE " . implode(" AND ", $where) : '';

    $sql = "SELECT * FROM orders $where_sql ORDER BY created_at DESC";
    $stmt = $pdo->prepare($sql);
    foreach ($params as $k => $v) $stmt->bindValue($k, $v);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // output CSV
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="orders_export_' . date('Ymd_His') . '.csv"');
    $out = fopen('php://output', 'w');
    // header row
    fputcsv($out, ['ID','Name','Email','Phone','Shipping Address','Total Amount','Status','Created At']);
    foreach ($rows as $r) {
        fputcsv($out, [
            $r['id'],
            $r['name'],
            $r['email'],
            $r['phone'],
            $r['shipping_address'],
            $r['total_amount'],
            $r['status'],
            $r['created_at']
        ]);
    }
    fclose($out);
    exit;
}

/* -----------------------------
   Regular page rendering & fetching orders
   ----------------------------- */
$filter = $_GET['filter'] ?? 'all';
$status_filter = $_GET['status_filter'] ?? '';
$search = $_GET['search'] ?? '';

$where = [];
$params = [];
if ($filter == 'today') $where[] = "DATE(created_at)=CURDATE()";
elseif ($filter == 'week') $where[] = "WEEK(created_at)=WEEK(CURDATE())";
elseif ($filter == 'month') $where[] = "MONTH(created_at)=MONTH(CURDATE())";

if ($status_filter && in_array($status_filter, $valid_statuses)) $where[] = "status='".addslashes($status_filter)."'";

if ($search) {
    $where[] = "(id LIKE :search OR name LIKE :search OR email LIKE :search)";
    $params[':search'] = "%{$search}%";
}

$where_sql = $where ? "WHERE " . implode(" AND ", $where) : '';

$sql = "SELECT * FROM orders $where_sql ORDER BY created_at DESC";
$stmt = $pdo->prepare($sql);
foreach ($params as $k => $v) $stmt->bindValue($k, $v);
$stmt->execute();
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

// fetch items per order
$order_items = [];
$stmt_items = $pdo->prepare("SELECT oi.*, p.name AS product_name FROM order_items oi LEFT JOIN products p ON oi.product_id = p.id WHERE oi.order_id = ?");
foreach ($orders as $o) {
    $stmt_items->execute([$o['id']]);
    $order_items[$o['id']] = $stmt_items->fetchAll(PDO::FETCH_ASSOC);
}

// summary
$total_orders = $pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn();
$status_counts = [];
foreach ($valid_statuses as $s) {
    $status_counts[$s] = $pdo->query("SELECT COUNT(*) FROM orders WHERE status='".addslashes($s)."'")->fetchColumn();
}
$new_today_count = $pdo->query("SELECT COUNT(*) FROM orders WHERE DATE(created_at)=CURDATE()")->fetchColumn();

function getStatusColor($status) {
    return match($status) {
        'pending' => '#ffc107',
        'processing' => '#17a2b8',
        'shipped' => '#6c757d',
        'completed' => '#28a745',
        'cancelled' => '#dc3545',
        default => '#6c757d'
    };
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Orders - Admin Panel</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.1/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="../public/assets/style.css" rel="stylesheet">
<style>
body { background-color: #f8f9fa; font-family: "Segoe UI", sans-serif; }
.table td, .table th { vertical-align: middle !important; }

/* New Orders Highlight */
.new-order { background-color: #e6ffe9 !important; animation: glowPulse 2s infinite alternate; }
@keyframes glowPulse { from { box-shadow: 0 0 5px rgba(40,167,69,0.3); } to { box-shadow: 0 0 15px rgba(40,167,69,0.6); } }
.badge-new { background: linear-gradient(135deg, #28a745, #20c997); color: white; font-size: 0.7rem; border-radius: 6px; padding: 2px 6px; margin-left: 4px; }

/* Fade Flash Animation (for new entries) */
.flash-new { animation: fadeFlash 1.2s ease-out; }
@keyframes fadeFlash {
  0% { background-color: #b6ffb8; }
  50% { background-color: #dcffdc; }
  100% { background-color: #e6ffe9; }
}

/* Buttons & Layout */
.btn-refresh { background-color: #20c997; color: white; border: none; }
.btn-refresh:hover { background-color: #17a2b8; }

/* Loader */
#loaderOverlay {
  position: fixed; inset: 0;
  background: rgba(255,255,255,0.8);
  z-index: 2000;
  display: none;
  align-items: center;
  justify-content: center;
}
.spinner-border { color: #28a745; width: 3rem; height: 3rem; }

/* Responsive improvements */
@media (max-width: 992px) {
  .table thead th { font-size: 0.85rem; }
  .table td, .table th { font-size: 0.82rem; }
  .btn-sm { font-size: 0.75rem; padding: .25rem .45rem; }
}

@media (max-width: 768px) {
  h2 { text-align: center; }
  #filterForm { flex-direction: column; align-items: stretch; }
  #filterForm > * { width: 100%; }
  .table-responsive { overflow-x: auto; -webkit-overflow-scrolling: touch; }
  .table td .btn { display: inline-block; margin-bottom: 4px; }
}
</style>
</head>
<body>
<?php include 'header.php'; ?>

<div class="container my-4">

  <div class="d-flex justify-content-between align-items-center flex-wrap mb-2">
    <h2 class="text-success fw-bold mb-2 mb-md-0">Orders Management</h2>
    <div class="d-flex align-items-center gap-2">
      <p class="mb-0 text-muted small">Last updated: <span id="lastUpdated"><?= date('H:i:s') ?></span></p>
      <button class="btn btn-sm btn-refresh d-flex align-items-center gap-1" id="refreshNowBtn">🔄 Refresh Now</button>
    </div>
  </div>

  <!-- flash messages -->
  <?php if (!empty($flash_success)): ?>
    <div class="alert alert-success"><?= h($flash_success) ?></div>
  <?php endif; ?>
  <?php if (!empty($flash_error)): ?>
    <div class="alert alert-danger"><?= h($flash_error) ?></div>
  <?php endif; ?>

  <!-- Summary -->
  <div class="row mb-4 g-3 text-center" id="summaryCards">
    <div class="col-6 col-md-2"><div class="card p-3 bg-primary text-white"><h6>Total Orders</h6><h4><?= $total_orders ?></h4></div></div>
    <div class="col-6 col-md-2"><div class="card p-3 bg-success text-white"><h6>New Orders Today</h6><h4><?= $new_today_count ?></h4></div></div>
    <?php foreach($status_counts as $status => $count): ?>
      <div class="col-6 col-md-2"><div class="card p-3 text-white" style="background-color: <?= getStatusColor($status) ?>"><h6><?= ucfirst($status) ?></h6><h4><?= $count ?></h4></div></div>
    <?php endforeach; ?>
  </div>

  <!-- Filters -->
  <form method="GET" class="d-flex align-items-center mb-3 gap-2 flex-wrap" id="filterForm">
    <input type="text" name="search" class="form-control form-control-sm" placeholder="Search by ID, Name, Email" value="<?= h($search) ?>">
    <select name="filter" class="form-select form-select-sm">
      <option value="all" <?= ($filter=='all')?'selected':'' ?>>All Dates</option>
      <option value="today" <?= ($filter=='today')?'selected':'' ?>>Today</option>
      <option value="week" <?= ($filter=='week')?'selected':'' ?>>This Week</option>
      <option value="month" <?= ($filter=='month')?'selected':'' ?>>This Month</option>
    </select>
    <select name="status_filter" class="form-select form-select-sm">
      <option value="">All Statuses</option>
      <?php foreach($valid_statuses as $s): ?>
        <option value="<?= $s ?>" <?= ($status_filter==$s)?'selected':'' ?>><?= ucfirst($s) ?></option>
      <?php endforeach; ?>
    </select>

    <button type="submit" class="btn btn-sm btn-primary">Filter</button>

    <!-- CSV download: include current filters in the query string -->
    <?php
      $qs = http_build_query(['download'=>1,'filter'=>$filter,'status_filter'=>$status_filter,'search'=>$search]);
    ?>
    <a href="?<?= $qs ?>" class="btn btn-sm btn-success">Download CSV</a>

    <!-- Reset Orders button (opens modal to ask admin password) -->
    <button type="button" class="btn btn-sm btn-danger ms-auto" data-bs-toggle="modal" data-bs-target="#resetModal">Reset Orders</button>
  </form>

  <!-- Orders Table -->
  <div class="table-responsive shadow-sm bg-white rounded-3 p-2">
    <table class="table table-striped align-middle mb-0">
      <thead class="table-success">
        <tr><th>ID</th><th>Customer</th><th>Phone</th><th>Address</th><th>Total</th><th>Status</th><th>Date</th><th>Actions</th></tr>
      </thead>
      <tbody id="ordersTable">
        <?php if ($orders): foreach ($orders as $o):
          $isToday = (date('Y-m-d', strtotime($o['created_at'])) == date('Y-m-d'));
        ?>
        <tr class="<?= $isToday ? 'new-order flash-new' : '' ?>">
          <td>
            <?= h($o['id']) ?>
            <?php if ($isToday): ?><span class="badge-new">🆕</span><?php endif; ?>
          </td>
          <td><?= h($o['name']) ?><br><small><?= h($o['email']) ?></small></td>
          <td><?= h($o['phone']) ?></td>
          <td><?= h($o['shipping_address']) ?></td>
          <td>₹<?= number_format($o['total_amount'], 2) ?></td>

          <td style="min-width:140px;">
            <select class="form-select form-select-sm status-select text-white" data-id="<?= $o['id'] ?>" style="background-color: <?= getStatusColor($o['status']) ?>;">
              <?php foreach ($valid_statuses as $s): ?>
                <option value="<?= $s ?>" <?= ($o['status']==$s)?'selected':'' ?>><?= ucfirst($s) ?></option>
              <?php endforeach; ?>
            </select>
          </td>

          <td><?= h(date('d M Y, h:i A', strtotime($o['created_at']))) ?></td>

          <td>
            <a href="../public/invoice.php?order_id=<?= h($o['id']) ?>" target="_blank" class="btn btn-sm btn-success mb-1">Invoice</a>
            <button class="btn btn-sm btn-info mb-1" data-bs-toggle="collapse" data-bs-target="#items<?= $o['id'] ?>">Items</button>

            <div class="collapse mt-2" id="items<?= $o['id'] ?>">
              <?php if (!empty($order_items[$o['id']])): ?>
              <table class="table table-bordered table-sm mb-0">
                <thead><tr><th>Product</th><th>Qty</th><th>Price</th><th>Subtotal</th></tr></thead>
                <tbody>
                <?php foreach($order_items[$o['id']] as $i): ?>
                  <tr>
                    <td><?= h($i['product_name']) ?></td>
                    <td><?= h($i['quantity']) ?></td>
                    <td>₹<?= number_format($i['price'], 2) ?></td>
                    <td>₹<?= number_format($i['quantity'] * $i['price'], 2) ?></td>
                  </tr>
                <?php endforeach; ?>
                </tbody>
              </table>
              <?php else: ?><p class="text-muted small mb-0">No items found.</p><?php endif; ?>
            </div>
          </td>
        </tr>
        <?php endforeach; else: ?>
        <tr><td colspan="8" class="text-center text-muted py-3">No orders found.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Reset Modal -->
<div class="modal fade" id="resetModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <form method="POST" class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Reset Orders (Admin Password Required)</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p class="small text-muted">This will permanently delete all orders and related order items & logs. Enter your admin password to proceed.</p>
        <div class="mb-3">
          <label class="form-label">Admin Password</label>
          <input type="password" name="admin_password" required class="form-control">
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" name="reset_orders" class="btn btn-danger btn-sm">Confirm Reset</button>
      </div>
    </form>
  </div>
</div>

<!-- Loader -->
<div id="loaderOverlay"><div class="spinner-border" role="status"></div></div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.1/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function(){
  const colors = {pending:'#ffc107',processing:'#17a2b8',shipped:'#6c757d',completed:'#28a745',cancelled:'#dc3545'};
  const loader = document.getElementById('loaderOverlay');
  const lastUpdated = document.getElementById('lastUpdated');
  const refreshBtn = document.getElementById('refreshNowBtn');
  const filterForm = document.getElementById('filterForm');

  function showLoader(show=true){
    loader.style.display = show ? 'flex' : 'none';
  }

  function attachStatusListeners(){
    document.querySelectorAll('.status-select').forEach(sel => {
      // clone to remove previous handlers (safe)
      const newSel = sel.cloneNode(true);
      sel.parentNode.replaceChild(newSel, sel);

      newSel.addEventListener('change', async function(){
        const orderId = this.dataset.id;
        const newStatus = this.value;
        this.style.backgroundColor = colors[newStatus] || '#6c757d';

        const fd = new FormData();
        fd.append('ajax','1');
        fd.append('action','update_status');
        fd.append('order_id', orderId);
        fd.append('status', newStatus);

        try {
          const res = await fetch(window.location.pathname + window.location.search, {
            method: 'POST',
            body: fd,
            credentials: 'same-origin'
          });
          const data = await res.json();
          if (data.status !== 'success') {
            alert('Failed to update status: ' + (data.message || 'unknown'));
            this.style.backgroundColor = '#6c757d';
          } else {
            // refresh summary counts
            refreshSummary();
          }
        } catch (err) {
          console.error(err);
          alert('Error updating status');
          this.style.backgroundColor = '#6c757d';
        }
      });
    });
  }

  async function refreshSummary(){
    try {
      const res = await fetch('get_order_summary.php', { credentials: 'same-origin' });
      if (!res.ok) return;
      const data = await res.json();
      if (data.status_counts) {
        const totalEl = document.querySelector('.bg-primary h4');
        const todayEl = document.querySelector('.bg-success h4');
        if (totalEl && 'total' in data) totalEl.textContent = data.total;
        if (todayEl && 'today' in data) todayEl.textContent = data.today;
      }
    } catch (err) {
      console.error('Summary refresh error', err);
    }
  }

  async function refreshOrders(){
    const tb = document.getElementById('ordersTable');
    const q = new URLSearchParams(new FormData(filterForm)).toString();
    try {
      const r = await fetch('orders_table.php?' + q, { credentials: 'same-origin' });
      if (!r.ok) throw new Error('Network error');
      const html = await r.text();
      tb.innerHTML = html;
      document.querySelectorAll('.new-order').forEach(row=>{
        row.classList.add('flash-new');
        setTimeout(()=>row.classList.remove('flash-new'),1500);
      });
      attachStatusListeners();
    } catch (err) {
      console.error('Orders refresh error', err);
      tb.innerHTML = '<tr><td colspan="8" class="text-center text-danger">Error loading orders.</td></tr>';
    }
  }

  // initial
  attachStatusListeners();

  refreshBtn.addEventListener('click', async function(){
    showLoader(true);
    await Promise.all([refreshSummary(), refreshOrders()]);
    showLoader(false);
    lastUpdated.textContent = new Date().toLocaleTimeString();
  });

  // auto refresh every 60s
  setInterval(async ()=> {
    await Promise.all([refreshSummary(), refreshOrders()]);
    lastUpdated.textContent = new Date().toLocaleTimeString();
  }, 60000);

  // keep filter submit as normal GET (URL updates). If you prefer AJAX only, you can prevent default and call refreshOrders()
  // document.getElementById('filterForm').addEventListener('submit', function(e){ e.preventDefault(); refreshOrders(); });

})();
</script>

<!-- admin app behaviours in external admin.js -->
<script src="/picklehub_project/admin/assets/js/admin.js" defer></script>
</body>
</html>
