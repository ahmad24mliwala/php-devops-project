<?php
// includes/track_visit.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/db.php';

/* =====================
   BASIC REQUEST INFO
===================== */
$ip_address = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$user_agent = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);

/* =====================
   URL NORMALIZATION
===================== */
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$path = rtrim($path, '/');
$url  = ($path === '' || $path === '/index.php') ? '/' : substr($path, 0, 255);

/* =====================
   VISITOR ID (STABLE)
===================== */
if (empty($_SESSION['visitor_id'])) {
    $_SESSION['visitor_id'] = bin2hex(random_bytes(16));
}
$visitor_id = $_SESSION['visitor_id'];

/* =====================
   USER (OPTIONAL)
===================== */
$user_id = $_SESSION['user']['id'] ?? null;

/* =====================
   DEVICE TYPE
===================== */
$ua = strtolower($user_agent);
$device_type =
    (strpos($ua, 'mobile') !== false || strpos($ua, 'iphone') !== false || strpos($ua, 'android') !== false)
        ? 'mobile'
        : ((strpos($ua, 'ipad') !== false || strpos($ua, 'tablet') !== false) ? 'tablet' : 'desktop');

/* =====================
   PAGE TYPE + PRODUCT
===================== */
$page_type  = 'other';
$product_id = null;

if ($url === '/') {
    $page_type = 'home';

} elseif (strpos($url, 'product') !== false) {
    $page_type = 'product';

    if (!empty($_GET['id']) && is_numeric($_GET['id'])) {
        $product_id = (int)$_GET['id'];
    }

} elseif (strpos($url, 'category') !== false) {
    $page_type = 'category';

} elseif (strpos($url, 'cart') !== false) {
    $page_type = 'cart';

} elseif (strpos($url, 'checkout') !== false) {
    $page_type = 'checkout';
}

/* =====================
   NEW VISITOR (FIRST EVER)
===================== */
$is_new = 0;
$chk = $pdo->prepare("
    SELECT 1 FROM visits WHERE visitor_id = ? LIMIT 1
");
$chk->execute([$visitor_id]);
if (!$chk->fetch()) {
    $is_new = 1;
}

/* =====================
   DUPLICATE PROTECTION
   (SAME PAGE / SAME SESSION / 30 MIN)
===================== */
$dup = $pdo->prepare("
    SELECT 1 FROM visits
    WHERE visitor_id = ?
      AND url = ?
      AND visited_at >= NOW() - INTERVAL 30 MINUTE
    LIMIT 1
");
$dup->execute([$visitor_id, $url]);

/* =====================
   INSERT VISIT
===================== */
if (!$dup->fetch()) {
    $stmt = $pdo->prepare("
        INSERT INTO visits
        (visitor_id, ip_address, user_id, url, page_type,
         product_id, user_agent, device_type, is_new, visited_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");

    $stmt->execute([
        $visitor_id,
        $ip_address,
        $user_id,
        $url,
        $page_type,
        $product_id,
        $user_agent,
        $device_type,
        $is_new
    ]);
}
