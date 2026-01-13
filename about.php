<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
log_visit($pdo);

$about_text = get_setting($pdo,'about_us_text');
$about_image = get_setting($pdo,'about_us_image');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>About Us - PickleHub</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.1/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="assets/style.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/aos@2.3.4/dist/aos.css" rel="stylesheet">
</head>
<body>
<?php include 'header.php'; ?>
<div class="container my-5">
<div class="row">
<div class="col-md-6" data-aos="fade-right">
<img src="../uploads/<?=h($about_image)?>" class="img-fluid" alt="About Us">
</div>
<div class="col-md-6" data-aos="fade-left">
<h2>About PickleHub</h2>
<p><?=h($about_text)?></p>
</div>
</div>
</div>
<?php include 'footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.1/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/aos@2.3.4/dist/aos.js"></script>
<script>AOS.init();</script>
</body>
</html>
