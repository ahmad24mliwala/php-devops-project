<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


// Log visits
log_visit($pdo);

// Project-level uploads folder
// Project-level uploads folder
$uploadDir = __DIR__ . '/uploads/';
if (!is_dir($uploadDir)) mkdir($uploadDir, 0775, true);

// Fetch homepage banners
$banner_images = get_setting('homepage_banner_slides', $pdo);
$banner_images = $banner_images ? explode(',', $banner_images) : [];
$banner_images = array_pad($banner_images, 4, 'banner_placeholder.jpg');
$banner_paths = array_map(fn($b) => 'image.php?file=' . urlencode($b), $banner_images);

// Fetch About Us
$about_image = get_setting('about_us_image', $pdo) ?: 'about_placeholder.jpg';
$about_text = get_setting('about_us_text', $pdo) ?: 'Family-run since 2020, crafting authentic pickles...';
$about_image_path = 'image.php?file=' . urlencode($about_image);

// Fetch dynamic map iframe
$map_iframe = get_setting('homepage_map_embed', $pdo) ?: '';

// Fetch categories
$categories = $pdo->query("SELECT id,name,image FROM categories ORDER BY id LIMIT 4")->fetchAll();
foreach ($categories as &$cat) {
    $cat['image_path'] = 'image.php?file=' . urlencode($cat['image'] ?: 'categories_placeholder.jpg');
}
unset($cat);

// Fetch featured products for homepage (max 10)
$products = $pdo->query("SELECT id,name,price,image,is_cart_enabled FROM products WHERE is_featured=1 ORDER BY created_at DESC LIMIT 10")->fetchAll();
foreach ($products as &$prod) {
    $prod['image_path'] = 'image.php?file=' . urlencode($prod['image'] ?: 'product_placeholder.jpg');
}
unset($prod);
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Avoji Foods</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.1/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="assets/style.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/aos@2.3.4/dist/aos.css" rel="stylesheet">

<style>
/* 3D Hover Effect for Product Cards */
.product-card {
    perspective: 1000px;
}
.product-card .card {
    transition: transform 0.5s ease, box-shadow 0.5s ease;
    transform-style: preserve-3d;
}
.product-card:hover .card {
    transform: rotateY(10deg) rotateX(5deg) scale(1.05);
    box-shadow: 0 15px 35px rgba(0,0,0,0.3);
}
@media (max-width: 768px) {
    .product-card .card:hover {
        transform: none;
        box-shadow: 0 8px 16px rgba(0,0,0,0.2);
    }
}

/* ⭐ PERFECT PRODUCT IMAGE — No cropping, full image visible */
.product-card .card-img-top {
    width: 100%;
    height: 220px;
    object-fit: contain;        /* shows full jar */
    background: #fff;           /* clean background */
    padding: 6px;               /* neat spacing */
    border-radius: 8px;
}

@media (max-width: 768px) {
    .product-card .card-img-top {
        height: 160px;
        padding: 4px;
    }
}

/* Map card styling */
.map-card {
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 4px 20px rgba(0,0,0,0.15);
}
.map-card iframe {
    width: 100%;
    height: 400px;
    border: 0;
}
</style>
</head>
<body>
<?php include 'includes/header.php'; ?>

<!-- Hero Carousel -->
<div id="heroCarousel" class="carousel slide carousel-fade" data-bs-ride="carousel" data-bs-interval="5000" data-aos="fade-in">
  <div class="carousel-indicators">
    <?php foreach ($banner_paths as $i => $path): ?>
      <button type="button" data-bs-target="#heroCarousel" data-bs-slide-to="<?= $i ?>" class="<?= $i===0?'active':'' ?>"></button>
    <?php endforeach; ?>
  </div>
  <div class="carousel-inner">
    <?php foreach ($banner_paths as $i => $path): ?>
      <div class="carousel-item <?= $i===0?'active':'' ?>">
        <img src="<?= h($path) ?>" class="d-block w-100" alt="Banner <?= $i+1 ?>" loading="lazy">
        <div class="carousel-caption d-none d-md-block">
          <h1 class="text-warning">Handcrafted Pickles at Avoji Foods</h1>
          <a class="btn btn-warning" href="products.php">Shop Now</a>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

  <button class="carousel-control-prev" type="button" data-bs-target="#heroCarousel" data-bs-slide="prev">
    <span class="carousel-control-prev-icon"></span>
  </button>
  <button class="carousel-control-next" type="button" data-bs-target="#heroCarousel" data-bs-slide="next">
    <span class="carousel-control-next-icon"></span>
  </button>
