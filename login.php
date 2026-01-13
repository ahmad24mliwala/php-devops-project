<?php
// public/login.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

log_visit($pdo);

// Redirect if already logged in
if (is_logged_in()) {
    redirect('my-account.php');
}

$errors = [];

// ====================================
// 🧠 Handle Login Request
// ====================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) die('CSRF token mismatch');

    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (!$email || !$password) {
        $errors[] = "Email and password are required.";
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email=? LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user || !password_verify($password, $user['password'])) {
            $errors[] = "Invalid email or password.";
        } else {
            // ❗ Only potential issue — verify_otp.php must exist
            if ((int)$user['is_verified'] === 0) {
                $otp = generate_otp();
                store_user_otp($pdo, $user['id'], $otp, 'register');
                send_otp_email($user['email'], $otp);

                $_SESSION['pending_user_id'] = $user['id'];
                $_SESSION['pending_email'] = $user['email'];

                flash('warning', 'Your account is not verified. A new OTP has been sent to your email.');
                redirect('verify_otp.php');   // <-- Confirm filename!
            }

            // Verified → send login OTP
            $otp = generate_otp();
            store_user_otp($pdo, $user['id'], $otp, 'login');
            send_login_otp_email($user['email'], $otp);

            $_SESSION['pending_login_user_id'] = $user['id'];
            $_SESSION['pending_login_email'] = $user['email'];
            $_SESSION['pending_role'] = $user['role'];

            flash('info', 'A verification code has been sent to your email.');
            redirect('verify_login_otp.php');
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Login - Avoji Foods</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.1/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="assets/style.css" rel="stylesheet">
<style>
body { background-color: #f8fafc; font-family: 'Poppins', sans-serif; }
.card { border-radius: 15px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
.btn-success { background: linear-gradient(135deg,#28a745,#20c997); border:none; }
.btn-success:hover { background: linear-gradient(135deg,#218838,#17a589); }
a { text-decoration: none; }
</style>
</head>
<body>
<?php include __DIR__ . '/includes/header.php'; ?>

<div class="container my-5" style="max-width:480px;">
    <div class="card p-4">
        <h3 class="text-center mb-4 fw-bold text-success">Login to Avoji Foods</h3>

        <?php if ($flash = flash('success')): ?>
            <div class="alert alert-success"><?= h($flash) ?></div>
        <?php endif; ?>
        <?php if ($flash = flash('warning')): ?>
            <div class="alert alert-warning"><?= h($flash) ?></div>
        <?php endif; ?>
        <?php if ($flash = flash('info')): ?>
            <div class="alert alert-info"><?= h($flash) ?></div>
        <?php endif; ?>
        <?php if ($errors): ?>
            <div class="alert alert-danger">
                <?php foreach ($errors as $e) echo '<p>'.h($e).'</p>'; ?>
            </div>
        <?php endif; ?>

        <form method="POST" class="mt-2">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

            <div class="mb-3">
                <label class="form-label">Email Address</label>
                <input type="email" class="form-control" name="email" value="<?= h($_POST['email'] ?? '') ?>" required>
            </div>

            <div class="mb-3">
                <label class="form-label">Password</label>
                <input type="password" class="form-control" name="password" required>
            </div>

            <button class="btn btn-success w-100 py-2">Login</button>

            <p class="mt-3 text-center">
                Don’t have an account? <a href="register.php" class="text-success fw-bold">Register here</a>
            </p>
        </form>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
