<?php
// admin/users.php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/functions.php';
if (session_status() == PHP_SESSION_NONE) session_start();

// Restrict to super admin only
if (!isset($_SESSION['user']) || ($_SESSION['user']['role'] ?? '') !== 'super_admin') {
    header('Location: login.php');
    exit;
}

$errors = [];
$success = "";

// Handle add new admin
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_admin'])) {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!$name || !$email || !$password) {
        $errors[] = "All fields are required.";
    } else {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email=?");
        $stmt->execute([$email]);
        if ($stmt->fetchColumn() > 0) {
            $errors[] = "Email already exists.";
        } else {
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $stmt = $pdo->prepare("INSERT INTO users (name,email,password,role) VALUES (?,?,?,?)");
            $stmt->execute([$name,$email,$hash,'admin']);
            $success = "New admin added successfully.";
        }
    }
}

// Handle delete admin
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    // Don’t allow deleting super admins
    $stmt = $pdo->prepare("SELECT role FROM users WHERE id=?");
    $stmt->execute([$id]);
    $role = $stmt->fetchColumn();

    if ($role === 'super_admin') {
        $errors[] = "Super admin cannot be deleted.";
    } else {
        $pdo->prepare("DELETE FROM users WHERE id=?")->execute([$id]);
        $success = "User deleted.";
    }
}

// Fetch all users
$users = $pdo->query("SELECT id,name,email,role FROM users ORDER BY role DESC, id DESC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Manage Users - PickleHub</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.1/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<?php include __DIR__ . '/header.php'; ?>

<div class="container my-4">
    <h2>Manage Users</h2>

    <?php if ($errors): ?>
        <div class="alert alert-danger"><?php foreach($errors as $e) echo "<div>".h($e)."</div>"; ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="alert alert-success"><?=h($success)?></div>
    <?php endif; ?>

    <!-- Add Admin Form -->
    <div class="card mb-4">
        <div class="card-header">Add New Admin</div>
        <div class="card-body">
            <form method="POST">
                <div class="mb-3">
                    <label>Name</label>
                    <input type="text" name="name" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label>Email</label>
                    <input type="email" name="email" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label>Password</label>
                    <input type="password" name="password" class="form-control" required>
                </div>
                <button type="submit" name="add_admin" class="btn btn-success">Add Admin</button>
            </form>
        </div>
    </div>

    <!-- Users Table -->
    <h4>All Users</h4>
    <table class="table table-striped">
        <thead>
            <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Email</th>
                <th>Role</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach($users as $u): ?>
            <tr>
                <td><?=h($u['id'])?></td>
                <td><?=h($u['name'])?></td>
                <td><?=h($u['email'])?></td>
                <td><span class="badge bg-<?= $u['role']==='super_admin'?'danger':'secondary' ?>"><?=h($u['role'])?></span></td>
                <td>
                    <?php if ($u['role'] !== 'super_admin'): ?>
                        <a href="?delete=<?=$u['id']?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this user?')">Delete</a>
                    <?php else: ?>
                        <span class="text-muted">Protected</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
