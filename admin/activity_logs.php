<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require '../includes/db.php';
require '../includes/functions.php';
is_admin();

/* ---------------------------
   RESET ACTIVITY LOGS
---------------------------- */
$flash_success = '';
$flash_error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_logs'])) {

    $admin_password = $_POST['admin_password'] ?? '';
    $current_user   = $_SESSION['user'] ?? null;

    if (!$current_user || empty($current_user['id'])) {
        $flash_error = "Unable to verify admin session.";
    } else {
        // fetch password hash
        $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->execute([$current_user['id']]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row || !password_verify($admin_password, $row['password'])) {
            $flash_error = "Incorrect password. Reset aborted.";
        } else {
            try {
                $pdo->beginTransaction();

                $pdo->exec("DELETE FROM admin_activity_logs");

                $pdo->commit();

                // ✅ LOG THIS ACTION
                log_admin_activity(
                    'reset',
                    'activity_logs',
                    null,
                    'Admin activity logs reset'
                );

                $flash_success = "✅ Activity logs have been reset successfully.";
            } catch (Exception $e) {
                $pdo->rollBack();
                $flash_error = "Reset failed: " . $e->getMessage();
            }
        }
    }
}

/* ---------------------------
   FETCH LOGS
---------------------------- */
$logs = $pdo->query("
    SELECT l.*, u.name AS admin_name
    FROM admin_activity_logs l
    JOIN users u ON l.admin_id = u.id
    ORDER BY l.created_at DESC
    LIMIT 200
")->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Admin Activity Logs</title>
<meta name="viewport" content="width=device-width, initial-scale=1">

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.1/dist/css/bootstrap.min.css" rel="stylesheet">

<style>
/* Mobile-first enhancements */
.log-card {
  border-left: 4px solid #0d6efd;
  border-radius: 10px;
}
.log-meta {
  font-size: 0.8rem;
  color: #6c757d;
}
@media (max-width: 768px) {
  table { display: none; }
}
@media (min-width: 769px) {
  .log-cards { display: none; }
}
</style>
</head>

<body>
<?php include 'header.php'; ?>

<div class="container my-4">

  <div class="d-flex justify-content-between align-items-center flex-wrap mb-3">
    <h4 class="fw-bold mb-2 mb-md-0">🧠 Admin Activity Logs</h4>

    <button class="btn btn-sm btn-danger"
            data-bs-toggle="modal"
            data-bs-target="#resetLogsModal">
      🗑️ Reset Logs
    </button>
  </div>

  <!-- Flash Messages -->
  <?php if ($flash_success): ?>
    <div class="alert alert-success"><?= h($flash_success) ?></div>
  <?php endif; ?>
  <?php if ($flash_error): ?>
    <div class="alert alert-danger"><?= h($flash_error) ?></div>
  <?php endif; ?>

  <!-- DESKTOP TABLE -->
  <div class="table-responsive">
    <table class="table table-striped table-sm align-middle">
      <thead class="table-dark">
        <tr>
          <th>Admin</th>
          <th>Action</th>
          <th>Entity</th>
          <th>Description</th>
          <th>IP</th>
          <th>Date</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($logs as $l): ?>
        <tr>
          <td><?= h($l['admin_name']) ?></td>
          <td><?= h($l['action']) ?></td>
          <td><?= h($l['entity_type']) ?><?= $l['entity_id'] ? ' #'.$l['entity_id'] : '' ?></td>
          <td><?= h($l['description']) ?></td>
          <td><?= h($l['ip_address']) ?></td>
          <td><?= date('d M Y, h:i A', strtotime($l['created_at'])) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <!-- MOBILE CARDS -->
  <div class="log-cards">
    <?php foreach ($logs as $l): ?>
      <div class="card log-card mb-3 shadow-sm">
        <div class="card-body p-3">
          <div class="fw-semibold"><?= h($l['admin_name']) ?></div>
          <div class="text-primary small"><?= h($l['action']) ?></div>
          <div class="small mt-1">
            <?= h($l['entity_type']) ?><?= $l['entity_id'] ? ' #'.$l['entity_id'] : '' ?>
          </div>
          <div class="mt-1"><?= h($l['description']) ?></div>
          <div class="log-meta mt-2">
            <?= h($l['ip_address']) ?> • <?= date('d M Y, h:i A', strtotime($l['created_at'])) ?>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

</div>

<!-- RESET MODAL -->
<div class="modal fade" id="resetLogsModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <form method="POST" class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Reset Activity Logs</h5>
        <button class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <p class="text-danger small">
          This will permanently delete all admin activity logs.
        </p>
        <div class="mb-3">
          <label class="form-label">Admin Password</label>
          <input type="password" name="admin_password" required class="form-control">
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" name="reset_logs" class="btn btn-danger btn-sm">
          Confirm Reset
        </button>
      </div>
    </form>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.1/dist/js/bootstrap.bundle.min.js"></script>
<script src="/picklehub_project/admin/assets/js/admin.js" defer></script>
</body>
</html>
