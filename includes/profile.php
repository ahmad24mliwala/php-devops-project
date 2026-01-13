<?php
require '../includes/db.php';
require '../includes/functions.php';
is_admin();

$admin_id = $_SESSION['user_id'];

$stmt = $pdo->prepare("SELECT * FROM users WHERE id=? LIMIT 1");
$stmt->execute([$admin_id]);
$admin = $stmt->fetch();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Admin Profile</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.1/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
    body { background:#f6f6f6; }
    .profile-box {
        max-width:420px; margin:auto; background:white; padding:25px;
        border-radius:14px; box-shadow:0 5px 20px rgba(0,0,0,0.1);
    }
    </style>
</head>
<body>

<?php include 'header.php'; ?>

<div class="container my-5">
    <div class="profile-box">
        <h3 class="text-success text-center mb-3">My Profile</h3>

        <p><strong>Name:</strong> <?=h($admin['name'])?></p>
        <p><strong>Email:</strong> <?=h($admin['email'])?></p>
        <p><strong>Role:</strong> <?=h(ucfirst($admin['role']))?></p>
        <p><strong>Joined:</strong> <?=h($admin['created_at'])?></p>

        <a href="edit_profile.php" class="btn btn-primary w-100 mt-3">Edit Profile</a>
        <a href="change_password.php" class="btn btn-warning w-100 mt-2">Change Password</a>
    </div>
</div>

</body>
</html>
