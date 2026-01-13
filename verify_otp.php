<?php
// public/verify_otp.php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$user_id = $_SESSION['pending_user_id'] ?? null;
$email   = $_SESSION['pending_email'] ?? null;

if (!$user_id || !$email) {
    flash('error', 'No pending verification found.');
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
            $errors[] = "Please enter the OTP.";
        } else {

            if (verify_user_otp($pdo, $user_id, $otp, 'register')) {

                // Check user still exists
                $stmt = $pdo->prepare("SELECT * FROM users WHERE id=? LIMIT 1");
                $stmt->execute([$user_id]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$user) {
                    flash('error', 'Account not found.');
                    redirect('login.php');
                }

                // Mark user verified
                $pdo->prepare("UPDATE users SET is_verified=1 WHERE id=?")
                    ->execute([$user_id]);

                // Auto-login
                $_SESSION['user'] = [
                    'id'    => $user['id'],
                    'name'  => $user['name'],
                    'email' => $user['email'],
                    'role'  => $user['role']
                ];

                unset($_SESSION['pending_user_id'], $_SESSION['pending_email']);

                flash('success', 'Account Verified Successfully!');
                redirect('my-account.php');

            } else {
                $errors[] = "Invalid or expired OTP. Please try again.";
            }
        }
    }
}

// Handle resend
if (isset($_GET['resend'])) {

    // Ensure user still exists
    $stmt = $pdo->prepare("SELECT email FROM users WHERE id=? LIMIT 1");
    $stmt->execute([$user_id]);

    if (!$stmt->fetchColumn()) {
        flash('error', 'Account not found.');
        redirect('login.php');
    }

    $otp = generate_otp();
    store_user_otp($pdo, $user_id, $otp, 'register');
    send_otp_email($email, $otp);

    flash('info', 'A new verification code has been emailed to you.');
    redirect('verify_otp.php');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Verify Email - PickleHub</title>
<meta name="viewport" content="width=device-width, initial-scale=1">

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.1/dist/css/bootstrap.min.css" rel="stylesheet">

<style>
body {
    background: linear-gradient(135deg, #e8fff3, #c8f7e0);
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
    font-family: "Poppins", sans-serif;
}

.verify-card {
    width: 100%;
    max-width: 420px;
    background: #ffffffdd;
    backdrop-filter: blur(10px);
    padding: 32px 25px;
    border-radius: 18px;
    box-shadow: 0 8px 26px rgba(0,0,0,0.12);
    animation: fadeIn 0.6s ease;
}

@keyframes fadeIn {
    from {opacity: 0; transform: translateY(20px);}
    to   {opacity: 1; transform: translateY(0);}
}

h3 {
    color: #198754;
    font-weight: 700;
}

.otp-input {
    padding: 14px;
    font-size: 1.25rem;
    border-radius: 12px;
    text-align: center;
    letter-spacing: 4px;
}

.btn-success {
    background: linear-gradient(135deg, #28a745, #20c997);
    border: none;
    padding: 12px;
    border-radius: 12px;
    font-weight: 600;
    font-size: 1.1rem;
}
.btn-success:hover {
    background: linear-gradient(135deg, #1f8b3d, #17a581);
}

.resend-link {
    display: block;
    font-weight: 600;
    margin-top: 10px;
    color: #198754;
    text-decoration: none;
}
.resend-link:hover {
    color: #145e32;
}
</style>

</head>
<body>

<div class="verify-card text-center">

    <h3 class="mb-2">Verify Your Email</h3>
    <p>Enter the 6-digit code sent to <strong><?= h($email) ?></strong></p>

    <?php if ($msg = flash('info')): ?>
        <div class="alert alert-info"><?= h($msg) ?></div>
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
               inputmode="numeric"
               placeholder="••••••"
               autofocus
               required>

        <button class="btn btn-success w-100">Verify</button>
    </form>

    <a href="?resend=1" class="resend-link">Resend Verification Code</a>

</div>

</body>
</html>
