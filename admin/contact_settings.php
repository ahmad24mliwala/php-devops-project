<?php
// admin/contact.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require '../includes/db.php';
require '../includes/functions.php';
is_admin(); // only admin can access

$success = '';
$errors = [];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $shop_address = trim($_POST['shop_address'] ?? '');
    $shop_phone = trim($_POST['shop_phone'] ?? '');
    $shop_email = trim($_POST['shop_email'] ?? '');
    $whatsapp_number = trim($_POST['whatsapp_number'] ?? '');
    $map_iframe = trim($_POST['homepage_map_embed'] ?? '');

    // Basic validation
    if (!$shop_address) $errors[] = "Shop address is required.";
    if (!$shop_phone) $errors[] = "Shop phone number is required.";
    if (!$shop_email) $errors[] = "Shop email is required.";
    if (!$whatsapp_number) $errors[] = "WhatsApp number is required.";
    if (!$map_iframe) $errors[] = "Map iframe embed code is required.";

    // Save settings
    if (!$errors) {
        $settings = [
            'shop_address' => $shop_address,
            'shop_phone' => $shop_phone,
            'shop_email' => $shop_email,
            'whatsapp_number' => $whatsapp_number,
            'homepage_map_embed' => $map_iframe
        ];

        foreach ($settings as $key => $value) {
            $stmt = $pdo->prepare("
                INSERT INTO settings (`key`, `value`)
                VALUES (?, ?)
                ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)
            ");
            $stmt->execute([$key, $value]);
        }

        $success = "Contact settings updated successfully!";
    }
}

// Fetch current settings
$current_settings = [];
$stmt = $pdo->query("SELECT `key`, `value` FROM settings");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $current_settings[$row['key']] = $row['value'];
}

// Default Google Map Embed (Avoji Foods)
if (empty($current_settings['homepage_map_embed'])) {
    $current_settings['homepage_map_embed'] = '<iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3756.2211851320574!2d73.78803847498928!3d19.70061368164456!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x3bddeb4f5f4ad4e9%3A0xc4e92a28e91f602b!2sAvoji%20Foods!5e0!3m2!1sen!2sin!4v1726768905298!5m2!1sen!2sin"
    width="100%" height="500" style="border:0;" allowfullscreen="" loading="lazy"></iframe>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Admin - Contact Settings</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.1/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="../public/assets/style.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
<style>
body { background-color: #f8fafc; font-family: 'Segoe UI', sans-serif; }
.container-fluid { margin-top: 60px; max-width: 100%; }
.card { border-radius: 14px; box-shadow: 0 6px 20px rgba(0,0,0,0.1); }
.card-header { background: linear-gradient(135deg,#28a745,#20c997); color: #fff; font-weight: 600; font-size: 1.2rem; }
textarea, input { resize: none; }
.btn-gradient {
    background: linear-gradient(135deg,#198754,#20c997);
    color: #fff;
    border: none;
}
.btn-gradient:hover {
    background: linear-gradient(135deg,#157347,#198754);
}
.map-preview {
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 4px 20px rgba(0,0,0,0.15);
    width: 100%;
    height: 500px;
}
.map-preview iframe {
    width: 100%;
    height: 100%;
    border: 0;
}
@media (max-width: 768px) {
    .card-body { padding: 1rem; }
    .map-preview { height: 300px; }
}
</style>
</head>
<body>

<?php include 'header.php'; ?>

<div class="container-fluid px-3 px-md-5">
    <div class="card mb-4">
        <div class="card-header d-flex align-items-center">
            <i class="bi bi-gear-fill me-2"></i> Manage Contact Settings
        </div>
        <div class="card-body">
            <?php if ($errors): ?>
                <div class="alert alert-danger">
                    <ul class="mb-0"><?php foreach ($errors as $err) echo "<li>".h($err)."</li>"; ?></ul>
                </div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="alert alert-success"><i class="bi bi-check-circle-fill me-1"></i><?= h($success) ?></div>
            <?php endif; ?>

            <form method="POST" novalidate>
                <div class="mb-3">
                    <label for="shop_address" class="form-label fw-semibold">Shop Address</label>
                    <textarea name="shop_address" id="shop_address" class="form-control" rows="2" required><?= h($current_settings['shop_address'] ?? '') ?></textarea>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="shop_phone" class="form-label fw-semibold">Shop Phone Numbers <small class="text-muted">(comma separated)</small></label>
                        <input type="text" name="shop_phone" id="shop_phone" class="form-control" value="<?= h($current_settings['shop_phone'] ?? '') ?>" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="shop_email" class="form-label fw-semibold">Shop Email Addresses <small class="text-muted">(comma separated)</small></label>
                        <input type="text" name="shop_email" id="shop_email" class="form-control" value="<?= h($current_settings['shop_email'] ?? '') ?>" required>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="whatsapp_number" class="form-label fw-semibold">WhatsApp Number</label>
                        <input type="text" name="whatsapp_number" id="whatsapp_number" class="form-control" value="<?= h($current_settings['whatsapp_number'] ?? '') ?>" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="homepage_map_embed" class="form-label fw-semibold">Google Map Embed Code</label>
                        <textarea name="homepage_map_embed" id="homepage_map_embed" class="form-control" rows="4" required><?= h($current_settings['homepage_map_embed'] ?? '') ?></textarea>
                        <small class="text-muted">Paste the iframe embed code from Google Maps (<em>Share → Embed a map</em>).</small>
                    </div>
                </div>

                <button class="btn btn-gradient w-100 py-2 mt-3">
                    <i class="bi bi-save me-2"></i> Save Settings
                </button>
            </form>
        </div>
    </div>

    <?php if (!empty($current_settings['homepage_map_embed'])): ?>
    <div class="card mb-4">
        <div class="card-header d-flex align-items-center">
            <i class="bi bi-map-fill me-2"></i> Live Map Preview
        </div>
        <div class="card-body p-0 map-preview">
            <?= $current_settings['homepage_map_embed'] ?>
        </div>
    </div>
    <?php endif; ?>
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

<!-- Admin JavaScript -->
<script src="assets/js/admin.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.1/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

