<?php
// admin/categories.php

ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);
ini_set('log_errors', 1);

header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');

require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/functions.php';
is_admin();

/* ================= CSRF ================= */
$csrf = csrf_token();

$errors = [];
$success = '';


/* ================= ADD CATEGORY ================= */
if (isset($_POST['add_category'])) {

    if (!verify_csrf($_POST['csrf'] ?? '')) {
        $errors[] = "Invalid session token. Please refresh.";
    }

    $name = trim($_POST['name'] ?? '');
    $slug = trim($_POST['slug'] ?? '');
    $img_name = '';

    if ($name === '') {
        $errors[] = "Category name is required.";
    }

    // Auto-generate slug if empty
    if ($slug === '') {
        $slug = make_slug($name);
    } else {
        $slug = make_slug($slug);
    }

    // Slug uniqueness check
    $checkSlug = $pdo->prepare("SELECT COUNT(*) FROM categories WHERE slug = ?");
    $checkSlug->execute([$slug]);
    if ($checkSlug->fetchColumn() > 0) {
        $errors[] = "Slug already exists. Please choose another.";
    }

    // Image upload
    if (!empty($_FILES['image']['tmp_name'])) {
        $img = $_FILES['image'];
        $ext = strtolower(pathinfo($img['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg','jpeg','png','webp'];

        if (!in_array($ext, $allowed)) {
            $errors[] = "Invalid image format (JPG, PNG, WebP only).";
        } elseif ($img['size'] > 2 * 1024 * 1024) {
            $errors[] = "Image too large (max 2MB).";
        } else {
            if (!is_dir(__DIR__ . '/../uploads')) {
                mkdir(__DIR__ . '/../uploads', 0755, true);
            }
            $img_name = uniqid('cat_', true) . '.' . $ext;
            move_uploaded_file($img['tmp_name'], __DIR__ . '/../uploads/' . $img_name);
        }
    }

    if (!$errors) {
        $stmt = $pdo->prepare(
            "INSERT INTO categories (name, slug, image) VALUES (?, ?, ?)"
        );
        $stmt->execute([$name, $slug, $img_name]);

        log_admin_activity('create', 'category', $pdo->lastInsertId(), "Category added: {$name}");
        $success = "Category added successfully!";
    }
}

/* ================= EDIT CATEGORY ================= */
if (isset($_POST['edit_category'])) {

    if (!verify_csrf($_POST['csrf'] ?? '')) {
        $errors[] = "Invalid session token.";
    }

    $id   = (int)($_POST['category_id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $slug = trim($_POST['slug'] ?? '');
    $old_image = $_POST['old_image'] ?? '';
    $img_name  = $old_image;

    if ($name === '') {
        $errors[] = "Category name is required.";
    }

    if ($slug === '') {
        $slug = make_slug($name);
    } else {
        $slug = make_slug($slug);
    }

    // Slug uniqueness (exclude self)
    $checkSlug = $pdo->prepare(
        "SELECT COUNT(*) FROM categories WHERE slug = ? AND id != ?"
    );
    $checkSlug->execute([$slug, $id]);
    if ($checkSlug->fetchColumn() > 0) {
        $errors[] = "Slug already exists.";
    }

    // Image update
    if (!empty($_FILES['image']['tmp_name'])) {
        $img = $_FILES['image'];
        $ext = strtolower(pathinfo($img['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg','jpeg','png','webp'];

        if (!in_array($ext, $allowed)) {
            $errors[] = "Invalid image format.";
        } elseif ($img['size'] > 2 * 1024 * 1024) {
            $errors[] = "Image too large.";
        } else {
            $img_name = uniqid('cat_', true) . '.' . $ext;
            move_uploaded_file($img['tmp_name'], __DIR__ . '/../uploads/' . $img_name);

            if ($old_image && file_exists(__DIR__ . '/../uploads/' . $old_image)) {
                unlink(__DIR__ . '/../uploads/' . $old_image);
            }
        }
    }

    if (!$errors) {
        $pdo->prepare(
            "UPDATE categories SET name=?, slug=?, image=? WHERE id=?"
        )->execute([$name, $slug, $img_name, $id]);

        log_admin_activity('update', 'category', $id, "Category updated: {$name}");
        $success = "Category updated successfully!";
    }
}

/* ================= DELETE CATEGORY ================= */
if (isset($_POST['delete_category'])) {

    if (!verify_csrf($_POST['csrf'] ?? '')) {
        $errors[] = "Invalid session token.";
    }

    $id = (int)($_POST['id'] ?? 0);

    if ($id) {
        $row = $pdo->prepare("SELECT name,image FROM categories WHERE id=?");
        $row->execute([$id]);
        $row = $row->fetch();

        if ($row && $row['image'] && file_exists(__DIR__ . '/../uploads/' . $row['image'])) {
            unlink(__DIR__ . '/../uploads/' . $row['image']);
        }

        $pdo->prepare("DELETE FROM categories WHERE id=?")->execute([$id]);
        log_admin_activity('delete', 'category', $id, "Category deleted: {$row['name']}");
        $success = "Category deleted successfully!";
    }
}

/* ================= FETCH ================= */
$categories = $pdo->query(
    "SELECT id, name, slug, image FROM categories ORDER BY id DESC"
)->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Categories - Admin</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.1/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
</head>
<body>

<?php include 'header.php'; ?>

<div class="container my-4">
<h2 class="text-success fw-bold mb-3">Manage Categories</h2>

<?php if ($errors): ?>
<div class="alert alert-danger"><?= implode('<br>', array_map('h', $errors)) ?></div>
<?php endif; ?>

<?php if ($success): ?>
<div class="alert alert-success"><?= h($success) ?></div>
<?php endif; ?>

<!-- ADD -->
<form method="POST" enctype="multipart/form-data" class="row g-2 mb-4">
<input type="hidden" name="csrf" value="<?= $csrf ?>">
<input type="text" name="name" class="form-control col" placeholder="Category Name" required>
<input type="text" name="slug" class="form-control col" placeholder="SEO Slug (optional)">
<input type="file" name="image" class="form-control col">
<button class="btn btn-success col" name="add_category">Add</button>
</form>

<table id="categoriesTable" class="table table-bordered">
<thead class="table-success">
<tr>
<th>ID</th><th>Name</th><th>Slug</th><th>Image</th><th>Actions</th>
</tr>
</thead>
<tbody>
<?php foreach ($categories as $c): ?>
<tr>
<td><?= h($c['id']) ?></td>
<td><?= h($c['name']) ?></td>
<td><code><?= h($c['slug']) ?></code></td>
<td><?php if ($c['image']): ?><img src="../uploads/<?= h($c['image']) ?>" height="40"><?php endif; ?></td>
<td>
<button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#edit<?= $c['id'] ?>">Edit</button>
<form method="POST" style="display:inline" onsubmit="return confirm('Delete this category?')">
<input type="hidden" name="csrf" value="<?= $csrf ?>">
<input type="hidden" name="id" value="<?= h($c['id']) ?>">
<button class="btn btn-danger btn-sm" name="delete_category">Delete</button>
</form>
</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>

<!-- EDIT MODALS -->
<?php foreach ($categories as $c): ?>
<div class="modal fade" id="edit<?= $c['id'] ?>">
<div class="modal-dialog">
<form method="POST" enctype="multipart/form-data" class="modal-content">
<input type="hidden" name="csrf" value="<?= $csrf ?>">
<input type="hidden" name="category_id" value="<?= h($c['id']) ?>">
<input type="hidden" name="old_image" value="<?= h($c['image']) ?>">

<div class="modal-header"><h5>Edit Category</h5></div>
<div class="modal-body">
<input type="text" name="name" value="<?= h($c['name']) ?>" class="form-control mb-2" required>
<input type="text" name="slug" value="<?= h($c['slug']) ?>" class="form-control mb-2">
<input type="file" name="image" class="form-control">
</div>
<div class="modal-footer">
<button class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
<button class="btn btn-primary" name="edit_category">Save</button>
</div>
</form>
</div>
</div>
<?php endforeach; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.1/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script>
$(function(){ $('#categoriesTable').DataTable({order:[[0,'desc']]}); });
</script>
</body>
</html>
