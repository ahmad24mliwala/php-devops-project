<?php
// admin/login.php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
if (session_status() == PHP_SESSION_NONE) session_start();

// Redirect if already logged in as admin or super_admin
if (isset($_SESSION['user']['role']) && in_array($_SESSION['user']['role'], ['admin','super_admin'])) {
    header('Location: index.php');
    exit;
}

$errors = [];
$success = '';

// Check if super_admin exists
$super_admin_exists = $pdo->query("SELECT id FROM users WHERE role='super_admin' LIMIT 1")->fetch();

// Handle login
if (isset($_POST['login'])) {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!$email) $errors[] = "Email is required.";
    if (!$password) $errors[] = "Password is required.";

    if (!$errors) {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email=? AND role IN ('admin','super_admin') LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            // Set session
            $_SESSION['user'] = [
                'id' => $user['id'],
                'name' => $user['name'],
                'email' => $user['email'],
                'role' => $user['role']
            ];
            header('Location: index.php');
            exit;
        } else {
            $errors[] = "Invalid email or password.";
        }
    }
}

// Handle admin registration (only allowed if super_admin or first setup)
if (isset($_POST['register'])) {
    // Restrict registration access
    if ($super_admin_exists && (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'super_admin')) {
        $errors[] = "Unauthorized registration attempt.";
    } else {
        $name = trim($_POST['reg_name'] ?? '');
        $email = trim($_POST['reg_email'] ?? '');
        $password = $_POST['reg_password'] ?? '';
        $confirm = $_POST['reg_confirm'] ?? '';
        $role = $_POST['reg_role'] ?? 'admin';

        if (!$name) $errors[] = "Name is required.";
        if (!$email) $errors[] = "Email is required.";
        if (!$password) $errors[] = "Password is required.";
        if ($password !== $confirm) $errors[] = "Passwords do not match.";

        // Check if email exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email=? LIMIT 1");
        $stmt->execute([$email]);
        if ($stmt->fetch()) $errors[] = "Email already registered.";

        // Only allow one super admin
        if ($role === 'super_admin') {
            $check = $pdo->query("SELECT id FROM users WHERE role='super_admin' LIMIT 1")->fetch();
            if ($check) $errors[] = "Super Admin already exists.";
        }

        if (!$errors) {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (name,email,password,role) VALUES (?,?,?,?)");
            $stmt->execute([$name,$email,$hash,$role]);
            $success = ucfirst($role)." registered successfully! You can now login.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Admin Login - PickleHub</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.1/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
body {
    background-color: #f8fafc;
    font-family: "Poppins", sans-serif;
}
.card {
    border-radius: 15px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}
.btn-success {
    background: linear-gradient(135deg, #198754, #28a745);
    border: none;
}
.btn-primary {
    background: linear-gradient(135deg, #007bff, #00a2ff);
    border: none;
}
</style>
<script>
function toggleRegister() {
    const form = document.getElementById('registerForm');
    form.style.display = form.style.display === 'none' ? 'block' : 'none';
}
</script>
</head>
<body>
<div class="container" style="max-width:450px; margin-top:50px;">
    <div class="card p-4">
        <h3 class="text-center mb-4 fw-bold">Admin Login</h3>

        <?php if($errors): ?>
            <div class="alert alert-danger">
                <?php foreach($errors as $e) echo "<div>".h($e)."</div>"; ?>
            </div>
        <?php endif; ?>

        <?php if($success): ?>
            <div class="alert alert-success"><?=h($success)?></div>
        <?php endif; ?>

        <!-- Login Form -->
        <form method="POST">
            <div class="mb-3">
                <label class="fw-semibold">Email</label>
                <input type="email" name="email" class="form-control" value="<?=h($_POST['email'] ?? '')?>" required>
            </div>
            <div class="mb-3">
                <label class="fw-semibold">Password</label>
                <input type="password" name="password" class="form-control" required>
            </div>
            <button class="btn btn-success w-100 fw-bold" name="login">Login</button>
        </form>

        <!-- Register Section -->
        <?php if(!$super_admin_exists || (isset($_SESSION['user']['role']) && $_SESSION['user']['role']==='super_admin')): ?>
        <div class="text-center mt-3">
            <small>No account? <a href="javascript:void(0)" onclick="toggleRegister()">Register here</a></small>
        </div>

        <form id="registerForm" method="POST" style="display:none; margin-top:20px;">
            <h5 class="text-center mb-3 fw-semibold">Register New User</h5>
            <div class="mb-2">
                <input type="text" name="reg_name" class="form-control" placeholder="Full Name" required>
            </div>
            <div class="mb-2">
                <input type="email" name="reg_email" class="form-control" placeholder="Email" required>
            </div>
            <div class="mb-2">
                <input type="password" name="reg_password" class="form-control" placeholder="Password" required>
            </div>
            <div class="mb-2">
                <input type="password" name="reg_confirm" class="form-control" placeholder="Confirm Password" required>
            </div>
            <div class="mb-2">
                <select name="reg_role" class="form-control" required>
                    <option value="admin">Admin</option>
                    <option value="super_admin" <?= $super_admin_exists ? 'disabled' : '' ?>>Super Admin</option>
                </select>
            </div>
            <button class="btn btn-primary w-100 fw-bold" name="register">Register</button>
        </form>
        <?php endif; ?>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
