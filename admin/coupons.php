<?php
require '../includes/db.php';
require '../includes/functions.php';
is_admin();

// Add new coupon
if(isset($_POST['add_coupon'])){
    $code = $_POST['code'];
    $type = $_POST['discount_type'];
    $value = $_POST['discount_value'];
    $expiry = $_POST['expiry_date'];
    $limit = $_POST['usage_limit'];
    $stmt = $pdo->prepare("INSERT INTO coupons (code, discount_type, discount_value, expiry_date, usage_limit) VALUES (?,?,?,?,?)");
    $stmt->execute([$code,$type,$value,$expiry,$limit]);
    $success = "Coupon added successfully";
}

// Delete coupon
if(isset($_GET['delete'])){
    $id = $_GET['delete'];
    $pdo->prepare("DELETE FROM coupons WHERE id=?")->execute([$id]);
    $success = "Coupon deleted successfully";
}

// Fetch coupons
$coupons = $pdo->query("SELECT * FROM coupons ORDER BY created_at DESC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Coupons - Admin</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.1/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="../public/assets/style.css" rel="stylesheet">
</head>
<body>
<?php include 'header.php'; ?>
<div class="container my-4">
<h2>Manage Coupons</h2>

<?php if(isset($success)) echo "<div class='alert alert-success'>$success</div>"; ?>

<form method="POST" class="row g-3 mb-4">
<div class="col-md-2">
<input type="text" name="code" class="form-control" placeholder="Code" required>
</div>
<div class="col-md-2">
<select name="discount_type" class="form-select" required>
<option value="percentage">Percentage</option>
<option value="fixed">Fixed</option>
</select>
</div>
<div class="col-md-2">
<input type="number" name="discount_value" class="form-control" placeholder="Value" step="0.01" required>
</div>
<div class="col-md-3">
<input type="date" name="expiry_date" class="form-control" required>
</div>
<div class="col-md-2">
<input type="number" name="usage_limit" class="form-control" placeholder="Usage Limit" required>
</div>
<div class="col-md-1">
<button class="btn btn-success" name="add_coupon">Add</button>
</div>
</form>

<table class="table table-striped">
<thead>
<tr><th>ID</th><th>Code</th><th>Type</th><th>Value</th><th>Expiry</th><th>Used</th><th>Limit</th><th>Actions</th></tr>
</thead>
<tbody>
<?php foreach($coupons as $c): ?>
<tr>
<td><?=h($c['id'])?></td>
<td><?=h($c['code'])?></td>
<td><?=h($c['discount_type'])?></td>
<td><?=h($c['discount_value'])?></td>
<td><?=h($c['expiry_date'])?></td>
<td><?=h($c['used_count'])?></td>
<td><?=h($c['usage_limit'])?></td>
<td>
<a href="?delete=<?=h($c['id'])?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this coupon?')">Delete</a>
</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
