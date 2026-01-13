<?php
// public/products.php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


// Log visit
log_visit($pdo);

/* -------------------------
   Normalize image field
---------------------------*/
function normalize_image_field($val) {
    if (!$val) return '';
    $val = trim($val);
    $val = preg_replace('#^/+|/+$#', '', $val);
    if (stripos($val, 'uploads/') === 0) {
        $val = substr($val, strlen('uploads/'));
    }
    return $val;
}

/* -------------------------
   Get WhatsApp Number
---------------------------*/
function get_whatsapp_number($pdo) {
    try {
        if (function_exists('get_setting')) {
            $n = get_setting('whatsapp_number', $pdo);
            if ($n) return $n;
        }
        $s = $pdo->query("SELECT value FROM settings WHERE `key`='whatsapp_number' LIMIT 1");
        return $s->fetchColumn() ?: '';
    } catch (Exception $e) { return ''; }
}

$wa = get_whatsapp_number($pdo);
$waSanitized = preg_replace('/[^0-9+]/', '', $wa);
if ($waSanitized && $waSanitized[0] !== '+') $waSanitized = '+91' . $waSanitized;

/* -------------------------
   Add to Cart
---------------------------*/
if (isset($_GET['action']) && $_GET['action'] === 'add' && isset($_GET['id'])) {
    $addId = (int)$_GET['id'];
    if ($addId > 0) {
        $stmt = $pdo->prepare("SELECT id,name,price,image,is_cart_enabled FROM products WHERE id=? LIMIT 1");
        $stmt->execute([$addId]);
        $prod = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($prod && $prod['is_cart_enabled']) {
            if (!isset($_SESSION['cart'])) $_SESSION['cart'] = [];
            if (!isset($_SESSION['cart'][$addId])) {
                $_SESSION['cart'][$addId] = [
                    'name' => $prod['name'],
                    'price' => $prod['price'],
                    'qty' => 1,
                    'image' => $prod['image']
                ];
            } else {
                $_SESSION['cart'][$addId]['qty'] += 1;
            }
        }
    }
    header('Location: cart.php');
    exit;
}

/* -------------------------
   Filters
---------------------------*/
$category_id = $_GET['category_id'] ?? '';
$search      = trim($_GET['search'] ?? '');
$page        = max(1, (int)($_GET['page'] ?? 1));
$limit       = 12;
$offset      = ($page - 1) * $limit;

$whereSQL = " WHERE 1 ";
$params = [];

if ($category_id !== '') {
    $category_id = (int)$category_id;
    if ($category_id > 0) {
        $whereSQL .= " AND category_id = :cat ";
        $params[':cat'] = $category_id;
    }
}

if ($search !== '') {
    $whereSQL .= " AND name LIKE :search ";
    $params[':search'] = "%$search%";
}

/* -------------------------
   Count
---------------------------*/
$countSQL = "SELECT COUNT(*) FROM products $whereSQL";
$stmt = $pdo->prepare($countSQL);
$stmt->execute($params);
$total = (int)$stmt->fetchColumn();
$pages = max(1, (int)ceil($total / $limit));

/* -------------------------
   Fetch Products
---------------------------*/
$sql = "SELECT id, slug, name, price, image, is_cart_enabled, price_enabled, weight
        FROM products
        $whereSQL
        ORDER BY created_at DESC
        LIMIT :limit OFFSET :offset";

$stmt = $pdo->prepare($sql);

foreach ($params as $k => $v) $stmt->bindValue($k, $v);
$stmt->bindValue(':limit',  (int)$limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);

$stmt->execute();
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* -------------------------
   Image Handling
---------------------------*/
foreach ($products as &$p) {
    $img = normalize_image_field($p['image']);
    $p['image_path'] = $img ? "image.php?file=" . urlencode($img) : "image.php?file=product_placeholder.jpg";
}
unset($p);

/* -------------------------
   Categories
---------------------------*/
$categories = $pdo->query("SELECT id,name FROM categories ORDER BY name ASC")->fetchAll();

/* FIXED BASE URL FOR CLEAN URL SETUP */
$baseURL = '';  // <--- FIXED

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Products - Avoji Foods</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.1/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/aos@2.3.4/dist/aos.css" rel="stylesheet">