</div>

<!-- Featured Categories -->
<section class="container my-5">
  <h2 class="text-center">Featured Categories</h2>
  <div class="row g-4 mt-3">
    <?php foreach ($categories as $cat): ?>
      <div class="col-6 col-md-3" data-aos="fade-up">
        <div class="card category-card h-100 text-center shadow-lg border-0">
          <img src="<?= h($cat['image_path']) ?>" class="card-img-top" alt="<?= h($cat['name']) ?>" loading="lazy">
          <div class="card-body">
            <h5><?= h($cat['name']) ?></h5>
            <a href="products.php?category_id=<?= h($cat['id']) ?>" class="btn btn-success btn-sm">Browse</a>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</section>

<!-- Popular Products -->
<section class="container my-5">
  <h2 class="text-center">Popular Products</h2>
  <div class="row g-4 mt-3">
    <?php foreach ($products as $prod): ?>
      <div class="col-6 col-md-3 product-card" data-aos="zoom-in">
        <div class="card h-100 shadow-lg border-0">
          <img src="<?= h($prod['image_path']) ?>" class="card-img-top" alt="<?= h($prod['name']) ?>" loading="lazy">

          <div class="card-body text-center">
            <h5><?= h($prod['name']) ?></h5>
            <p class="text-success fw-bold">₹<?= number_format($prod['price'],2) ?></p>

            <?php if ($prod['is_cart_enabled']): ?>
              <a class="btn btn-success btn-sm" href="cart.php?add=<?= h($prod['id']) ?>">Add to Cart</a>
            <?php else: ?>
              <a class="btn btn-warning btn-sm" href="contact.php">Enquire</a>
            <?php endif; ?>

            <a class="btn btn-outline-primary btn-sm" href="product-detail.php?id=<?= h($prod['id']) ?>">View</a>
          </div>

        </div>
      </div>
    <?php endforeach; ?>
  </div>
</section>

<!-- About Us -->
<section id="about-us" class="container my-5 py-5">
  <div class="row align-items-center g-5">
    <div class="col-12 col-md-6" data-aos="fade-right">
      <img src="<?= h($about_image_path) ?>" class="img-fluid rounded shadow" alt="About PickleHub" loading="lazy">
    </div>

    <div class="col-12 col-md-6" data-aos="fade-left">
      <h2 class="mb-3">About Avoji Foods</h2>
      <p class="lead"><?= nl2br(h($about_text)) ?></p>
    </div>
  </div>
</section>

<!-- Dynamic Map -->
<?php if($map_iframe): ?>
<section id="map" class="my-5">
  <div class="container" data-aos="fade-up">
    <div class="card map-card mb-4">
      <div class="card-header">
        <h2 class="bi bi-map-fill me-2"> Find Us Here </h2>
      </div>
      <div class="card-body p-0">
        <?= $map_iframe ?>
      </div>
    </div>
  </div>
</section>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.1/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/aos@2.3.4/dist/aos.js"></script>

<script>
AOS.init({ duration: 1000, once: true });

document.querySelectorAll('a[href="#about-us"]').forEach(link => {
  link.addEventListener('click', e => {
    e.preventDefault();
    document.querySelector('#about-us').scrollIntoView({ behavior: 'smooth' });
  });
});
</script>

</body>
</html>
