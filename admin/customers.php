<?php
require '../includes/db.php';
require '../includes/functions.php';
is_admin();

/* ---------- FILTERS ---------- */
$search = trim($_GET['search'] ?? '');
$limit  = intval($_GET['limit'] ?? 10);
$page   = max(1, intval($_GET['page'] ?? 1));

$allowed_limits = [10, 20, 50];
if (!in_array($limit, $allowed_limits)) $limit = 10;

$where_sql = "WHERE role='customer' ";
$params = [];

if ($search) {
    $where_sql .= "AND (name LIKE :s OR email LIKE :s) ";
    $params[':s'] = "%$search%";
}

/* ---------- TOTAL COUNT ---------- */
$stmt = $pdo->prepare("SELECT COUNT(*) FROM users $where_sql");
if ($search) $stmt->bindValue(':s', "%$search%");
$stmt->execute();
$total = $stmt->fetchColumn();

$offset = ($page - 1) * $limit;

/* ---------- FETCH USERS ---------- */
$sql = "SELECT * FROM users $where_sql ORDER BY created_at DESC LIMIT :lim OFFSET :offs";
$stmt = $pdo->prepare($sql);

if ($search) $stmt->bindValue(':s', "%$search%");
$stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offs', $offset, PDO::PARAM_INT);
$stmt->execute();

$users = $stmt->fetchAll();
$total_pages = ceil($total / $limit);

/* ---------- EXPORT CSV ---------- */
if (isset($_GET['export']) && $_GET['export'] === "csv") {
    header("Content-Type: text/csv");
    header("Content-Disposition: attachment; filename=customers.csv");

    $out = fopen("php://output", "w");
    fputcsv($out, ["ID", "Name", "Email", "Joined On"]);
    foreach ($users as $u) fputcsv($out, [$u['id'], $u['name'], $u['email'], $u['created_at']]);
    exit;
}

/* ---------- EXPORT XLSX (Fake Excel = CSV) ---------- */
if (isset($_GET['export']) && $_GET['export'] === "xlsx") {
    header("Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet");
    header("Content-Disposition: attachment; filename=customers.xlsx");

    $out = fopen("php://output", "w");
    fputcsv($out, ["ID", "Name", "Email", "Joined On"]);
    foreach ($users as $u) fputcsv($out, [$u['id'], $u['name'], $u['email'], $u['created_at']]);
    exit;
}

/* ---------- EXPORT PDF (Browser Print) ---------- */
if (isset($_GET['export']) && $_GET['export'] === "pdf") {
    echo "<h2>Customers PDF Export</h2>";
    echo "<table border='1' cellspacing='0' cellpadding='8'>";
    echo "<tr><th>ID</th><th>Name</th><th>Email</th><th>Joined</th></tr>";
    foreach ($users as $u)
        echo "<tr><td>{$u['id']}</td><td>{$u['name']}</td><td>{$u['email']}</td><td>{$u['created_at']}</td></tr>";
    echo "</table>";
    echo "<script>window.print();</script>";
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Customers - Admin</title>
<meta name="viewport" content="width=device-width,initial-scale=1">

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.1/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="../public/assets/style.css" rel="stylesheet">

<style>
/* Customer Card (Mobile) */
.card-customer {
    border-radius: 12px;
    background: #fff;
    padding: 15px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.08);
    transition: 0.2s ease;
}
.card-customer:hover { transform: translateY(-3px); }

.customer-email { font-size: 0.9rem; color: #444; }
.customer-joined { font-size: 0.8rem; color: #777; }

/* Mobile grid */
.mobile-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(165px, 1fr));
    gap: 12px;
}

/* Hide desktop table on mobile */
@media (max-width:768px){
    .table-container { display:none; }
    .btn-group { width:100%; }
}

/* Hide mobile cards on desktop */
@media (min-width:769px){
    .mobile-view { display:none; }
}
</style>
</head>
<body>

<?php include 'header.php'; ?>

<div class="container my-4">

    <!-- Header + Export -->
    <div class="d-flex justify-content-between align-items-center flex-wrap mb-3">
        <h2 class="text-success fw-bold mb-2">Customers</h2>

        <div class="btn-group mb-2">
            <a href="?export=csv" class="btn btn-outline-primary btn-sm">CSV</a>
            <a href="?export=xlsx" class="btn btn-outline-success btn-sm">Excel</a>
            <a href="?export=pdf" class="btn btn-outline-danger btn-sm">PDF</a>
        </div>
    </div>

    <!-- Search + Limit -->
    <form class="row g-2 mb-3">
        <div class="col-12 col-md-4">
            <input type="text" name="search" value="<?=h($search)?>" class="form-control" placeholder="Search name or email">
        </div>

        <div class="col-6 col-md-2">
            <select name="limit" class="form-select">
                <?php foreach ([10,20,50] as $l): ?>
                    <option value="<?=$l?>" <?=($l==$limit?"selected":"")?>><?=$l?> per page</option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-6 col-md-2">
            <button class="btn btn-primary w-100">Apply</button>
        </div>
    </form>

    <!-- MOBILE VIEW -->
    <div class="mobile-view">
        <div class="mobile-grid">
            <?php foreach($users as $u): ?>
                <div class="card-customer">
                    <h6 class="fw-bold mb-1"><?=h($u['name'])?></h6>
                    <div class="customer-email"><?=h($u['email'])?></div>
                    <div class="customer-joined mt-1">
                        Joined: <?=date("d M Y", strtotime($u['created_at']))?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- DESKTOP TABLE -->
    <div class="table-container">
        <table class="table table-bordered table-striped">
            <thead class="table-success">
                <tr>
                    <th>ID</th><th>Name</th><th>Email</th><th>Joined</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($users as $u): ?>
                <tr>
                    <td><?=h($u['id'])?></td>
                    <td><?=h($u['name'])?></td>
                    <td><?=h($u['email'])?></td>
                    <td><?=h($u['created_at'])?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <nav class="mt-3">
        <ul class="pagination justify-content-center">
            <?php if ($page > 1): ?>
                <li class="page-item"><a class="page-link" href="?page=<?=($page-1)?>&limit=<?=$limit?>&search=<?=$search?>">Previous</a></li>
            <?php endif; ?>

            <?php for ($i=1; $i <= $total_pages; $i++): ?>
                <li class="page-item <?=($i==$page?'active':'')?>"><a class="page-link" href="?page=<?=$i?>&limit=<?=$limit?>&search=<?=$search?>"><?=$i?></a></li>
            <?php endfor; ?>

            <?php if ($page < $total_pages): ?>
                <li class="page-item"><a class="page-link" href="?page=<?=($page+1)?>&limit=<?=$limit?>&search=<?=$search?>">Next</a></li>
            <?php endif; ?>
        </ul>
    </nav>

</div>




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

<script src="/picklehub_project/admin/assets/js/admin.js" defer></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.1/dist/js/bootstrap.bundle.min.js" defer></script>


<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

