<?php
// public/includes/footer.php
if (session_status() == PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';

// Fetch settings
$whatsapp_number = get_setting('whatsapp_number', $pdo) ?: "+919876543210";
$call_number     = get_setting('shop_phone', $pdo) ?: "+919876543210";
$shop_address    = get_setting('shop_address', $pdo) ?: "123 Pickle Street, Foodie Town, India";

$wa_clean   = preg_replace('/\D/', '', $whatsapp_number);
$call_clean = preg_replace('/\D/', '', $call_number);
?>
<footer class="footer bg-lightgreen text-dark pt-5 mt-5">
  <div class="container">
    <div class="row gy-4">

      <!-- Brand Info -->
      <div class="col-12 col-md-4" data-aos="fade-up">
        <h5 class="fw-bold">Avoji Foods</h5>
        <p class="small">Handcrafted pickles — tangy, fresh, and made with love. Delivered to your doorstep.</p>
        <p class="small">&copy; <?= date('Y') ?> Avoji Foods. All Rights Reserved.</p>

        <div class="d-flex gap-3 mt-2 footer-social">
          <a href="#" class="text-dark fs-5 social-icon"><i class="bi bi-facebook"></i></a>
          <a href="#" class="text-dark fs-5 social-icon"><i class="bi bi-instagram"></i></a>
          <a href="#" class="text-dark fs-5 social-icon"><i class="bi bi-twitter"></i></a>
        </div>
      </div>

      <!-- Quick Links -->
      <div class="col-6 col-md-4" data-aos="fade-up" data-aos-delay="100">
        <h6 class="fw-bold">Quick Links</h6>
        <ul class="list-unstyled">
          <li><a class="footer-link" href="index.php">Home</a></li>
          <li><a class="footer-link" href="products.php">Products</a></li>
          <li><a class="footer-link" href="index.php#about-us">About Us</a></li>
          <li><a class="footer-link contact-blink" href="contact.php">Contact Us</a></li>

          <?php if (isset($_SESSION['user']) && in_array($_SESSION['user']['role'], ['admin','super_admin'])): ?>
            <li>
              <a class="footer-link text-danger fw-bold" href="../admin/index.php" target="_blank">
                <i class="bi bi-shield-lock"></i> Admin Dashboard
              </a>
            </li>
          <?php endif; ?>
        </ul>
      </div>

      <!-- SHOP ADDRESS + CONTACT -->
      <div class="col-6 col-md-4" data-aos="fade-up" data-aos-delay="150">
        <h6 class="fw-bold">Contact Information</h6>
        <p class="small mb-1">
          <i class="bi bi-geo-alt-fill text-success"></i>
          <?= h($shop_address) ?>
        </p>

        <p class="small mb-1">
          <i class="bi bi-telephone-fill text-success"></i>
          <a href="tel:<?= h($call_clean) ?>" class="footer-link"><?= h($call_number) ?></a>
        </p>

        <p class="small">
          <i class="bi bi-whatsapp text-success"></i>
          <a href="https://wa.me/<?= h($wa_clean) ?>" class="footer-link" target="_blank"><?= h($whatsapp_number) ?></a>
        </p>
      </div>

    </div>

    <div class="row mt-4">
      <div class="col text-center">
        <small class="text-dark">Designed with ❤️ by Avoji Foods</small>
      </div>
    </div>
  </div>
</footer>

<!-- Floating WhatsApp -->
<a href="https://wa.me/<?= $wa_clean ?>" class="floating-whatsapp" target="_blank">
  <span class="wa-emoji">💬</span>
  <i class="bi bi-whatsapp"></i>
</a>

<!-- Floating Call Now -->
<a href="tel:<?= $call_clean ?>" class="floating-call">
  <span class="call-emoji">📞</span>
  <i class="bi bi-telephone-fill"></i>
</a>

<style>
/* Footer BG */
.bg-lightgreen {
    background: #d4edda;
    border-top: 4px solid #81c784;
    border-radius: 12px 12px 0 0;
    font-family: 'Segoe UI', Tahoma, Geneva;
}

/* Footer Links */
.footer-link {
    color: #000;
    text-decoration: none;
    transition: .3s ease;
}
.footer-link:hover {
    color: #2e7d32;
}

/* Social Icons */
.social-icon i {
    transition: .3s;
}
.social-icon:hover i {
    color: #2e7d32;
    transform: scale(1.3);
}

/* Floating Buttons */
.floating-whatsapp, .floating-call {
    position: fixed;
    right: 20px;
    width: 60px;
    height: 60px;
    color: #fff;
    border-radius: 50%;
    font-size: 30px;
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 99999;
    border: 3px solid #fff;
    box-shadow: 0 6px 18px rgba(0,0,0,0.25);
    animation: floatPulse 2s infinite ease-in-out;
    cursor: pointer;
}

.floating-whatsapp { bottom: 95px; background: #25D366; }
.floating-call     { bottom: 25px; background: #ff5722; }

.floating-whatsapp:hover,
.floating-call:hover {
    transform: scale(1.15);
}

@keyframes floatPulse {
    0% { transform: scale(1); }
    50% { transform: scale(1.12); }
    100% { transform: scale(1); }
}

/* Mobile Responsive */
@media(max-width:768px){
  footer { text-align:center; }
  .footer-social { justify-content:center; }
}
</style>

<script src="https://cdn.jsdelivr.net/npm/aos@2.3.4/dist/aos.js"></script>
<script>AOS.init({ duration:1000, once:true });</script>
