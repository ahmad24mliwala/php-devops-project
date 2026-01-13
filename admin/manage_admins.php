<?php
// admin/manage_admins.php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

if (session_status() == PHP_SESSION_NONE) session_start();
is_super_admin(); // Only super admin

$errors = [];
$success = "";

/* ---------- ADD ADMIN ---------- */
if (isset($_POST['add_admin'])) {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm'] ?? '';

    if (!$name) $errors[] = "Name is required.";
    if (!$email) $errors[] = "Email is required.";
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Invalid email format.";
    if (!$password) $errors[] = "Password is required.";
    if ($password !== $confirm) $errors[] = "Passwords do not match.";

    $stmt = $pdo->prepare("SELECT id FROM users WHERE email=?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) $errors[] = "This email is already registered.";

    if (!$errors) {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users (name,email,password,role) VALUES (?,?,?,?)");
        $stmt->execute([$name, $email, $hash, 'admin']);
        $success = "Admin <strong>$name</strong> created successfully.";
    }
}

/* ---------- DELETE ADMIN ---------- */
if (isset($_POST['delete_admin'])) {
    $id = intval($_POST['id']);
    $pdo->prepare("DELETE FROM users WHERE id=? AND role='admin'")->execute([$id]);
    $success = "Admin deleted successfully.";
}

/* ---------- FETCH ADMINS ---------- */
$admins = $pdo->query("SELECT * FROM users WHERE role='admin' ORDER BY created_at DESC")->fetchAll();
$super_admin = $pdo->query("SELECT * FROM users WHERE role='super_admin' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Manage Admins - Avoji Foods</title>
<meta name="viewport" content="width=device-width,initial-scale=1">

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.1/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="../public/assets/style.css" rel="stylesheet">

<style>
body {
    background: #f8fafc;
    font-family: "Poppins", sans-serif;
}

/* Modern cards */
.card-modern {
    border-radius: 16px;
    background: white;
    box-shadow: 0 3px 12px rgba(0,0,0,0.1);
    padding: 20px;
}

/* Mobile admin card view */
.admin-card {
    border-radius: 14px;
    background: white;
    padding: 15px;
    box-shadow: 0 3px 10px rgba(0,0,0,0.08);
}
.admin-email { font-size: 0.85rem; color: #555; }
.admin-date { font-size: 0.8rem; color: #777; }

/* Hide desktop table on mobile */
@media (max-width: 768px) {
    .desktop-table { display: none; }
}

/* Hide mobile card view on desktop */
@media (min-width: 769px) {
    .mobile-view { display: none; }
}
</style>
</head>

<body>
<?php include 'header.php'; ?>

<div class="container my-4">

<h2 class="text-center fw-bold text-success mb-4">Manage Admins</h2>

<?php if($errors): ?>
    <div class="alert alert-danger"><?php foreach($errors as $e) echo "$e<br>"; ?></div>
<?php endif; ?>

<?php if($success): ?>
    <div class="alert alert-success"><?= $success ?></div>
<?php endif; ?>

<!-- SUPER ADMIN CARD -->
<div class="card-modern mb-4">
    <h5 class="fw-bold text-success mb-2">Super Admin</h5>
    <p><strong>Name:</strong> <?=h($super_admin['name'])?></p>
    <p><strong>Email:</strong> <?=h($super_admin['email'])?></p>
</div>

<!-- ADD ADMIN FORM -->
<div class="card-modern mb-4">
    <h5 class="fw-bold mb-3 text-primary">Add New Admin</h5>

    <form method="POST" class="row g-2">
        <div class="col-12 col-md-4">
            <input type="text" name="name" class="form-control" placeholder="Full Name" required>
        </div>
        <div class="col-12 col-md-4">
            <input type="email" name="email" class="form-control" placeholder="Email" required>
        </div>
        <div class="col-6 col-md-2">
            <input type="password" name="password" class="form-control" placeholder="Pass" required>
        </div>
        <div class="col-6 col-md-2">
            <input type="password" name="confirm" class="form-control" placeholder="Confirm" required>
        </div>
        <div class="col-12 mt-2 text-end">
            <button type="submit" name="add_admin" class="btn btn-success px-4">Add Admin</button>
        </div>
    </form>
</div>

<!-- MOBILE CARD VIEW -->
<div class="mobile-view">
    <?php foreach($admins as $a): ?>
        <div class="admin-card mb-3">
            <h6 class="fw-bold mb-1"><?=h($a['name'])?></h6>
            <div class="admin-email"><?=h($a['email'])?></div>
            <div class="admin-date mt-1">Joined: <?=date("d M Y", strtotime($a['created_at']))?></div>

            <form method="POST" onsubmit="return confirm('Delete this admin?');" class="mt-3">
                <input type="hidden" name="id" value="<?=$a['id']?>">
                <button name="delete_admin" class="btn btn-danger btn-sm w-100">Delete</button>
            </form>
        </div>
    <?php endforeach; ?>
</div>

<!-- DESKTOP TABLE VIEW -->
<div class="desktop-table">
    <div class="card-modern">
        <h5 class="fw-bold mb-3 text-primary">Existing Admins</h5>

        <div class="table-responsive">
            <table class="table table-bordered table-striped align-middle">
                <thead class="table-success">
                <tr>
                    <th>ID</th><th>Name</th><th>Email</th><th>Role</th><th>Created</th><th>Action</th>
                </tr>
                </thead>

                <tbody>
                <?php foreach($admins as $a): ?>
                    <tr>
                        <td><?=h($a['id'])?></td>
                        <td><?=h($a['name'])?></td>
                        <td><?=h($a['email'])?></td>
                        <td><span class="badge bg-primary"><?=h($a['role'])?></span></td>
                        <td><?=h($a['created_at'])?></td>
                        <td>
                            <form method="POST" onsubmit="return confirm('Delete admin?');">
                                <input type="hidden" name="id" value="<?=$a['id']?>">
                                <button name="delete_admin" class="btn btn-danger btn-sm">Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>

                <?php if (!$admins): ?>
                    <tr><td colspan="6" class="text-center text-muted">No admin accounts.</td></tr>
                <?php endif; ?>
                </tbody>

            </table>
        </div>
    </div>
</div>

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



