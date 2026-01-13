<?php
// admin/super_admin_setup.php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
if (session_status() == PHP_SESSION_NONE) session_start();

// Check if a super admin already exists
$super_admin_exists = $pdo->query("SELECT id FROM users WHERE role='super_admin' LIMIT 1")->fetch();

if ($super_admin_exists) {
    http_response_code(403);
    echo "
    <html><head><title>Access Denied</title>
    <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.1/dist/css/bootstrap.min.css' rel='stylesheet'>
    </head><body class='bg-light d-flex justify-content-center align-items-center' style='height:100vh;'>
      <div class='card shadow p-4 text-center' style='max-width:400px;'>
        <h4 class='text-danger mb-3'>🚫 Setup Locked</h4>
        <p>A Super Admin already exists. To add more admins, please log in as Super Admin and use the admin panel.</p>
        <a href='login.php' class='btn btn-success btn-sm mt-3'>Go to Login</a>
      </div>
    </body></html>";
    exit;
}

$errors = [];
$success = '';

// Handle registration
if (isset($_POST['setup'])) {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm'] ?? '';

    if (!$name) $errors[] = "Full name is required.";
    if (!$email) $errors[] = "Email address is required.";
    if (!$password) $errors[] = "Password is required.";
    if ($password !== $confirm) $errors[] = "Passwords do not match.";
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Invalid email address.";

    // Check if email already exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email=? LIMIT 1");
    $stmt->execute([$email]);
    if ($stmt->fetch()) $errors[] = "This email is already registered.";

    if (!$errors) {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users (name,email,password,role) VALUES (?,?,?,?)");
        $stmt->execute([$name, $email, $hash, 'super_admin']);
        $success = "🎉 Super Admin created successfully! You can now login.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Super Admin Setup - PickleHub</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.1/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
body {
  background: linear-gradient(135deg, #e3f2fd, #bbdefb);
  font-family: "Poppins", sans-serif;
}
.card {
  border-radius: 15px;
  box-shadow: 0 6px 20px rgba(0,0,0,0.15);
}
h3 {
  font-weight: 700;
  color: #1565c0;
}
.btn-success {
  background: linear-gradient(135deg, #2e7d32, #43a047);
  border: none;
}
</style>
</head>
<body>
<div class="container d-flex justify-content-center align-items-center" style="min-height:100vh;">
  <div class="card p-4" style="max-width:450px; width:100%;">
    <h3 class="text-center mb-3">Super Admin Setup</h3>
    <p class="text-center text-muted small mb-4">This setup is only available once — use it to create your main Super Admin account.</p>

    <?php if($errors): ?>
      <div class="alert alert-danger">
        <?php foreach($errors as $e) echo "<div>".h($e)."</div>"; ?>
      </div>
    <?php endif; ?>

    <?php if($success): ?>
      <div class="alert alert-success text-center"><?= h($success) ?></div>
      <div class="text-center">
        <a href="login.php" class="btn btn-success mt-3">Go to Login</a>
      </div>
    <?php else: ?>
    <form method="POST">
      <div class="mb-3">
        <label class="fw-semibold">Full Name</label>
        <input type="text" name="name" class="form-control" placeholder="Your full name" required>
      </div>
      <div class="mb-3">
        <label class="fw-semibold">Email Address</label>
        <input type="email" name="email" class="form-control" placeholder="Your email" required>
      </div>
      <div class="mb-3">
        <label class="fw-semibold">Password</label>
        <input type="password" name="password" class="form-control" placeholder="Create password" required>
      </div>
      <div class="mb-3">
        <label class="fw-semibold">Confirm Password</label>
        <input type="password" name="confirm" class="form-control" placeholder="Confirm password" required>
      </div>
      <button class="btn btn-success w-100 fw-bold" name="setup">Create Super Admin</button>
    </form>
    <?php endif; ?>
  </div>
</div>



</body>
</html>
