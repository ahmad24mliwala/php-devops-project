<?php
// admin/categories.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/functions.php';
is_admin();

$errors = [];
$success = '';

/* ---------------- ADD CATEGORY ---------------- */
if (isset($_POST['add_category'])) {
    $name = trim($_POST['name'] ?? '');
    $img_name = '';

    if ($name === '') {
        $errors[] = "Category name is required.";
    }

    if (!empty($_FILES['image']['tmp_name'])) {
        $img = $_FILES['image'];
        $ext = strtolower(pathinfo($img['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg','jpeg','png','webp'];

        if (!in_array($ext, $allowed)) {
            $errors[] = "Invalid image format. Allowed: JPG, PNG, WebP.";
        } elseif ($img['size'] > 2 * 1024 * 1024) {
            $errors[] = "Image too large. Max 2MB.";
        } else {
            $img_name = time() . '_' . preg_replace('/[^a-zA-Z0-9_\-.]/', '_', basename($img['name']));
            if (!is_dir(__DIR__ . '/../uploads')) {
                @mkdir(__DIR__ . '/../uploads', 0755, true);
            }
            move_uploaded_file($img['tmp_name'], __DIR__ . '/../uploads/' . $img_name);
        }
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare("INSERT INTO categories (name, image) VALUES (?, ?)");
        $stmt->execute([$name, $img_name]);
        $success = "Category added successfully!";
    }
}

/* ---------------- EDIT CATEGORY ---------------- */
if (isset($_POST['edit_category'])) {
    $id = intval($_POST['category_id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $old_image = $_POST['old_image'] ?? '';
    $img_name = $old_image;

    if ($name === '') {
        $errors[] = "Category name is required.";
    }

    if (!empty($_FILES['image']['tmp_name'])) {
        $img = $_FILES['image'];
        $ext = strtolower(pathinfo($img['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg','jpeg','png','webp'];

        if (!in_array($ext, $allowed)) {
            $errors[] = "Invalid image format. Allowed: JPG, PNG, WebP.";
        } elseif ($img['size'] > 2 * 1024 * 1024) {
            $errors[] = "Image too large. Max 2MB.";
        } else {
            $img_name = time() . '_' . preg_replace('/[^a-zA-Z0-9_\-.]/', '_', basename($img['name']));
            move_uploaded_file($img['tmp_name'], __DIR__ . '/../uploads/' . $img_name);
        }
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare("UPDATE categories SET name = ?, image = ? WHERE id = ?");
        $stmt->execute([$name, $img_name, $id]);
        $success = "Category updated successfully!";
    }
}

/* ---------------- DELETE CATEGORY ---------------- */
if (isset($_POST['delete_category'])) {
    $id = intval($_POST['id'] ?? 0);

    if ($id) {
        $img = $pdo->prepare("SELECT image FROM categories WHERE id = ?");
        $img->execute([$id]);
        $row = $img->fetch();

        if ($row && $row['image']) {
            $file = __DIR__ . '/../uploads/' . $row['image'];
            if (file_exists($file)) @unlink($file);
        }

        $stmt = $pdo->prepare("DELETE FROM categories WHERE id = ?");
        $stmt->execute([$id]);
        $success = "Category deleted successfully!";
    }
}

/* ---------------- FETCH CATEGORIES ---------------- */
$categories = $pdo->query("SELECT * FROM categories ORDER BY id DESC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Categories - Admin</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.1/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="../public/assets/style.css" rel="stylesheet">

<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">

<style>
/* --- MOBILE FRIENDLY FIX (Option A) --- */
.category-card {
    border-radius: 14px;
    padding: 15px;
    background: #fff;
    box-shadow: 0 3px 10px rgba(0,0,0,0.08);
}
.category-img {
    width: 80px;
    height: 80px;
    border-radius: 10px;
    object-fit: cover;
    border: 1px solid #ddd;
}

/* 👇 FIXED BUTTONS: STACK VERTICALLY ON MOBILE */
.category-actions {
    display: flex;
    flex-direction: column;
    gap: 8px;
}
.category-actions button,
.category-actions form button {
    width: 100%;
}

/* hide desktop table on mobile */
@media(max-width: 768px){
    .desktop-table { display: none; }
    .category-img { width: 64px; height: 64px; }
}

/* hide mobile cards on desktop */
@media(min-width: 769px){
    .mobile-view { display: none; }
}
</style>
</head>
<body>

<?php include 'header.php'; ?>

<div class="container my-4">
    <h2 class="text-success fw-bold mb-3">Manage Categories</h2>

    <?php if ($errors): ?>
        <div class="alert alert-danger"><?php foreach ($errors as $e) echo h($e) . "<br>"; ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success"><?= h($success) ?></div>
    <?php endif; ?>

    <!-- ADD CATEGORY -->
    <div class="card p-3 mb-4 shadow-sm">
        <h5 class="mb-2">Add New Category</h5>
        <form method="POST" enctype="multipart/form-data" class="row g-2">
            <div class="col-12 col-md-4">
                <input type="text" name="name" class="form-control" placeholder="Category Name" required>
            </div>
            <div class="col-12 col-md-4">
                <input type="file" name="image" class="form-control">
            </div>
            <div class="col-12 col-md-4 d-grid">
                <button class="btn btn-success" name="add_category">Add Category</button>
            </div>
        </form>
    </div>

    <!-- MOBILE VIEW -->
    <div class="mobile-view">
        <?php foreach ($categories as $c): ?>
            <div class="category-card mb-3">

                <div class="d-flex align-items-center">
                    <?php if ($c['image'] && file_exists(__DIR__ . '/../uploads/' . $c['image'])): ?>
                        <img src="../uploads/<?= h($c['image']) ?>" class="category-img me-3" alt="">
                    <?php else: ?>
                        <div class="category-img me-3 bg-light d-flex justify-content-center align-items-center text-muted">
                            No Img
                        </div>
                    <?php endif; ?>

                    <div>
                        <h6 class="fw-bold mb-1"><?= h($c['name']) ?></h6>
                        <small class="text-muted">ID: <?= h($c['id']) ?></small>
                    </div>
                </div>

                <div class="category-actions mt-3">
                    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#editModal<?= $c['id'] ?>">Edit</button>

                    <form method="POST" onsubmit="return confirm('Delete this category?');">
                        <input type="hidden" name="id" value="<?= h($c['id']) ?>">
                        <button name="delete_category" class="btn btn-danger btn-sm">Delete</button>
                    </form>
                </div>

            </div>
        <?php endforeach; ?>
    </div>

    <!-- DESKTOP TABLE -->
    <div class="desktop-table">
        <table id="categoriesTable" class="table table-striped table-bordered">
            <thead class="table-success">
                <tr><th>ID</th><th>Name</th><th>Image</th><th>Actions</th></tr>
            </thead>
            <tbody>
                <?php foreach ($categories as $c): ?>
                <tr>
                    <td><?= h($c['id']) ?></td>
                    <td><?= h($c['name']) ?></td>
                    <td>
                        <?php if ($c['image'] && file_exists(__DIR__ . '/../uploads/' . $c['image'])): ?>
                            <img src="../uploads/<?= h($c['image']) ?>" height="40">
                        <?php endif; ?>
                    </td>
                    <td>
                        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#editModal<?= $c['id'] ?>">Edit</button>

                        <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this category?');">
                            <input type="hidden" name="id" value="<?= h($c['id']) ?>">
                            <button name="delete_category" class="btn btn-danger btn-sm">Delete</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- MODALS -->
<?php foreach ($categories as $c): ?>
<div class="modal fade" id="editModal<?= $c['id'] ?>" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="category_id" value="<?= h($c['id']) ?>">
                <input type="hidden" name="old_image" value="<?= h($c['image']) ?>">

                <div class="modal-header">
                    <h5 class="modal-title">Edit Category</h5>
                    <button class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                    <label>Name</label>
                    <input type="text" name="name" value="<?= h($c['name']) ?>" class="form-control mb-3" required>

                    <label>Image</label>
                    <input type="file" name="image" class="form-control">

                    <?php if ($c['image']): ?>
                        <img src="../uploads/<?= h($c['image']) ?>" height="60" class="mt-2">
                    <?php endif; ?>
                </div>

                <div class="modal-footer">
                    <button class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button class="btn btn-primary" name="edit_category">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endforeach; ?>

<!-- JS -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.1/dist/js/bootstrap.bundle.min.js"></script>

<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

<script>
$(function(){
    $('#categoriesTable').DataTable({
        pageLength: 10,
        order: [[0,"desc"]]
    });
});
</script>

<script src="/picklehub_project/admin/assets/js/admin.js" defer></script>

</body>
</html>
