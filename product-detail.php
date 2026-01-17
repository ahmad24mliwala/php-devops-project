<?php
// public/product-detail.php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
//require_once __DIR__ . '/includes/track_visit.php'; 

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

//log_visit($pdo);

// ----------------------------------------------
// GET WHATSAPP NUMBER FROM SETTINGS
// ----------------------------------------------
function get_whatsapp_number_from_settings($pdo) {
    if (function_exists('get_setting')) {
        $n = get_setting('whatsapp_number', $pdo);
        if ($n) return $n;
    }
    try {
        $stmt = $pdo->prepare("SELECT `value` FROM settings WHERE `key`='whatsapp_number' LIMIT 1");
        $stmt->execute();
        return $stmt->fetchColumn() ?: '';
    } catch (Exception $e) {
        return '';
    }
}

$whatsapp_raw = get_whatsapp_number_from_settings($pdo);
$whatsapp = preg_replace('/[^0-9+]/', '', $whatsapp_raw);
if ($whatsapp && !preg_match('/^\+/', $whatsapp)) {
    $whatsapp = '+91' . $whatsapp;
}

// ----------------------------------------------
// FETCH PRODUCT
// ----------------------------------------------
// ----------------------------------------------
// FETCH PRODUCT (SEO FRIENDLY - BY SLUG)
// ----------------------------------------------
$slug = trim($_GET['slug'] ?? '');

if ($slug === '') {
    header("Location: products.php");
    exit;
}

