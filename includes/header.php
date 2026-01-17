<?php
// public_html/includes/header.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/track_visit.php'; // ✅ AFTER session

// Log visit ONCE
//log_visit($pdo);


// Settings
$site_name = get_setting('site_name', $pdo) ?: 'Avoji Foods';

// User
$user = $_SESSION['user'] ?? null;

// Cart Count
$cart_count = array_sum(array_column($_SESSION['cart'] ?? [], 'qty') ?: [0]);

// ✅ CHECK GLOBAL CART STATUS (ADMIN CONTROL)
$stmt = $pdo->query("SELECT COUNT(*) FROM products WHERE is_cart_enabled = 1");
$is_cart_globally_enabled = $stmt->fetchColumn() > 0;

// Base URL (Hostinger friendly)
$base_url = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
?>

<nav class="navbar navbar-expand-lg premium-navbar sticky-top">
  <div class="container">

    <!-- LOGO -->
  <?php
$site_logo = get_setting('site_logo', $pdo); // filename only
$logo_path = $site_logo
    ? "image.php?file=" . urlencode($site_logo)
    : null;
?>

<a class="navbar-brand d-flex align-items-center brand-animate"
   href="<?= $base_url ?>/index.php">

  <?php if ($logo_path): ?>
    <img src="<?= h($logo_path) ?>"
         alt="<?= h($site_name) ?>"
         class="site-logo">
  <?php else: ?>
    <span class="fw-bold fs-3"><?= h($site_name) ?></span>
  <?php endif; ?>

</a>


    <!-- TOGGLER -->
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navMain">
      <span class="navbar-toggler-icon"></span>
    </button>

    <!-- NAV MENU -->
    <div class="collapse navbar-collapse" id="navMain">
      <ul class="navbar-nav ms-auto align-items-center gap-lg-2 nav-fade">

        <li class="nav-item">
          <a class="nav-link premium-link" href="<?= $base_url ?>/index.php">🏠 Home</a>
        </li>

        <li class="nav-item">
          <a class="nav-link premium-link" href="<?= $base_url ?>/products.php">📦 Products</a>
        </li>

        <li class="nav-item">
          <a class="nav-link btn-contact-us ms-lg-2 px-3 py-1 rounded" href="<?= $base_url ?>/contact.php">
            💬 Contact Us
          </a>
        </li>

        <!-- ✅ CART (VISIBLE ONLY IF ENABLED BY ADMIN) -->
        <?php if ($is_cart_globally_enabled): ?>
        <li class="nav-item position-relative ms-lg-3 cart-animate">
          <a class="nav-link premium-link" href="<?= $base_url ?>/cart.php">
            🛒 Cart
            <?php if ($cart_count): ?>
              <span class="cart-badge pulse"><?= $cart_count ?></span>
            <?php endif; ?>
          </a>
        </li>
        <?php endif; ?>

        <!-- AUTH -->
        <?php if (!$user): ?>
          <li class="nav-item">
            <a class="btn btn-outline-success ms-lg-2" href="<?= $base_url ?>/login.php">🔐 Login</a>
          </li>
          <li class="nav-item">
            <a class="btn btn-warning ms-lg-2" href="<?= $base_url ?>/register.php">🆕 Register</a>
          </li>
        <?php else:
          $initials = strtoupper(substr($user['name'] ?? $user['email'], 0, 1));
        ?>
          <li class="nav-item dropdown ms-lg-3">
            <a class="nav-link dropdown-toggle d-flex align-items-center premium-avatar" href="#" data-bs-toggle="dropdown">
              <div class="avatar-circle"><?= h($initials) ?></div>
            </a>
            <ul class="dropdown-menu dropdown-menu-end fade-in">
              <li><a class="dropdown-item" href="<?= $base_url ?>/my-account.php">👤 My Account</a></li>
              <li><a class="dropdown-item" href="<?= $base_url ?>/logout.php">🚪 Logout</a></li>
            </ul>
          </li>
        <?php endif; ?>

      </ul>
    </div>
  </div>
