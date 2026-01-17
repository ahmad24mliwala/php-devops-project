<?php
// public/includes/footer.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/db.php';

/* ==========================
   SETTINGS
========================== */
$site_name    = get_setting('site_name', $pdo) ?: 'Avoji Foods';
$wa_raw       = get_setting('whatsapp_number', $pdo) ?: '+919876543210';
$phone_raw    = get_setting('shop_phone', $pdo) ?: '+919876543210';
$shop_address = get_setting('shop_address', $pdo) ?: 'India';

/* ==========================
   CLEAN NUMBERS
========================== */
function first_number($v) {
    $v = trim($v);
    if (strpos($v, ',') !== false || strpos($v, '/') !== false) {
        $p = preg_split('/[,\/]/', $v);
        return trim($p[0]);
    }
    return $v;
}

$wa_number    = first_number($wa_raw);
$phone_number = first_number($phone_raw);

$wa_clean     = preg_replace('/\D+/', '', $wa_number);
$phone_clean  = preg_replace('/\D+/', '', $phone_number);

/* ==========================
   WHATSAPP PREFILLED MESSAGE
========================== */
$wa_message = "Hello $site_name, I would like to know more about your products.";
$wa_link    = "https://wa.me/$wa_clean?text=" . rawurlencode($wa_message);
?>

<script>
document.addEventListener("DOMContentLoaded", () => {
  const footer = document.querySelector(".footer-animate");
  if (!footer) return;

  // Force animation restart on every visit
  footer.classList.remove("footer-animate");

  // Trigger reflow
  void footer.offsetWidth;

  // Re-add animation class
  footer.classList.add("footer-animate");
});
</script>


<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">

<footer class="site-footer">
  <div class="container">
    <div class="row gy-4 footer-animate">

      <!-- BRAND -->
      <div class="col-12 col-md-4">
        <h4 class="footer-brand"><?= h($site_name) ?></h4>
        <p class="footer-desc">
          Handcrafted pickles made with authentic taste & tradition.
        </p>
        <p class="footer-copy">
          © <?= date('Y') ?> <?= h($site_name) ?>. All rights reserved.
        </p>

        <div class="footer-social">
          <a href="#" aria-label="Facebook"><i class="bi bi-facebook"></i></a>
          <a href="#" aria-label="Instagram"><i class="bi bi-instagram"></i></a>
          <a href="#" aria-label="Twitter"><i class="bi bi-twitter-x"></i></a>
        </div>
      </div>

      <!-- LINKS -->
      <div class="col-6 col-md-4">
        <h6 class="footer-title">Quick Links</h6>
        <ul class="footer-links">
          <li><a href="/index.php">Home</a></li>
          <li><a href="/products.php">Products</a></li>
          <li><a href="/index.php#about-us">About Us</a></li>
          <li><a href="/contact.php">Contact</a></li>

          <?php if (!empty($_SESSION['user']) && in_array($_SESSION['user']['role'], ['admin','super_admin'])): ?>
            <li>
              <a class="admin-link" href="/admin/index.php" target="_blank">
                <i class="bi bi-shield-lock"></i> Admin Dashboard
              </a>
            </li>
          <?php endif; ?>
        </ul>
      </div>

      <!-- CONTACT -->
      <div class="col-6 col-md-4">
        <h6 class="footer-title">Contact</h6>

        <p class="footer-contact">
          <i class="bi bi-geo-alt-fill"></i>
          <?= h($shop_address) ?>
        </p>

        <p class="footer-contact">
          <i class="bi bi-telephone-fill"></i>
          <a href="tel:<?= h($phone_clean) ?>">
            <?= h($phone_number) ?>
          </a>
        </p>

        <p class="footer-contact">
          <i class="bi bi-whatsapp"></i>
          <a href="<?= h($wa_link) ?>" target="_blank">
            <?= h($wa_number) ?>
          </a>
        </p>
      </div>

    </div>
  </div>
</footer>

<!-- FLOATING WHATSAPP -->
<a href="<?= h($wa_link) ?>"
   class="whatsapp-float"
   target="_blank"
   aria-label="WhatsApp Chat">
   <i class="bi bi-whatsapp"></i>
</a>

<style>





