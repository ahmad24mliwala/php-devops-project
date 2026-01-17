<?php
// public/visit_logger.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../includes/db.php';

/* ===============================
   BASIC REQUEST INFO
================================ */
$ip_address = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$user_agent = substr($_SERVER['HTTP_USER_AGENT'] ?? 'unknown', 0, 190);

/* normalize home page */
$uri = $_SERVER['REQUEST_URI'] ?? '/';
$url = strtok($uri, '?');
if ($url === '' || $url === '/index.php') {
    $url = '/';
}

/* ===============================
   SESSION (UNIQUE VISITOR)
================================ */
if (empty($_SESSION['v_sid'])) {
    $_SESSION['v_sid'] = bin2hex(random_bytes(16));
    $_SESSION['v_new'] = 1;
} else {
    $_SESSION['v_new'] = 0;
}
$session_id = $_SESSION['v_sid'];

/* ===============================
   LOGGED IN USER
================================ */
$user_id = $_SESSION['user']['id'] ?? null;

/* ===============================
   DEVICE TYPE
================================ */
$ua = strtolower($user_agent);
$device_type = 'desktop';
if (preg_match('/mobile|iphone|android/', $ua)) $device_type = 'mobile';
elseif (preg_match('/ipad|tablet/', $ua)) $device_type = 'tablet';

/* ===============================
   PAGE TYPE
================================ */
$page_type = 'other';
if ($url === '/') $page_type = 'home';
elseif (str_contains($url, 'product')) $page_type = 'product';
elseif (str_contains($url, 'cart')) $page_type = 'cart';
elseif (str_contains($url, 'checkout')) $page_type = 'checkout';

/* ===============================
   DUPLICATE PROTECTION
================================ */
$chk = $pdo->prepare("
    SELECT id FROM visits
    WHERE session_id = ?
      AND url = ?
      AND visited_at >= NOW() - INTERVAL 1 DAY
    LIMIT 1
");
$chk->execute([$session_id, $url]);

if (!$chk->fetch()) {
    $stmt = $pdo->prepare("
        INSERT INTO visits
        (ip_address, session_id, user_id, url, page_type, user_agent, device_type, is_new)
        VALUES (?,?,?,?,?,?,?,?)
    ");
    $stmt->execute([
        $ip_address,
        $session_id,
        $user_id,
        $url,
        $page_type,
        $user_agent,
        $device_type,
        $_SESSION['v_new']
    ]);
}
