<?php
require '../includes/db.php';
require '../includes/functions.php';
is_super_admin(); // ✅ Only Super Admins can access

// Filter logic
$search = trim($_GET['search'] ?? '');
$where = '';
if ($search !== '') {
    $where = "WHERE a.action LIKE :search OR u.name LIKE :search OR u.email LIKE :search OR a.ip_address LIKE :search";
}

$sql = "SELECT a.*, u.name, u.email, u.role 
        FROM admin_logs a
        LEFT JOIN users u ON a.admin_id = u.id
        $where
        ORDER BY a.created_at DESC";
$stmt = $pdo->prepare($sql);
if ($search !== '') $stmt->bindValue(':search', "%$search%");
$stmt->execute();
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// CSV export
if (isset($_GET['download'])) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename=admin_logs.csv');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Admin Name', 'Email', 'Role', 'Action', 'IP Address', 'Date']);
    foreach ($logs as $l) {
        fputcsv($out, [
            $l['name'] ?? 'Unknown',
            $l['email'] ?? '-',
            ucfirst($l['role'] ?? 'unknown'),
            $l['action'],
            $l['ip_address'],
            $l['created_at']
        ]);
    }
    fclose($out);
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Admin Logs - Avoji Admin</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.1/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="../public/assets/style.css" rel="stylesheet">
<style>
body {
  background: #f8fafc;
  font-family: 'Poppins', sans-serif;
}
.card {
  border-radius: 14px;
  box-shadow: 0 4px 15px rgba(0,0,0,0.1);
}
.table thead {
  background: #e8f5e9;
  font-weight: 600;
}
.badge-role {
  padding: 5px 10px;
  border-radius: 6px;
}
.badge-admin { background-color: #81c784; }
.badge-super_admin { background-color: #e57373; }
.search-bar input {
  max-width: 250px;
}
@media (max-width: 768px) {
  .search-bar input {
    max-width: 100%;
  }
  table { font-size: 0.85rem; }
}
</style>
</head>
<body>
<?php include 'header.php'; ?>

<div class="container my-4">
  <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
    <h2 class="fw-bold mb-2">🧾 Admin Activity Logs</h2>
    <div class="d-flex gap-2 search-bar">
      <form method="GET" class="d-flex gap-2 flex-wrap">
        <input type="text" name="search" class="form-control form-control-sm" value="<?= h($search) ?>" placeholder="Search actions, email, IP...">
        <button type="submit" class="btn btn-sm btn-success">Search</button>
        <a href="?download=1<?= $search ? '&search='.urlencode($search) : '' ?>" class="btn btn-sm btn-primary">Download CSV</a>
      </form>
    </div>
  </div>

  <div class="card p-3">
    <div class="table-responsive">
      <table class="table table-striped align-middle">
        <thead>
          <tr>
            <th>Admin</th>
            <th>Email</th>
            <th>Role</th>
            <th>Action</th>
            <th>IP Address</th>
            <th>Date</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach($logs as $l): ?>
          <tr>
            <td><?= h($l['name'] ?? 'Unknown') ?></td>
            <td><?= h($l['email'] ?? '-') ?></td>
            <td>
              <?php if(($l['role'] ?? '') === 'super_admin'): ?>
                <span class="badge badge-super_admin text-white">Super Admin</span>
              <?php elseif(($l['role'] ?? '') === 'admin'): ?>
                <span class="badge badge-admin text-white">Admin</span>
              <?php else: ?>
                <span class="badge bg-secondary">Unknown</span>
              <?php endif; ?>
            </td>
            <td><?= nl2br(h($l['action'])) ?></td>
            <td><?= h($l['ip_address']) ?></td>
            <td><small class="text-muted"><?= date('d M Y, h:i A', strtotime($l['created_at'])) ?></small></td>
          </tr>
          <?php endforeach; ?>

          <?php if (empty($logs)): ?>
          <tr><td colspan="6" class="text-center text-muted">No admin activity recorded yet.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
