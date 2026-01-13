<?php
// public/verify_login_otp.php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$user_id = $_SESSION['pending_login_user_id'] ?? null;
$email   = $_SESSION['pending_login_email'] ?? null;
$role    = $_SESSION['pending_role'] ?? 'customer';

if (!$user_id || !$email) {
    flash('error', 'No pending login verification found.');
    redirect('login.php');
}

$errors = [];

// Handle OTP submit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $errors[] = "Security validation failed. Try again.";
    } else {

        $otp = trim($_POST['otp'] ?? '');

        if (!$otp) {
            $errors[] = "Please enter the OTP code.";
        } else {

            if (verify_user_otp($pdo, $user_id, $otp, 'login')) {

                // Fetch user details
                $stmt = $pdo->prepare("SELECT * FROM users WHERE id=? LIMIT 1");
                $stmt->execute([$user_id]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$user) {
                    flash('error', 'Account not found.');
                    redirect('login.php');
                }

                // Login user
                $_SESSION['user'] = [
                    'id'    => $user['id'],
                    'name'  => $user['name'],
                    'email' => $user['email'],
                    'role'  => $user['role']
                ];

                // Cleanup temp session
                unset($_SESSION['pending_login_user_id'], $_SESSION['pending_login_email'], $_SESSION['pending_role']);

                flash('success', 'Login successful!');

                // Redirect by role (FIXED)
                if (in_array($role, ['admin', 'super_admin'])) {
                    redirect('admin/index.php'); // ← ONLY CHANGE
                } else {
                    redirect('my-account.php');
                }

            } else {
                $errors[] = "Invalid or expired OTP. Please try again.";
            }
        }
    }
}

// Resend OTP
if (isset($_GET['resend'])) {

    // Ensure user still exists
    $stmt = $pdo->prepare("SELECT email FROM users WHERE id=? LIMIT 1");
    $stmt->execute([$user_id]);
    if (!$stmt->fetchColumn()) {
        flash('error', 'Your account was not found.');
        redirect('login.php');
    }

    $otp = generate_otp();
    store_user_otp($pdo, $user_id, $otp, 'login');
    send_login_otp_email($email, $otp);

    flash('info', 'A new login code has been sent to your email.');
    redirect('verify_login_otp.php');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Login Verification - PickleHub</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.1/dist/css/bootstrap.min.css" rel="stylesheet">

<style>
/* your CSS remains unchanged */
</style>
</head>

<body>

<div class="verify-card">

    <h3 class="text-center mb-2">Login Verification</h3>
    <p class="text-center">Enter the 6-digit code sent to <strong><?= h($email) ?></strong></p>

    <?php if ($msg = flash('info')): ?>
        <div class="alert alert-info text-center"><?= h($msg) ?></div>
    <?php endif; ?>

    <?php if ($errors): ?>
        <div class="alert alert-danger">
            <?php foreach ($errors as $e): ?>
                <p class="mb-0"><?= h($e) ?></p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= csrf_token(); ?>">

        <input type="text"
               name="otp"
               maxlength="6"
               class="form-control otp-input mb-3"
               placeholder="••••••"
               inputmode="numeric"
               required>

        <button class="btn btn-success w-100">Verify & Login</button>
    </form>

    <a href="?resend=1" class="resend-link text-center">Resend Code</a>
</div>

</body>
</html>
