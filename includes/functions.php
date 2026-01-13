<?php
// includes/functions.php

// Ensure session works across all paths (important for admin/public separation)
if (session_status() == PHP_SESSION_NONE) {
    ini_set('session.cookie_path', '/');
    session_start();
}

/**
 * Escape HTML safely
 */
function h($str) {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

/**
 * Check if user is logged in
 */
function is_logged_in() {
    return isset($_SESSION['user']) && !empty($_SESSION['user']['id']);
}

/**
 * Check if user is Admin or Super Admin (for admin dashboard)
 */
function is_admin() {
    if (!is_logged_in() || !in_array($_SESSION['user']['role'] ?? '', ['admin', 'super_admin'])) {
        header('Location: /picklehub_project/public/login.php');
        exit;
    }
}

/**
 * Restrict access to Super Admin only
 */
function is_super_admin() {
    if (!isset($_SESSION['user'])) {
        header('Location: /picklehub_project/public/login.php');
        exit;
    }

    if (($_SESSION['user']['role'] ?? '') !== 'super_admin') {
        http_response_code(403);
        echo '
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <title>Access Denied</title>
            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.1/dist/css/bootstrap.min.css" rel="stylesheet">
            <style>
                body {
                    background: #f8fafc;
                    display: flex;
                    justify-content: center;
                    align-items: center;
                    height: 100vh;
                    font-family: "Poppins", sans-serif;
                }
                .denied-card {
                    text-align: center;
                    background: #fff;
                    padding: 40px 50px;
                    border-radius: 15px;
                    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
                }
                h1 { color: #dc3545; font-size: 3rem; margin-bottom: 15px; }
                .btn-back {
                    background-color: #198754; color: white; border: none; padding: 8px 16px; border-radius: 6px;
                }
                .btn-back:hover { background-color: #157347; }
            </style>
        </head>
        <body>
            <div class="denied-card">
                <h1>🚫 Access Denied</h1>
                <p>You are not authorized to view this page.</p>
                <a href="/picklehub_project/admin/index.php" class="btn btn-back">Go Back</a>
            </div>
        </body>
        </html>';
        exit;
    }
}

/**
 * Generate CSRF token
 */
function csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF token
 */
function verify_csrf($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Detect device type (for analytics)
 */
function get_device_type() {
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    if (preg_match('/mobile|android|touch|webos|iphone/i', $ua)) return 'mobile';
    elseif (preg_match('/tablet|ipad/i', $ua)) return 'tablet';
    return 'desktop';
}

/**
 * Log a site visit
 */
function log_visit($pdo) {
    try {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $url = $_SERVER['REQUEST_URI'] ?? '';
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $device = get_device_type();

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM visits WHERE ip_address=? AND DATE(visited_at)=CURDATE()");
        $stmt->execute([$ip]);
        $is_new = ($stmt->fetchColumn() == 0) ? 1 : 0;

        $stmt = $pdo->prepare("INSERT INTO visits (ip_address,url,user_agent,device_type,is_new,visited_at)
                               VALUES (?,?,?,?,?,NOW())");
        $stmt->execute([$ip,$url,$ua,$device,$is_new]);
    } catch (Exception $e) { error_log("Visit Log Error: ".$e->getMessage()); }
}

/**
 * Get a setting value safely
 */
function get_setting($key, $pdo) {
    try {
        $stmt = $pdo->prepare("SELECT value FROM settings WHERE `key`=? LIMIT 1");
        $stmt->execute([$key]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row['value'] ?? null;
    } catch (PDOException $e) {
        error_log("Settings table missing: ".$e->getMessage());
        return null;
    }
}

/**
 * Flash message helper
 */
function flash($key, $msg = null) {
    if ($msg === null) {
        $m = $_SESSION['flash_'.$key] ?? null;
        unset($_SESSION['flash_'.$key]);
        return $m;
    } else {
        $_SESSION['flash_'.$key] = $msg;
    }
}

/**
 * Redirect helper
 */
function redirect($url) {
    header("Location: $url");
    exit;
}

/**
 * Get current logged-in user (fresh)
 */
function current_user() {
    global $pdo;
    if (empty($_SESSION['user']['id'])) return null;
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id=? LIMIT 1");
    $stmt->execute([$_SESSION['user']['id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($user) {
        $_SESSION['user'] = $user;
        return $user;
    }
    unset($_SESSION['user']);
    return null;
}

/**
 * ========================
 * 🧩 OTP & MFA FUNCTIONS
 * ========================
 */

/**
 * Generate random 6-digit OTP
 */
function generate_otp() {
    return rand(100000, 999999);
}

/**
 * Store OTP for a user
 */
function store_user_otp($pdo, $user_id, $otp, $purpose = 'login') {
    $expires_at = date('Y-m-d H:i:s', strtotime('+10 minutes'));
    $pdo->prepare("DELETE FROM user_otps WHERE user_id=? AND purpose=?")->execute([$user_id, $purpose]);
    $stmt = $pdo->prepare("INSERT INTO user_otps (user_id, otp_code, purpose, expires_at) VALUES (?, ?, ?, ?)");
    $stmt->execute([$user_id, $otp, $purpose, $expires_at]);
}

/**
 * Verify OTP
 */
function verify_user_otp($pdo, $user_id, $otp, $purpose = 'login') {
    $stmt = $pdo->prepare("SELECT * FROM user_otps 
                           WHERE user_id=? AND otp_code=? AND purpose=? 
                           AND expires_at > NOW() ORDER BY id DESC LIMIT 1");
    $stmt->execute([$user_id, $otp, $purpose]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $pdo->prepare("DELETE FROM user_otps WHERE user_id=? AND purpose=?")->execute([$user_id, $purpose]);
        return true;
    }
    return false;
}

/**
 * Send OTP email for registration verification (PHPMailer)
 */
require_once __DIR__ . '/mail.php';

function send_otp_email($email, $otp) {
    $subject = "PickleHub - Verify Your Account";
    $html = "
        <p>Hello,</p>
        <p>Your PickleHub verification code is:</p>
        <h2 style='color:#28a745;'>$otp</h2>
        <p>This code will expire in 10 minutes.</p>
        <p>Thank you,<br>PickleHub Team</p>
    ";
    return send_email($email, $subject, $html);
}

/**
 * Send OTP email for login MFA (PHPMailer)
 */
function send_login_otp_email($email, $otp) {
    $subject = "PickleHub - Login Verification Code";
    $html = "
        <p>Hello,</p>
        <p>Your login verification code is:</p>
        <h2 style='color:#20c997;'>$otp</h2>
        <p>This code will expire in 10 minutes.</p>
        <p>If you did not attempt to login, please ignore this email.</p>
    ";
    return send_email($email, $subject, $html);
}

/**
 * ========================
 * 🧾 ORDER FUNCTIONS
 * ========================
 */

function get_user_orders($user_id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE user_id=? ORDER BY created_at DESC");
    $stmt->execute([$user_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function get_order_items($order_id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM order_items WHERE order_id=?");
    $stmt->execute([$order_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function save_order($user_id, $name, $email, $address, $total_amount, $cart_items) {
    global $pdo;
    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("INSERT INTO orders (user_id, name, email, shipping_address, total_amount, status, created_at)
                               VALUES (?, ?, ?, ?, ?, 'pending', NOW())");
        $stmt->execute([$user_id, $name, $email, $address, $total_amount]);
        $order_id = $pdo->lastInsertId();

        $stmt_item = $pdo->prepare("INSERT INTO order_items (order_id, product_id, quantity, price)
                                    VALUES (?, ?, ?, ?)");
        foreach ($cart_items as $item) {
            $stmt_item->execute([$order_id, $item['product_id'], $item['quantity'], $item['price']]);
        }

        $pdo->commit();
        return $order_id;
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Order Save Error: ".$e->getMessage());
        return false;
    }
}

function update_order_status($order_id, $status) {
    global $pdo;
    $allowed = ['pending','processing','shipped','completed','cancelled'];
    if (!in_array($status, $allowed)) return false;
    try {
        $stmt = $pdo->prepare("UPDATE orders SET status=? WHERE id=?");
        $stmt->execute([$status, $order_id]);
        $pdo->prepare("INSERT INTO order_status_logs (order_id,status) VALUES (?,?)")->execute([$order_id,$status]);
        return true;
    } catch (Exception $e) {
        error_log("Status Update Error: ".$e->getMessage());
        return false;
    }
}

?>
