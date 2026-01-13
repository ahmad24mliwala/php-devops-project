<?php
// admin/profile.php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

if (session_status() === PHP_SESSION_NONE) session_start();
is_admin();

$uid = $_SESSION['user']['id'] ?? null;
if (!$uid) {
    // if no user in session, redirect to login
    header('Location: ../public/login.php');
    exit;
}

// Fetch latest user data
$stmt = $pdo->prepare("SELECT id,name,email,role,created_at FROM users WHERE id=? LIMIT 1");
$stmt->execute([$uid]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    // if user not exists, logout
    unset($_SESSION['user']);
    header('Location: ../public/login.php');
    exit;
}

$errors = [];
$success = '';

// Handle profile update (name/email)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) { $errors[] = "Security validation failed."; }
    else {
        $new_name = trim($_POST['name'] ?? '');
        $new_email = trim($_POST['email'] ?? '');

        if (!$new_name) $errors[] = "Name cannot be empty.";
        if (!$new_email || !filter_var($new_email, FILTER_VALIDATE_EMAIL)) $errors[] = "Valid email required.";

        // ensure email not used by other user
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email=? AND id<>? LIMIT 1");
        $stmt->execute([$new_email, $uid]);
        if ($stmt->fetch()) $errors[] = "Email already in use.";

        if (empty($errors)) {
            $pdo->prepare("UPDATE users SET name=?, email=? WHERE id=?")->execute([$new_name, $new_email, $uid]);
            $_SESSION['user']['name'] = $new_name;
            $_SESSION['user']['email'] = $new_email;
            $success = "Profile updated successfully.";
            // refresh local user
            $user['name'] = $new_name; $user['email'] = $new_email;
        }
    }
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) { $errors[] = "Security validation failed."; }
    else {
        $current = $_POST['current_password'] ?? '';
        $new_pw = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        if (!$current || !$new_pw || !$confirm) $errors[] = "All password fields are required.";
        if ($new_pw !== $confirm) $errors[] = "New passwords do not match.";

        // verify current pw
        $stmt = $pdo->prepare("SELECT password FROM users WHERE id=? LIMIT 1");
        $stmt->execute([$uid]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row || !password_verify($current, $row['password'])) $errors[] = "Current password is incorrect.";

        if (empty($errors)) {
            $hash = password_hash($new_pw, PASSWORD_DEFAULT);
            $pdo->prepare("UPDATE users SET password=? WHERE id=?")->execute([$hash, $uid]);
            $success = "Password changed successfully.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>My Profile - Admin</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.1/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
.container { max-width: 920px; }
.profile-card { border-radius:12px; box-shadow: 0 6px 20px rgba(0,0,0,0.06); padding:20px; background:#fff; }
.initials { width:84px; height:84px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-weight:800; color:#fff; background:#198754; font-size:28px; }
@media (max-width:576px){ .initials{width:64px;height:64px;font-size:22px} }
</style>
</head>
<body>
<?php include 'header.php'; ?>

<div class="container my-4">
  <div class="profile-card">
    <div class="row g-3">
      <div class="col-md-4 text-center">
        <?php
          $parts = preg_split('/\s+/', trim($user['name']));
          $initials = strtoupper(($parts[0][0] ?? '') . ($parts[1][0] ?? ''));
          $initials = $initials ?: strtoupper(substr($user['name'],0,1));
        ?>
        <div class="initials mx-auto mb-3"><?= htmlspecialchars($initials) ?></div>
        <h5 class="mb-0"><?= htmlspecialchars($user['name']) ?></h5>
        <small class="text-muted"><?= htmlspecialchars($user['email']) ?></small>
        <p class="mt-2"><span class="badge bg-success"><?= htmlspecialchars(ucfirst($user['role'])) ?></span></p>
      </div>

      <div class="col-md-8">
        <?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
        <?php if ($errors): ?><div class="alert alert-danger"><?php foreach($errors as $e) echo "<div>".htmlspecialchars($e)."</div>"; ?></div><?php endif; ?>

        <h6 class="fw-bold">Edit Profile</h6>
        <form method="POST" class="row g-2 mb-3">
          <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
          <div class="col-12 col-md-6">
            <label class="form-label small">Full Name</label>
            <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($user['name']) ?>" required>
          </div>
          <div class="col-12 col-md-6">
            <label class="form-label small">Email</label>
            <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($user['email']) ?>" required>
          </div>
          <div class="col-12 text-end">
            <button class="btn btn-success" name="update_profile">Save Changes</button>
          </div>
        </form>

        <hr>

        <h6 class="fw-bold">Change Password</h6>
        <form method="POST" class="row g-2">
          <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
          <div class="col-12 col-md-6">
            <label class="form-label small">Current Password</label>
            <input type="password" name="current_password" class="form-control" required>
          </div>
          <div class="col-12 col-md-6">
            <label class="form-label small">New Password</label>
            <input type="password" name="new_password" class="form-control" required>
          </div>
          <div class="col-12 col-md-6">
            <label class="form-label small">Confirm New Password</label>
            <input type="password" name="confirm_password" class="form-control" required>
          </div>
          <div class="col-12 text-end">
            <button class="btn btn-outline-primary" name="change_password">Change Password</button>
          </div>
        </form>

      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