/* ================= FOOTER BASE ================= */
.site-footer {
  background: linear-gradient(180deg, #e8f5e9, #d0ebd8);
  border-top: 4px solid #2e7d32;
  padding: 60px 0 50px;
  font-family: 'Segoe UI', sans-serif;
}

/* Entrance animation */
/* ================= CINEMATIC FOOTER ENTRANCE ================= */
.footer-animate {
  animation: footerReveal 1.1s cubic-bezier(0.22, 1, 0.36, 1);
  transform-origin: center;
  perspective: 1200px;
}

@keyframes footerReveal {
  0% {
    opacity: 0;
    transform: translateY(-40px) scale(0.94) translateZ(-120px);
    filter: blur(6px);
  }
  60% {
    opacity: 1;
    transform: translateY(6px) scale(1.01) translateZ(20px);
    filter: blur(1px);
  }
  100% {
    opacity: 1;
    transform: none;
    filter: blur(0);
  }
}



/* TEXT */
.footer-brand { font-weight: 700; color: #1b5e20; }
.footer-desc  { font-size: 14px; color: #333; }
.footer-copy  { font-size: 13px; color: #555; }

.footer-title {
  font-weight: 600;
  margin-bottom: 14px;
  color: #1b5e20;
}

/* LINKS */
.footer-links { list-style: none; padding: 0; }
.footer-links li { margin-bottom: 8px; }

.footer-links a {
  color: #000;
  text-decoration: none;
  transition: all .25s ease;
}
.footer-links a:hover {
  color: #2e7d32;
  padding-left: 6px;
}

/* CONTACT */
.footer-contact,
.footer-contact a {
  color: #000;
  text-decoration: none;
  display: flex;
  gap: 8px;
  align-items: center;
  margin-bottom: 8px;
}

/* SOCIAL */
.footer-social a {
  font-size: 22px;
  margin-right: 14px;
  color: #1b5e20;
  transition: transform .3s, color .3s;
}
.footer-social a:hover {
  transform: translateY(-4px) scale(1.25);
  color: #2e7d32;
}

/* ADMIN */
.admin-link {
  color: #c62828 !important;
  font-weight: 600;
}

/* ================= WHATSAPP FLOAT ================= */
.whatsapp-float {
  position: fixed;
  right: 20px;
  bottom: 90px;
  width: 64px;
  height: 64px;
  background: #25D366;
  color: #fff;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 34px;
  z-index: 99999;
  box-shadow: 0 8px 22px rgba(0,0,0,.35);
  animation: pulse 1.8s infinite;
}
.whatsapp-float:hover {
  transform: scale(1.15);
}

/* Pulse animation */
@keyframes pulse {
  0%   { transform: scale(1); }
  50%  { transform: scale(1.12); }
  100% { transform: scale(1); }
}

/* ================= MOBILE ================= */
@media (max-width: 768px) {
  .site-footer { text-align: center; padding-bottom: 80px; }
  .footer-contact { justify-content: center; }
  .footer-social { justify-content: center; }
  .whatsapp-float {
    bottom: 70px;
    width: 56px;
    height: 56px;
    font-size: 30px;
  }
}

/* ================= MODERN HOVER ANIMATIONS ================= */

/* ================= MODERN HOVER ANIMATIONS ================= */

/* Footer column hover lift */
.site-footer .col-md-4,
.site-footer .col-md-6 {
  transition: transform .45s cubic-bezier(.22,1,.36,1),
              box-shadow .45s ease;
}

.site-footer .col-md-4:hover,
.site-footer .col-md-6:hover {
  transform: translateY(-10px) scale(1.015);
  box-shadow: 0 18px 40px rgba(0,0,0,.08);
}

/* Link underline sweep animation */
.footer-links a {
  position: relative;
}

.footer-links a::after {
  content: "";
  position: absolute;
  left: 0;
  bottom: -4px;
  width: 0;
  height: 2px;
  background: #2e7d32;
  transition: width .35s ease;
}

.footer-links a:hover::after {
  width: 100%;
}

/* Contact icon micro bounce */
.footer-contact i {
  transition: transform .35s ease, color .35s ease;
}

.footer-contact:hover i {
  transform: translateY(-3px) scale(1.15);
  color: #2e7d32;
}

/* Social icon elastic hover */
.footer-social a {
  transition: transform .4s cubic-bezier(.34,1.56,.64,1), color .3s;
}

.footer-social a:hover {
  transform: rotate(-6deg) scale(1.35);
}

/* WhatsApp floating magnetic hover */
.whatsapp-float {
  transition: transform .4s cubic-bezier(.34,1.56,.64,1),
              box-shadow .4s ease;
}

.whatsapp-float:hover {
  transform: scale(1.25) rotate(-8deg);
  box-shadow: 0 18px 35px rgba(37,211,102,.55);
}

/* ================= 3D FLIP-IN ON EVERY VISIT ================= */

.site-footer {
  perspective: 1400px;
}

.footer-animate {
  animation: footerFlipIn 1.2s cubic-bezier(.22,1,.36,1);
  transform-origin: center top;
  backface-visibility: hidden;
}

@keyframes footerFlipIn {
  0% {
    opacity: 0;
    transform:
      rotateX(-85deg)
      translateY(-40px)
      scale(0.96);
    filter: blur(6px);
  }
  55% {
    opacity: 1;
    transform:
      rotateX(12deg)
      translateY(8px)
      scale(1.01);
    filter: blur(1px);
  }
  100% {
    opacity: 1;
    transform: none;
    filter: blur(0);
  }
}




</style>

<script>
document.addEventListener("DOMContentLoaded", () => {
  const footer = document.querySelector(".footer-animate");
  if (!footer) return;

  // Remove animation class
  footer.classList.remove("footer-animate");

  // Force browser reflow
  void footer.offsetHeight;

  // Re-add animation class (replays animation every visit)
  footer.classList.add("footer-animate");
});
</script>



