<?php
// admin/header.php
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_path', '/');
    session_start();
}

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/db.php';

// ensure admin
is_admin();

// current user
$user = $_SESSION['user'] ?? ['name' => 'Admin', 'email' => '', 'role' => 'admin'];
$is_super = ($user['role'] ?? '') === 'super_admin';

function is_active($page) {
    return basename($_SERVER['PHP_SELF']) === $page ? "active" : "";
}

function initials($name){
    $parts = preg_split('/\s+/', trim($name));
    $init = strtoupper(($parts[0][0] ?? 'A'));
    if (isset($parts[1][0])) $init .= strtoupper($parts[1][0]);
    return $init;
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width,initial-scale=1" />
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.1/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">

<style>
:root{
  --brand-1:#198754;
  --brand-2:#20c997;
  --bg:#f7fbf7;
  --panel:#ffffff;
  --text:#212529;
  --muted:#6c757d;
}
body.dark-mode{
  --bg:#0f1720;
  --panel:#07111a;
  --text:#e6eef6;
  --muted:#9aa6b2;
}
html,body{background:var(--bg);color:var(--text);margin:0;height:100%;}
.admin-shell{display:flex;min-height:100vh;}

/* Sidebar */
.admin-sidebar{
  width:260px;background:var(--panel);border-right:1px solid rgba(0,0,0,0.06);
  transition:transform .22s ease,width .22s ease;z-index:1100;
}
.admin-sidebar.collapsed{width:72px;}
.admin-sidebar .brand{padding:16px;display:flex;gap:10px;align-items:center;border-bottom:1px solid rgba(0,0,0,0.04);}
.logo{width:40px;height:40px;border-radius:8px;background:linear-gradient(135deg,var(--brand-1),var(--brand-2));display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;}
.admin-nav{padding:10px;}
.admin-nav .nav-link{display:flex;gap:10px;align-items:center;padding:10px;border-radius:8px;color:var(--muted);text-decoration:none;}
.admin-nav .nav-link:hover{background:rgba(0,0,0,0.04);color:var(--text);}
.admin-nav .nav-link.active{background:linear-gradient(135deg,var(--brand-1),var(--brand-2));color:#fff;font-weight:600;}

/* mobile overlay */
#sidebarOverlay{position:fixed;inset:0;background:rgba(0,0,0,0.45);z-index:1080;display:none;}
#sidebarOverlay.show{display:block;}

/* Topbar */
.admin-topbar{height:64px;display:flex;align-items:center;justify-content:space-between;padding:0 14px;background:var(--panel);border-bottom:1px solid rgba(0,0,0,0.06);position:sticky;top:0;z-index:1050;}
.avatar-circle{width:36px;height:36px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;background:linear-gradient(135deg,var(--brand-1),var(--brand-2));color:#fff;font-weight:700;}
.admin-content{flex:1;padding:20px;}

/* quick action UI */
#quickBtn{position:fixed;bottom:20px;right:20px;z-index:2000;background:var(--brand-1);color:#fff;padding:12px 16px;border-radius:999px;display:flex;gap:8px;align-items:center;cursor:pointer;box-shadow:0 6px 20px rgba(0,0,0,0.18);font-weight:600;}
#quickPanel{position:fixed;right:20px;bottom:90px;width:300px;background:var(--panel);border-radius:12px;box-shadow:0 10px 35px rgba(0,0,0,0.12);z-index:2001;transform:translateY(12px);opacity:0;pointer-events:none;transition:all .28s ease;}
#quickPanel.show{transform:translateY(0);opacity:1;pointer-events:auto;}

/* theme picker */
#themePicker{position:fixed;bottom:140px;right:20px;width:44px;height:44px;border:none;border-radius:50%;cursor:pointer;box-shadow:0 6px 18px rgba(0,0,0,0.12);}

/* Mobile */
@media(max-width:991px){
  .admin-sidebar{position:fixed;left:-320px;top:0;height:100vh;box-shadow:0 18px 40px rgba(0,0,0,0.3);}
  .admin-sidebar.open{left:0;}
  body{padding-top:64px;}
}
</style>
</head>
<body>

<div id="sidebarOverlay"></div>

<div class="admin-shell">

  <!-- SIDEBAR -->
  <aside id="adminSidebar" class="admin-sidebar">
    <div class="brand">
      <div class="logo">A</div>
      <div>
        <div style="font-weight:700;">Avoji Admin</div>
        <div style="font-size:12px;color:var(--muted);">Control Panel</div>
      </div>
    </div>

    <nav class="admin-nav">
      <a class="nav-link <?= is_active('index.php') ?>" href="index.php"><i class="bi bi-speedometer2"></i> <span class="nav-text">Dashboard</span></a>
      <a class="nav-link <?= is_active('orders.php') ?>" href="orders.php"><i class="bi bi-basket"></i> <span class="nav-text">Orders</span></a>
      <a class="nav-link <?= is_active('products.php') ?>" href="products.php"><i class="bi bi-box-seam"></i> <span class="nav-text">Products</span></a>
      <a class="nav-link <?= is_active('customers.php') ?>" href="customers.php"><i class="bi bi-people"></i> <span class="nav-text">Customers</span></a>
      <a class="nav-link <?= is_active('categories.php') ?>" href="categories.php"><i class="bi bi-tags"></i> <span class="nav-text">Categories</span></a>
      <a class="nav-link <?= is_active('visits.php') ?>" href="visits.php"><i class="bi bi-graph-up"></i> <span class="nav-text">Visits</span></a>
      <a class="nav-link <?= is_active('revenue.php') ?>" href="revenue.php"><i class="bi bi-currency-rupee"></i> <span class="nav-text">Revenue</span></a>
      <a class="nav-link <?= is_active('contact_settings.php') ?>" href="contact_settings.php"><i class="bi bi-telephone"></i> <span class="nav-text">Contact</span></a>
      <a class="nav-link <?= is_active('settings.php') ?>" href="settings.php"><i class="bi bi-gear"></i> <span class="nav-text">Website Settings</span></a>
      <?php if ($is_super): ?>
      <a class="nav-link <?= is_active('manage_admins.php') ?>" href="manage_admins.php"><i class="bi bi-shield-lock"></i> <span class="nav-text">Manage Admins</span></a>
      <?php endif; ?>
    </nav>

    <div style="padding:12px;border-top:1px solid rgba(0,0,0,0.04);margin-top:10px;">
      <small style="color:var(--muted)">Quick links</small>
      <div class="d-grid gap-2 mt-2">
        <a href="products.php" class="btn btn-sm btn-outline-success">Add Product</a>
        <a href="orders.php" class="btn btn-sm btn-outline-primary">View Orders</a>
      </div>
    </div>
  </aside>

  <!-- MAIN -->
  <div class="flex-grow-1">

    <!-- TOPBAR -->
    <header class="admin-topbar">
      <div class="d-flex align-items-center gap-2">
        <button id="sidebarToggle" class="btn btn-light btn-sm"><i class="bi bi-list"></i></button>
        <button id="mobileOpen" class="btn btn-light btn-sm d-lg-none"><i class="bi bi-list"></i></button>

        <div class="d-flex align-items-center gap-2 ms-2">
          <div class="avatar-circle"><?= initials($user['name']) ?></div>
          <div class="d-none d-md-block">
            <div style="font-weight:600;"><?= htmlspecialchars($user['name']) ?></div>
            <div style="font-size:12px;color:var(--muted);"><?= htmlspecialchars($user['email']) ?></div>
          </div>
        </div>
      </div>

      <div class="d-flex align-items-center gap-2">

        <!-- Theme Toggle -->
        <button id="themeToggle" class="btn btn-outline-secondary btn-sm">
          <i id="themeIcon" class="bi bi-moon-stars"></i>
        </button>

        <!-- Profile dropdown -->
        <div class="dropdown">
          <a href="#" id="profileDropdown" class="d-flex align-items-center" data-bs-toggle="dropdown">
            <div class="avatar-circle me-2"><?= initials($user['name']) ?></div>
          </a>
          <ul class="dropdown-menu dropdown-menu-end">
            <li><a class="dropdown-item" href="profile.php"><i class="bi bi-person"></i> Profile</a></li>
            <?php if($is_super): ?>
            <li><a class="dropdown-item" href="manage_admins.php"><i class="bi bi-people"></i> Manage Admins</a></li>
            <?php endif; ?>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item text-danger" href="../logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a></li>
          </ul>
        </div>

      </div>
    </header>

    <!-- Quick button -->
    <button id="quickBtn"><i class="bi bi-lightning-charge-fill"></i> Quick Actions</button>

    <div id="quickPanel">
      <div class="qp-header">
        <span>⚡ Quick Actions</span>
        <button id="qpClose" class="btn btn-sm btn-light"><i class="bi bi-x-lg"></i></button>
      </div>
      <div class="qp-body">
        <a href="products.php" class="qp-item"><i class="bi bi-plus-circle-fill"></i> Add Product</a>
        <a href="orders.php" class="qp-item"><i class="bi bi-basket-fill"></i> View Orders</a>
        <a href="customers.php" class="qp-item"><i class="bi bi-people-fill"></i> Customers</a>
        <a href="revenue.php" class="qp-item"><i class="bi bi-currency-rupee"></i> Revenue</a>
        <a href="settings.php" class="qp-item"><i class="bi bi-gear-fill"></i> Website Settings</a>
      </div>
    </div>

    <input id="themePicker" type="color" />

<!-- JS FIX (sidebar responsiveness + overlay) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.1/dist/js/bootstrap.bundle.min.js"></script>
<script src="/admin/assets/js/admin.js?v=3"></script>

</body>
</html>