</nav>

<!-- ✅ MOBILE BOTTOM MENU -->
<div class="mobile-bottom-menu d-lg-none d-flex justify-content-around slide-up">
  <a href="<?= $base_url ?>/index.php">🏠 <span>Home</span></a>
  <a href="<?= $base_url ?>/products.php">📦 <span>Products</span></a>

  <?php if ($is_cart_globally_enabled): ?>
  <a href="<?= $base_url ?>/cart.php">
    🛒 <span>Cart</span>
    <?php if ($cart_count): ?><em><?= $cart_count ?></em><?php endif; ?>
  </a>
  <?php endif; ?>

  <a href="<?= $base_url ?>/contact.php">📞 <span>Contact</span></a>
</div>

<style>
/* 🌿 GLASS NAVBAR */
.premium-navbar {
  background: rgba(255,255,255,0.92);
  backdrop-filter: blur(14px);
  border-bottom: 2px solid #e6f4ea;
  transition: box-shadow .4s ease;
}

/*Logo */
.site-logo {
  height: 46px;
  max-width: 180px;
  object-fit: contain;
}

/* Mobile */
@media (max-width: 768px) {
  .site-logo {
    height: 36px;
    max-width: 140px;
  }
}


/* 🌟 BRAND ANIMATION */
.brand-animate {
  color:#1b5e20;
  animation: floatIn .8s ease-out;
}
@keyframes floatIn {
  from { opacity:0; transform:translateY(-10px); }
  to { opacity:1; transform:none; }
}

/* NAV LINKS */
.premium-link {
  font-weight: 500;
  color: #1b5e20 !important;
  transition: .3s;
}
.premium-link:hover {
  color: #2e7d32 !important;
  transform: translateY(-2px) scale(1.03);
}

/* CONTACT BLINK */
.btn-contact-us {
  background:#ffc107;
  font-weight:bold;
  animation: blinkBtn 1.4s infinite;
}
@keyframes blinkBtn {
  0%,100% { opacity:1 }
  50% { opacity:.6 }
}

/* CART BADGE */
.cart-badge {
  background:#ff1744;
  color:white;
  border-radius:50%;
  padding:3px 7px;
  font-size:12px;
  position:absolute;
  top:-6px; right:-12px;
}
.pulse {
  animation:pulse 1.5s infinite;
}
@keyframes pulse {
  0% { transform:scale(1); }
  50% { transform:scale(1.15); }
  100% { transform:scale(1); }
}

/* AVATAR */
.avatar-circle {
  width:38px;height:38px;
  background:#198754;color:white;
  border-radius:50%;
  display:flex;align-items:center;justify-content:center;
  font-weight:bold;
}

/* MOBILE MENU */
.mobile-bottom-menu {
  position:fixed;
  bottom:0;left:0;right:0;
  background:#fff;
  border-top:2px solid #d4edda;
  padding:8px 0;
  z-index:9999;
}
.mobile-bottom-menu a {
  text-decoration:none;
  font-size:12px;
  color:#1b5e20;
  font-weight:600;
  display:flex;
  flex-direction:column;
  align-items:center;
}
.mobile-bottom-menu em {
  background:#ff1744;
  color:white;
  padding:2px 6px;
  border-radius:10px;
  font-size:11px;
  position:absolute;
  margin-top:-28px;
}

/* ANIMATIONS */
.slide-up {
  animation: slideUp .6s ease-out;
}
@keyframes slideUp {
  from { transform:translateY(100%); }
  to { transform:none; }
}
.fade-in {
  animation: fadeIn .3s ease-out;
}
@keyframes fadeIn {
  from { opacity:0; transform:translateY(5px); }
  to { opacity:1; }
}
</style>

<script>
document.addEventListener("DOMContentLoaded", () => {
  const navbar = document.querySelector(".premium-navbar");
  window.addEventListener("scroll", () => {
    navbar.style.boxShadow = window.scrollY > 40
      ? "0 4px 20px rgba(0,0,0,.15)"
      : "none";
  });
});
</script>