$stmt = $pdo->prepare("
    SELECT p.*, c.name AS category_name
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    WHERE p.slug = ?
    LIMIT 1
");
$stmt->execute([$slug]);
$product = $stmt->fetch();

if (!$product) {
    header("Location: products.php");
    exit;
}


// ----------------------------------------------
// IMAGE PATH FIX
// ----------------------------------------------
$image = $product['image'] ?: "product_placeholder.jpg";
$image = preg_replace('#^uploads/#i', '', $image);
$imageFile = trim($image);

$mainImageUrl = "image.php?file=" . urlencode($imageFile);

// Absolute OG
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host   = $_SERVER['HTTP_HOST'];

$ogImage = $scheme . "://" . $host . "/" . $mainImageUrl;

// ----------------------------------------------
// SEO
// ----------------------------------------------
$seoTitle = h($product['name']) . " | Avoji Foods";
$seoDesc  = substr(strip_tags($product['description']), 0, 150) . "...";

$canonical = $scheme . "://" . $host . "/product/" . $product['slug'];


// ----------------------------------------------
// RELATED PRODUCTS
// ----------------------------------------------
$related = [];
if (!empty($product['category_id'])) {
    $stmt = $pdo->prepare("
    SELECT id, slug, name, price, image, price_enabled
    FROM products
    WHERE category_id = ? AND id <> ?
    ORDER BY created_at DESC LIMIT 4
");

    $stmt->execute([$product['category_id'], $product['id']]);
    $related = $stmt->fetchAll();
}

// ----------------------------------------------
// ADD TO CART (BACKEND)
// ----------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_to_cart'])) {

    // 🔒 Force login
    if (!isset($_SESSION['user']['id'])) {
        header("Location: login.php?redirect=product/" . urlencode($product['slug']));
        exit;
    }

    // 🛡 CSRF check
    if (!verify_csrf($_POST['csrf_token'])) {
        die("❌ Invalid CSRF token");
    }

    // ❌ Cart disabled
    if (!$product['is_cart_enabled']) {
        $_SESSION['flash_error'] = "Not available for online purchase.";
        header("Location: /product/" . urlencode($product['slug']));
        exit;
    }

    // ✅ Quantity
    $qty = max(1, (int)($_POST['qty'] ?? 1));

    // ✅ Product ID
    $productId = (int)$product['id'];

    // ✅ Init cart
    if (!isset($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }

    // ✅ Add / Update cart
    if (!isset($_SESSION['cart'][$productId])) {
        $_SESSION['cart'][$productId] = [
            "name"  => $product['name'],
            "price" => $product['price'],
            "qty"   => $qty,
            "image" => $product['image']
        ];
    } else {
        $_SESSION['cart'][$productId]['qty'] += $qty;
    }

    // 🚀 Redirect to cart
    header("Location: cart.php");
    exit;
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">

<title><?= $seoTitle ?></title>
<meta name="description" content="<?= h($seoDesc) ?>">
<link rel="canonical" href="<?= h($canonical) ?>">

<meta property="og:title" content="<?= h($seoTitle) ?>">
<meta property="og:description" content="<?= h($seoDesc) ?>">
<meta property="og:image" content="<?= h($ogImage) ?>">
<meta property="og:type" content="product">

<meta name="viewport" content="width=device-width,initial-scale=1">

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.1/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/aos@2.3.4/dist/aos.css" rel="stylesheet">

<style>
.product-img-wrap {
    width: 100%;
    background: #fff;
    border-radius: 12px;
    padding: 10px;
    overflow: hidden;
    text-align: center;
    border: 1px solid #eee;
}
.product-img {
    width: 100%;
    max-height: 430px;
    object-fit: contain;
    transition: transform .4s ease, opacity .4s ease;
    opacity: 0;
}
.product-img.loaded { opacity: 1; }
.product-img:hover { transform: scale(1.06); }
@media(max-width:768px){
  .product-img{ max-height:300px; }
  h1{ font-size:1.4rem; }
}
.related-card img {
    width: 100%;
    height: 160px;
    object-fit: contain;
    background: #fafafa;
    padding: 8px;
    border-radius: 6px;
}
.view-more-btn {
    display: block;
    margin: 20px auto 0;
    max-width: 220px;
}
</style>
</head>

<body>

<?php include __DIR__ . '/includes/header.php'; ?>

<div class="container my-4">

    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb">
      <ol class="breadcrumb small">
        <li class="breadcrumb-item"><a href="index.php">Home</a></li>
        <li class="breadcrumb-item"><a href="products.php">Products</a></li>
        <?php if ($product['category_name']): ?>
        <li class="breadcrumb-item">
            <a href="products.php?category_id=<?= $product['category_id'] ?>">
                <?= h($product['category_name']) ?>
            </a>
        </li>
        <?php endif; ?>
        <li class="breadcrumb-item active" aria-current="page">
  <?= h($product['name']) ?>
</li>
      </ol>
    </nav>

    <div class="row g-4">

        <!-- PRODUCT IMAGE -->
        <div class="col-md-6" data-aos="fade-right">
            <div class="product-img-wrap shadow-sm">
                <img src="<?= h($mainImageUrl) ?>" 
                     class="product-img" 
                     alt="<?= h($product['name']) ?>"
                     onload="this.classList.add('loaded')">
            </div>
        </div>

        <!-- PRODUCT DETAILS -->
        <div class="col-md-6" data-aos="fade-left">

            <h1 class="fw-bold"><?= h($product['name']) ?></h1>

            <p class="text-muted mb-1">Category: <strong><?= h($product['category_name']) ?></strong></p>

            <?php if ($product['weight']): ?>
            <p class="text-muted mb-1">Weight: <strong><?= h($product['weight']) ?></strong></p>
            <?php endif; ?>

            <?php if ($product['price_enabled']): ?>
            <p class="text-success fw-bold fs-3">₹<?= number_format($product['price'], 2) ?></p>
            <?php else: ?>
            <p class="text-danger fw-bold">Price available on enquiry</p>
            <?php endif; ?>

            <p class="mt-3"><?= nl2br(h($product['description'])) ?></p>

            <!-- ADD TO CART -->
            <?php if ($product['is_cart_enabled']): ?>

                <?php if (!isset($_SESSION['user']['id'])): ?>
                    <!-- 🔒 Guest user must login -->
                    <a href="login.php?redirect=product/<?= urlencode($product['slug']) ?>"

                       class="btn btn-warning w-100 mt-3">
                       🔒 Login to Add to Cart
                    </a>

                <?php else: ?>
                    <!-- Logged-in user -->
                    <form method="POST" class="d-flex gap-2 mt-3" style="max-width:300px;">
                        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                        <input type="hidden" name="add_to_cart" value="1">
                        <input type="number" name="qty" min="1" value="1" class="form-control" style="width:90px;">
                        <button class="btn btn-success" type="submit">🛒 Add to Cart</button>
                    </form>
                <?php endif; ?>

            <?php endif; ?>

            <!-- WHATSAPP -->
            <?php 
            $waMsg = "Hello Avoji Foods, I want to enquire about *{$product['name']}*.\n" . $canonical;
            $waLink = "https://wa.me/" . rawurlencode($whatsapp) . "?text=" . rawurlencode($waMsg);
            ?>
            <a href="<?= h($waLink) ?>" target="_blank" class="btn btn-success w-100 mt-3">
                💬 WhatsApp Enquiry
            </a>

        </div>

    </div>

    <!-- RELATED PRODUCTS -->
    <?php if($related): ?>
    <hr class="my-5">
    <h4 class="mb-3">Related Products</h4>

    <div class="row g-3">
        <?php foreach($related as $r): 
            $rImg = "image.php?file=" . urlencode(basename($r['image']));
        ?>
        <div class="col-6 col-md-3">
            <div class="card related-card shadow-sm border-0 h-100">
                <img src="<?= h($rImg) ?>" alt="<?= h($r['name']) ?>">
                <div class="card-body text-center">
                    <h6><?= h($r['name']) ?></h6>
                    <?php if($r['price_enabled']): ?>
                    <p class="text-success mb-1">₹<?= number_format($r['price'],2) ?></p>
                    <?php else: ?>
                    <p class="text-muted mb-1">Price on request</p>
                    <?php endif; ?>
              <?php
$detailLink = !empty($r['slug'])
    ? 'product-detail.php?slug=' . urlencode($r['slug'])
    : 'product-detail.php?id=' . (int)$r['id'];
?>

<a href="<?= h($detailLink) ?>"
   class="btn btn-outline-primary btn-sm w-100">
   View
</a>


                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <a href="products.php" class="btn btn-primary view-more-btn">View More Products</a>

    <?php endif; ?>

</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.1/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/aos@2.3.4/dist/aos.js"></script>
<script>AOS.init({duration:700});</script>

</body>
</html>
