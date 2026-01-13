<?php
require '../includes/db.php';
require '../includes/functions.php';
is_admin();

$success = '';
$error = '';

$uploadDir = __DIR__ . '/../uploads/';
if (!is_dir($uploadDir)) mkdir($uploadDir, 0775, true);
if (!is_writable($uploadDir)) $error = "Uploads folder not writable: $uploadDir";

$settings = $pdo->query("SELECT `key`,`value` FROM settings")->fetchAll(PDO::FETCH_KEY_PAIR);

// Banner slides
$banner_slides = isset($settings['homepage_banner_slides']) ? explode(',', $settings['homepage_banner_slides']) : [];
$banner_slides = array_map('basename', $banner_slides);
$banner_slides = array_pad($banner_slides, 4, 'banner_placeholder.jpg');

/* ---------------- SAVE BANNER ---------------- */
if (isset($_POST['save_banner']) && !$error) {
    $index = (int)$_POST['banner_index'];

    if (!empty($_FILES['banner_file']['tmp_name'])) {
        $ext = pathinfo($_FILES['banner_file']['name'], PATHINFO_EXTENSION);
        $fileName = "banner_" . time() . "_$index.$ext";

        if (move_uploaded_file($_FILES['banner_file']['tmp_name'], $uploadDir.$fileName)) {
            $banner_slides[$index] = $fileName;
            $value = implode(',', $banner_slides);

            $pdo->prepare("INSERT INTO settings (`key`,`value`) VALUES ('homepage_banner_slides',?) 
                           ON DUPLICATE KEY UPDATE `value`=?")
                ->execute([$value, $value]);

            $success = "Banner #".($index+1)." updated!";
        } else $error = "Failed uploading banner.";
    }
}

/* ---------------- SAVE ABOUT US ---------------- */
if (isset($_POST['save_about']) && !$error) {
    // Image
    if (!empty($_FILES['about_us_image']['tmp_name'])) {
        $ext = pathinfo($_FILES['about_us_image']['name'], PATHINFO_EXTENSION);
        $fileName = "about_" . time() . ".$ext";

        if (move_uploaded_file($_FILES['about_us_image']['tmp_name'], $uploadDir.$fileName)) {
            $settings['about_us_image'] = $fileName;

            $pdo->prepare("INSERT INTO settings (`key`,`value`) VALUES ('about_us_image',?) 
                           ON DUPLICATE KEY UPDATE `value`=?")
                ->execute([$fileName, $fileName]);

            $success .= " About Us image updated.";
        } else $error .= " Failed uploading About Us image.";
    }

    // Text
    $text = trim($_POST['about_us_text']);
    $settings['about_us_text'] = $text;

    $pdo->prepare("INSERT INTO settings (`key`,`value`) VALUES ('about_us_text',?) 
                   ON DUPLICATE KEY UPDATE `value`=?")
        ->execute([$text, $text]);

    $success .= " About text updated.";
}

/* ---------------- SAVE MAP ---------------- */
if (isset($_POST['save_map']) && !$error) {
    $map = trim($_POST['homepage_map_embed']);
    $settings['homepage_map_embed'] = $map;

    $pdo->prepare("INSERT INTO settings (`key`,`value`) VALUES ('homepage_map_embed',?) 
                   ON DUPLICATE KEY UPDATE `value`=?")
        ->execute([$map, $map]);

    $success .= " Map updated.";
}

$about_image = $settings['about_us_image'] ?? 'about_placeholder.jpg';
$about_text  = $settings['about_us_text'] ?? '';
$map_iframe  = $settings['homepage_map_embed'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Settings - Admin</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.1/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="../public/assets/style.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">

<style>
body { background: #f5f7fa; font-family: 'Segoe UI', sans-serif; }

.card-modern {
  border-radius: 14px;
  background: #fff;
  padding: 0;
  overflow: hidden;
  box-shadow: 0 5px 15px rgba(0,0,0,0.07);
  margin-bottom: 25px;
}

.card-header-custom {
  padding: 12px 18px;
  background: linear-gradient(135deg, #198754, #20c997);
  color: #fff;
  font-weight: 600;
  font-size: 1.1rem;
  display: flex;
  align-items: center;
  gap: 8px;
}

.img-preview {
  width: 100%;
  height: 130px;
  border-radius: 12px;
  object-fit: cover;
  background: #e9ecef;
}

#aboutPreview {
  width: 100%;
  height: 220px;
  border-radius: 10px;
  object-fit: cover;
}

.map-frame iframe {
  width: 100%;
  height: 320px;
  border: none;
  border-radius: 10px;
}

/* MOBILE OPTIMIZATION */
@media(max-width: 768px){
  .banner-col {
    flex: 0 0 50%;
    max-width: 50%;
  }
  .card-modern { margin-bottom: 20px; }
  .img-preview { height: 110px; }
  #aboutPreview { height: 180px; }
}
</style>
</head>
<body>

<?php include 'header.php'; ?>

<div class="container my-4">

<h2 class="text-success fw-bold mb-3"><i class="bi bi-gear-fill me-2"></i>Website Settings</h2>

<?php if ($success): ?>
<div class="alert alert-success"><i class="bi bi-check-circle-fill me-1"></i><?= $success ?></div>
<?php endif; ?>

<?php if ($error): ?>
<div class="alert alert-danger"><i class="bi bi-exclamation-triangle-fill me-1"></i><?= $error ?></div>
<?php endif; ?>

<!-- ====================== BANNERS ====================== -->
<div class="card-modern">
  <div class="card-header-custom"><i class="bi bi-images"></i> Homepage Banners</div>
  <div class="card-body">
    <div class="row g-3">
      <?php foreach($banner_slides as $i => $slide): ?>
      <div class="col-6 col-md-3 banner-col">
        <form method="POST" enctype="multipart/form-data" class="p-2 border rounded shadow-sm bg-white">
          <img id="bannerPreview<?= $i ?>" src="../uploads/<?=h($slide)?>" class="img-preview mb-2">
          <input type="file" name="banner_file" class="form-control form-control-sm mb-2"
                 onchange="previewImage(this, 'bannerPreview<?= $i ?>')">
          <input type="hidden" name="banner_index" value="<?= $i ?>">
          <button class="btn btn-success btn-sm w-100" name="save_banner">Save</button>
        </form>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<!-- ====================== ABOUT US ====================== -->
<div class="card-modern">
  <div class="card-header-custom"><i class="bi bi-info-circle"></i> About Us</div>
  <div class="card-body">
    <div class="row g-3">
      <div class="col-md-4">
        <img id="aboutPreview" src="../uploads/<?=h($about_image)?>" alt="">
      </div>

      <div class="col-md-8">
        <form method="POST" enctype="multipart/form-data">
          <label class="fw-semibold">About Image</label>
          <input type="file" name="about_us_image" class="form-control mb-2"
                 onchange="previewImage(this, 'aboutPreview')">

          <label class="fw-semibold">About Text</label>
          <textarea name="about_us_text" class="form-control mb-3" rows="5"><?=h($about_text)?></textarea>

          <button class="btn btn-success px-4" name="save_about">Save About Us</button>
        </form>
      </div>
    </div>
  </div>
</div>

<!-- ====================== MAP SECTION ====================== -->
<div class="card-modern">
  <div class="card-header-custom"><i class="bi bi-map"></i> Homepage Map</div>
  <div class="card-body">

    <form method="POST">
      <label class="fw-semibold">Google Map Iframe</label>
      <textarea name="homepage_map_embed" class="form-control mb-3" rows="3"><?=h($map_iframe)?></textarea>
      <button class="btn btn-success px-4" name="save_map">Save Map</button>
    </form>

    <?php if($map_iframe): ?>
    <div class="map-frame mt-3">
      <?= $map_iframe ?>
    </div>
    <?php endif; ?>
  </div>
</div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.1/dist/js/bootstrap.bundle.min.js"></script>

<script>
function previewImage(input, id) {
    if (input.files && input.files[0]) {
        const r = new FileReader();
        r.onload = e => document.getElementById(id).src = e.target.result;
        r.readAsDataURL(input.files[0]);
    }
}
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
<script src="assets/js/admin.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.1/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