<style>
.product-card img {
    width:100%; height:220px;
    object-fit:contain; background:#fff;
    border-radius:12px; padding:10px;
    border:1px solid #eee;
    opacity:0; transition:opacity .4s, transform .3s;
}
.product-card img.loaded { opacity:1; }
.product-card img:hover { transform:scale(1.06); }
.product-card { transition:transform .25s; }
.product-card:hover { transform:translateY(-6px); }
@media(max-width:768px){ .product-card img{height:180px;} }
@media(max-width:576px){ .product-card img{height:160px;} }
.card-price { color:#198754; font-weight:700; }
.card-weight { font-size:.9rem; color:#666; }
.pagination .page-link { color:#198754; }
.pagination .active .page-link {
    background:#198754; border-color:#198754; color:white;
}
</style>
</head>

<body>

<?php include __DIR__ . '/includes/header.php'; ?>

<div class="container my-5">

<h2 class="text-center mb-4">Products</h2>

<!-- FILTERS -->
<form class="row g-2 mb-4 justify-content-center" method="GET">
    <div class="col-md-4 col-12">
        <select name="category_id" class="form-select">
            <option value="">All Categories</option>
            <?php foreach ($categories as $c): ?>
                <option value="<?=h($c['id'])?>" <?=($category_id==$c['id'])?'selected':''?>>
                    <?=h($c['name'])?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="col-md-5 col-12">
        <input type="text" name="search" class="form-control"
               value="<?=h($search)?>" placeholder="Search products...">
    </div>

    <div class="col-md-3 col-12">
        <button class="btn btn-success w-100">Filter</button>
    </div>
</form>

<!-- PRODUCT GRID -->
<div class="row g-4">
<?php if ($products): foreach ($products as $p): ?>

    <?php
    $waText = "Hello Avoji Foods, I am interested in *".$p['name']."*.";
    $waLink = $waSanitized
        ? "https://wa.me/".rawurlencode($waSanitized)."?text=".rawurlencode($waText)
        : "contact.php?product=".urlencode($p['name']);

    /* FIXED VIEW URL — uses id instead of slug */
    $detailLink = $baseURL . '/product-detail.php?id=' . $p['id'];
    ?>

    <div class="col-6 col-md-3 product-card" data-aos="zoom-in">
        <div class="card border-0 shadow-sm h-100">

            <img src="<?=$p['image_path']?>"
                 onload="this.classList.add('loaded')"
                 alt="<?=h($p['name'])?>">

            <div class="card-body text-center d-flex flex-column">

                <h5><?=h($p['name'])?></h5>

                <?php if ($p['price_enabled']): ?>
                    <p class="card-price">₹<?=number_format($p['price'],2)?></p>
                <?php else: ?>
                    <p class="text-muted">Price on request</p>
                <?php endif; ?>

                <?php if ($p['weight']): ?>
                    <p class="card-weight"><?=h($p['weight'])?></p>
                <?php endif; ?>

                <?php if ($p['is_cart_enabled']): ?>
                    <a class="btn btn-success btn-sm mb-1 w-100"
                       href="<?=$baseURL?>/products.php?action=add&id=<?=$p['id']?>">Add to Cart</a>
                <?php else: ?>
                    <a class="btn btn-warning btn-sm mb-1 w-100"
                       href="<?=$waLink?>" target="_blank">Enquire</a>
                <?php endif; ?>

                <a class="btn btn-outline-primary btn-sm mb-1 w-100"
                   href="<?=$detailLink?>">View</a>

                <a class="btn btn-success btn-sm w-100"
                   href="<?=$waLink?>" target="_blank">WhatsApp</a>
            </div>
        </div>
    </div>

<?php endforeach; else: ?>

    <div class="col-12">
        <div class="alert alert-info text-center">No products found.</div>
    </div>

<?php endif; ?>
</div>

<!-- PAGINATION -->
<?php if ($pages > 1): ?>
<nav class="mt-4">
    <ul class="pagination justify-content-center flex-wrap">
        <?php for ($i=1; $i <= $pages; $i++): ?>
            <li class="page-item <?=($i==$page) ? 'active' : ''?>">
                <a class="page-link"
                   href="?<?= http_build_query(['category_id'=>$category_id,'search'=>$search,'page'=>$i]) ?>">
                   <?=$i?>
                </a>
            </li>
        <?php endfor; ?>
    </ul>
</nav>
<?php endif; ?>

</div>

<?php include __DIR__ . '/includes/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.1/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/aos@2.3.4/dist/aos.js"></script>
<script>AOS.init({ duration:700 });</script>

</body>
</html>
