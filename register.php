<?php
// public/register.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/mail.php';

if (session_status() === PHP_SESSION_NONE) session_start();

log_visit($pdo);

// If logged in
if (is_logged_in()) {
    flash('info', 'You are already logged in.');
    redirect('my-account.php');
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid session token.';
    }

    $name     = trim($_POST['name'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';

    // Validation
    if (!$name || !$email || !$password || !$confirm) $errors[] = 'All fields are required.';
    if (strlen($name) < 2) $errors[] = "Full name must be at least 2 characters.";
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Invalid email format.';
    if ($password !== $confirm) $errors[] = 'Passwords do not match.';
    if (strlen($password) < 6) $errors[] = 'Password must be at least 6 characters.';

    // Check email already exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email=? LIMIT 1");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        $errors[] = 'Email already registered. Please log in.';
    }

    // If valid → Create account
    if (!$errors) {
        $hash = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $pdo->prepare("
            INSERT INTO users (name,email,password,role,is_verified,created_at)
            VALUES (?,?,?,'customer',0,NOW())
        ");
        $stmt->execute([$name, $email, $hash]);

        $user_id = $pdo->lastInsertId();

        // Create OTP
        $otp = generate_otp();

        // Store in DB
        store_user_otp($pdo, $user_id, $otp, 'register');

        // Send email
        $emailStatus = send_otp_email($email, $otp);

        if (!$emailStatus) {
            $errors[] = "Account created but OTP email could not be sent. Contact support.";
        } else {
            $_SESSION['pending_user_id'] = $user_id;
            $_SESSION['pending_email'] = $email;

            flash('success', 'Account created! Check your email for the OTP verification code.');
            redirect('verify_otp.php');
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Register - Avoji Foods</title>
<meta name="viewport" content="width=device-width, initial-scale=1">

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.1/dist/css/bootstrap.min.css" rel="stylesheet">

<style>
body {
    background: #f3fff3;
    font-family: "Poppins", sans-serif;
}
.register-card {
    max-width: 500px;
    margin: 40px auto;
    background: #ffffffee;
    backdrop-filter: blur(6px);
    padding: 30px;
    border-radius: 16px;
    box-shadow: 0 8px 20px rgba(0,0,0,0.12);
}
</style>
</head>

<body>

<?php include __DIR__ . '/includes/header.php'; ?>

<div class="register-card">

    <h2 class="text-center mb-4 text-success fw-bold">Create Account</h2>

    <?php if ($msg = flash('success')): ?>
        <div class="alert alert-success"><?= h($msg) ?></div>
    <?php endif; ?>

    <?php if ($errors): ?>
        <div class="alert alert-danger">
            <?php foreach ($errors as $e): ?>
                <p><?= h($e) ?></p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

        <div class="mb-3">
            <label class="form-label fw-semibold">Full Name</label>
            <input class="form-control" name="name" value="<?= h($_POST['name'] ?? '') ?>" required>
        </div>

        <div class="mb-3">
            <label class="form-label fw-semibold">Email Address</label>
            <input class="form-control" type="email" name="email" value="<?= h($_POST['email'] ?? '') ?>" required>
        </div>

        <div class="mb-3">
            <label class="form-label fw-semibold">Password</label>
            <input class="form-control" type="password" name="password" required>
        </div>

        <div class="mb-3">
            <label class="form-label fw-semibold">Confirm Password</label>
            <input class="form-control" type="password" name="confirm_password" required>
        </div>

        <button class="btn btn-success w-100 py-2">Register</button>
    </form>

    <p class="mt-3 text-center">
        Already have an account?
        <a href="login.php" class="text-success fw-bold">Login here</a>
    </p>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
