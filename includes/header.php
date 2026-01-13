<?php
// public/includes/header.php
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db.php';

if (session_status() == PHP_SESSION_NONE) session_start();

// Log visit
log_visit($pdo);

// Settings
$site_name = get_setting('site_name', $pdo) ?: 'Avoji Foods';

// User
$user = $_SESSION['user'] ?? null;

// Cart Count
$cart_count = array_sum(array_column($_SESSION['cart'] ?? [], 'qty') ?: [0]);

// Base URL
$base_url = dirname($_SERVER['SCRIPT_NAME']);
?>
<nav class="navbar navbar-expand-lg premium-navbar shadow-sm sticky-top">
  <div class="container">
    
    <!-- LOGO -->
    <a class="navbar-brand text-success fw-bold fs-3" href="<?= $base_url ?>/index.php">
       <?= h($site_name) ?>
    </a>

    <!-- TOGGLER -->
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navMain">
      <span class="navbar-toggler-icon"></span>
    </button>

    <!-- NAV MENU -->
    <div class="collapse navbar-collapse" id="navMain">
      <ul class="navbar-nav ms-auto align-items-center">

        <!-- HOME -->
        <li class="nav-item">
          <a class="nav-link premium-link" href="<?= $base_url ?>/index.php">
            🏠 Home
          </a>
        </li>

        <!-- PRODUCTS -->
        <li class="nav-item">
          <a class="nav-link premium-link" href="<?= $base_url ?>/products.php">
            📦 Products
          </a>
        </li>

        <!-- CONTACT US -->
        <li class="nav-item">
          <a class="nav-link btn-contact-us ms-2 px-3 py-1 rounded" href="<?= $base_url ?>/contact.php">
            💬 Contact Us
          </a>
        </li>

        <!-- CART BUTTON (restored + emoji) -->
        <li class="nav-item position-relative ms-3">
          <a class="nav-link premium-link" href="<?= $base_url ?>/cart.php">
            🛒 Cart
            <?php if($cart_count): ?>
              <span class="cart-badge"><?= $cart_count ?></span>
            <?php endif; ?>
          </a>
        </li>

        <!-- AUTH BUTTONS -->
        <?php if (!$user): ?>

          <li class="nav-item">
            <a class="btn btn-outline-success ms-2" href="<?= $base_url ?>/login.php">🔐 Login</a>
          </li>

          <li class="nav-item">
            <a class="btn btn-warning ms-2" href="<?= $base_url ?>/register.php">🆕 Register</a>
          </li>

        <?php else:
          $initials = strtoupper(substr($user['name'] ?? $user['email'], 0, 1));
        ?>

          <!-- USER DROPDOWN -->
          <li class="nav-item dropdown ms-3">
            <a class="nav-link dropdown-toggle d-flex align-items-center premium-avatar" href="#" data-bs-toggle="dropdown">
              <div class="avatar-circle"><?= h($initials) ?></div>
            </a>

            <ul class="dropdown-menu dropdown-menu-end">
              <li><a class="dropdown-item" href="<?= $base_url ?>/my-account.php">👤 My Account</a></li>
              <li><a class="dropdown-item" href="<?= $base_url ?>/logout.php">🚪 Logout</a></li>
            </ul>
          </li>

        <?php endif; ?>

      </ul>
    </div>
  </div>
</nav>

<!-- MOBILE BOTTOM MENU -->
<div class="mobile-bottom-menu d-lg-none d-flex justify-content-around">
  <a href="<?= $base_url ?>/index.php"> 🏠 <span>Home</span></a>
  <a href="<?= $base_url ?>/products.php"> 📦 <span>Products</span></a>
  <a href="<?= $base_url ?>/cart.php">
    🛒 <span>Cart</span>
    <?php if($cart_count): ?><em><?= $cart_count ?></em><?php endif; ?>
  </a>
  <a href="<?= $base_url ?>/contact.php"> 📞 <span>Contact</span></a>
</div>

<style>
/* PREMIUM GLASS NAVBAR */
.premium-navbar {
  background: rgba(255,255,255,0.9);
  backdrop-filter: blur(12px);
  border-bottom: 2px solid #e6f4ea;
}

/* HEADER LINKS */
.premium-link {
  font-weight: 500;
  color: #1b5e20 !important;
  transition: .3s;
}
.premium-link:hover {
  color: #2e7d32 !important;
  transform: translateY(-2px);
}

/* CONTACT BUTTON BLINK */
.btn-contact-us {
  background-color: #ffc107;
  color: black;
  font-weight: bold;
  animation: blinkBtn 1.4s infinite;
}
@keyframes blinkBtn {
  0%,100% { opacity: 1; }
  50% { opacity: .5; }
}

/* USER AVATAR */
.avatar-circle {
  width: 38px; height: 38px;
  background: #198754;
  color: white;
  border-radius: 50%;
  display:flex; justify-content:center; align-items:center;
  font-weight: bold;
}

/* CART BADGE */
.cart-badge {
  background:#ff1744;
  color:white;
  border-radius:50%;
  padding:3px 7px;
  font-size:12px;
  position:absolute;
  top:-5px; right:-10px;
}

/* MOBILE MENU */
.mobile-bottom-menu {
  position: fixed;
  bottom: 0; left: 0; right: 0;
  background: #ffffff;
  border-top: 2px solid #d4edda;
  padding: 8px 0;
  z-index: 9999;
}
.mobile-bottom-menu a {
  text-decoration: none;
  font-size: 12px;
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
</style>

<script>
document.addEventListener("DOMContentLoaded", () => {
  const navbar = document.querySelector(".premium-navbar");
  window.addEventListener("scroll", () => {
    navbar.style.boxShadow = window.scrollY > 50
      ? "0 4px 18px rgba(0,0,0,0.12)"
      : "none";
  });
});
</script>
