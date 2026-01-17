<?php
// admin/header.php
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_path', '/');
    ini_set('session.cookie_httponly', 1);
    session_start();
}

header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/db.php';

is_admin();

$user = $_SESSION['user'] ?? ['name' => 'Admin', 'email' => '', 'role' => 'admin'];
$is_super = ($user['role'] ?? '') === 'super_admin';

function is_active($page) {
    return basename($_SERVER['PHP_SELF']) === $page ? "active" : "";
}

function initials($name){
    $parts = preg_split('/\s+/', trim($name));
    $init = strtoupper($parts[0][0] ?? 'A');
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

html,body{
  background:var(--bg);
  color:var(--text);
  margin:0;
  height:100%;
}

.admin-shell{display:flex;min-height:100vh}

/* SIDEBAR */
.admin-sidebar{
  width:260px;
  background:var(--panel);
  border-right:1px solid rgba(0,0,0,.06);
  transition:transform .25s ease,width .25s ease;
  z-index:1100;
}
.admin-sidebar.collapsed{width:72px}

.admin-sidebar .brand{
  padding:16px;
  display:flex;
  gap:10px;
  align-items:center;
  border-bottom:1px solid rgba(0,0,0,.05);
}

.logo{
  width:40px;height:40px;border-radius:10px;
  background:linear-gradient(135deg,var(--brand-1),var(--brand-2));
  display:flex;align-items:center;justify-content:center;
  color:#fff;font-weight:700;
}

.admin-nav{padding:12px}
.admin-nav .nav-link{
  display:flex;gap:10px;align-items:center;
  padding:10px;border-radius:10px;
  color:var(--muted);text-decoration:none;font-weight:500;
}
.admin-nav .nav-link:hover{background:rgba(0,0,0,.05);color:var(--text)}
.admin-nav .nav-link.active{
  background:linear-gradient(135deg,var(--brand-1),var(--brand-2));
  color:#fff;font-weight:600;
}

/* TOPBAR */
.admin-topbar{
  height:64px;display:flex;align-items:center;justify-content:space-between;
  padding:0 14px;background:var(--panel);
  border-bottom:1px solid rgba(0,0,0,.06);
  position:sticky;top:0;z-index:1050;
}

.avatar-circle{
  width:36px;height:36px;border-radius:50%;
  display:inline-flex;align-items:center;justify-content:center;
  background:linear-gradient(135deg,var(--brand-1),var(--brand-2));
  color:#fff;font-weight:700;
}

#sidebarOverlay{
  position:fixed;inset:0;background:rgba(0,0,0,.45);
  z-index:1080;display:none;
}
#sidebarOverlay.show{display:block}

@media(max-width:991px){
  .admin-sidebar{position:fixed;left:-280px;top:0;height:100vh}
  .admin-sidebar.open{left:0}
  body{padding-top:64px}
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
    <a class="nav-link <?=is_active('index.php')?>" href="index.php"><i class="bi bi-speedometer2"></i> Dashboard</a>
    <a class="nav-link <?=is_active('orders.php')?>" href="orders.php"><i class="bi bi-basket"></i> Orders</a>
    <a class="nav-link <?=is_active('products.php')?>" href="products.php"><i class="bi bi-box-seam"></i> Products</a>
    <a class="nav-link <?=is_active('customers.php')?>" href="customers.php"><i class="bi bi-people"></i> Customers</a>
    <a class="nav-link <?=is_active('categories.php')?>" href="categories.php"><i class="bi bi-tags"></i> Categories</a>
    <a class="nav-link <?=is_active('visits.php')?>" href="visits.php"><i class="bi bi-graph-up"></i> Visits</a>
    <a class="nav-link <?=is_active('revenue.php')?>" href="revenue.php"><i class="bi bi-currency-rupee"></i> Revenue</a>
    <a class="nav-link <?=is_active('activity_logs.php')?>" href="activity_logs.php"><i class="bi bi-clipboard-data"></i> Activity Logs</a>

    <!-- ✅ NEW CONTACT SETTINGS -->
    <a class="nav-link <?=is_active('contact_settings.php')?>" href="contact_settings.php">
      <i class="bi bi-telephone-fill"></i> Contact Settings
    </a>

    <a class="nav-link <?=is_active('settings.php')?>" href="settings.php"><i class="bi bi-gear"></i> Settings</a>

    <?php if($is_super): ?>
      <a class="nav-link <?=is_active('manage_admins.php')?>" href="manage_admins.php">
        <i class="bi bi-shield-lock"></i> Manage Admins
      </a>
    <?php endif; ?>
  </nav>
</aside>

<!-- MAIN -->
<div class="flex-grow-1">

<header class="admin-topbar">
  <div class="d-flex align-items-center gap-2">
    <button id="sidebarToggle" class="btn btn-light btn-sm d-none d-lg-inline">
      <i class="bi bi-list"></i>
    </button>
    <button id="mobileOpen" class="btn btn-light btn-sm d-lg-none">
      <i class="bi bi-list"></i>
    </button>

    <div class="d-flex align-items-center gap-2 ms-2">
      <div class="avatar-circle"><?=initials($user['name'])?></div>
      <div class="d-none d-md-block">
        <div class="fw-semibold"><?=htmlspecialchars($user['name'])?></div>
        <div style="font-size:12px;color:var(--muted);"><?=htmlspecialchars($user['email'])?></div>
      </div>
    </div>
  </div>

  <div class="d-flex align-items-center gap-2">
    <button id="themeToggle" class="btn btn-outline-secondary btn-sm">
      <i id="themeIcon" class="bi bi-moon-stars"></i>
    </button>

    <div class="dropdown">
      <a href="#" data-bs-toggle="dropdown">
        <div class="avatar-circle"><?=initials($user['name'])?></div>
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

<script src="/admin/assets/js/admin.js" defer></script>

<script>
(function(){
  const sidebar=document.getElementById("adminSidebar");
  const overlay=document.getElementById("sidebarOverlay");
  document.getElementById("mobileOpen")?.addEventListener("click",()=>{
    sidebar.classList.add("open");overlay.classList.add("show");
  });
  document.getElementById("sidebarToggle")?.addEventListener("click",()=>{
    sidebar.classList.toggle("collapsed");
  });
  overlay.addEventListener("click",()=>{
    sidebar.classList.remove("open");overlay.classList.remove("show");
  });
})();
</script>

</body>
</html>
