<?php
require '../includes/db.php';
require '../includes/functions.php';
is_admin();

$admin_id = $_SESSION['user_id'];
$success = '';
$error = '';

$stmt = $pdo->prepare("SELECT * FROM users WHERE id=?");
$stmt->execute([$admin_id]);
$admin = $stmt->fetch();

if (isset($_POST['update_profile'])) {
    $name   = trim($_POST['name']);
    $email  = trim($_POST['email']);

    if (!$name || !$email) {
        $error = "All fields are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email address.";
    } else {
        // Check duplicate email
        $chk = $pdo->prepare("SELECT id FROM users WHERE email=? AND id!=?");
        $chk->execute([$email, $admin_id]);

        if ($chk->fetch()) {
            $error = "Email already used by another account.";
        } else {
            $update = $pdo->prepare("UPDATE users SET name=?, email=? WHERE id=?");
            $update->execute([$name, $email, $admin_id]);
            $success = "Profile updated successfully!";
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Edit Profile</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.1/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>

<?php include 'header.php'; ?>

<div class="container my-5">
    <div class="col-lg-5 mx-auto card p-4">
        <h3 class="text-success">Edit Profile</h3>

        <?php if($error): ?><div class="alert alert-danger"><?=$error?></div><?php endif; ?>
        <?php if($success): ?><div class="alert alert-success"><?=$success?></div><?php endif; ?>

        <form method="POST">
            <label class="fw-bold mt-2">Name</label>
            <input type="text" name="name" class="form-control" value="<?=h($admin['name'])?>" required>

            <label class="fw-bold mt-3">Email</label>
            <input type="email" name="email" class="form-control" value="<?=h($admin['email'])?>" required>

            <button class="btn btn-success w-100 mt-3" name="update_profile">Save Changes</button>
        </form>
    </div>
</div>

</body>
</html>
