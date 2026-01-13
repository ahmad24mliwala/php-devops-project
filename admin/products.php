<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require '../includes/db.php';
require '../includes/functions.php';
is_admin();

// Ensure optional columns exist (non-destructive)
try {
    $pdo->exec("ALTER TABLE products ADD COLUMN IF NOT EXISTS weight VARCHAR(50) DEFAULT NULL");
    $pdo->exec("ALTER TABLE products ADD COLUMN IF NOT EXISTS price_enabled TINYINT(1) DEFAULT 1");
} catch (Exception $e) { /* ignore */ }

// Fetch categories
$categories = $pdo->query("SELECT * FROM categories")->fetchAll();
$errors = [];
$success = '';

// Add Product
if (isset($_POST['add_product'])) {
    $name = trim($_POST['name'] ?? '');
    $slug = trim($_POST['slug'] ?? '');
    $category_id = intval($_POST['category_id'] ?? 0);
    $price = $_POST['price'] ?? 0;
    $stock = intval($_POST['stock'] ?? 0);
    $desc = $_POST['description'] ?? '';
    $weight = trim($_POST['weight'] ?? '');
    $is_cart = isset($_POST['is_cart_enabled']) ? 1 : 0;
    $is_featured = isset($_POST['is_featured']) ? 1 : 0;
    $price_enabled = isset($_POST['is_price_enabled']) ? 1 : 0;

    if (empty($name)) $errors[] = "Product name is required.";
    if ($category_id === 0) $errors[] = "Please select a category.";

    if (empty($slug)) {
        $slug = strtolower(preg_replace('/[^a-z0-9]+/','-', $name));
        $slug = trim($slug, '-');
    }

    // ensure unique slug
    $check = $pdo->prepare("SELECT COUNT(*) FROM products WHERE slug = ?");
    $check->execute([$slug]);
    if ($check->fetchColumn() > 0) $slug .= '-' . time();

    // image handling
    $img_name = '';
    if (!empty($_FILES['image']['tmp_name'])) {
        $img = $_FILES['image'];
        $ext = strtolower(pathinfo($img['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg','jpeg','png','webp']) && $img['size'] <= 2 * 1024 * 1024) {
            $img_name = time() . '_' . preg_replace('/[^a-zA-Z0-9_\-.]/', '_', basename($img['name']));
            if (!is_dir(__DIR__ . '/../uploads')) @mkdir(__DIR__ . '/../uploads', 0755, true);
            move_uploaded_file($img['tmp_name'], __DIR__ . '/../uploads/' . $img_name);
        } else {
            $errors[] = "Invalid image (Only JPG, PNG, WEBP under 2MB allowed).";
        }
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare("INSERT INTO products (category_id,name,slug,description,price,stock,image,is_cart_enabled,is_featured,weight,price_enabled)
            VALUES (?,?,?,?,?,?,?,?,?,?,?)");
        $stmt->execute([$category_id, $name, $slug, $desc, $price, $stock, $img_name, $is_cart, $is_featured, $weight, $price_enabled]);
        $success = "✅ Product added successfully!";
    }
}

// Delete
if (isset($_POST['delete_product'])) {
    $id = intval($_POST['id'] ?? 0);
    if ($id) {
        // optional: try delete image file safely
        $row = $pdo->prepare("SELECT image FROM products WHERE id = ?");
        $row->execute([$id]);
        $r = $row->fetch();
        if ($r && !empty($r['image'])) {
            $file = __DIR__ . '/../uploads/' . $r['image'];
            if (file_exists($file)) @unlink($file);
        }
        $pdo->prepare("DELETE FROM products WHERE id = ?")->execute([$id]);
        $success = "🗑️ Product deleted successfully!";
    }
}

// Global toggles
if (isset($_POST['toggle_cart'])) {
    $status = $_POST['toggle_cart'] === 'enable' ? 1 : 0;
    $pdo->query("UPDATE products SET is_cart_enabled = " . ($status ? '1' : '0'));
    $success = $status ? "🛒 Add to Cart enabled for all products" : "🛒 Add to Cart disabled for all products";
}

if (isset($_POST['toggle_price'])) {
    $status = $_POST['toggle_price'] === 'enable' ? 1 : 0;
    $pdo->query("UPDATE products SET price_enabled = " . ($status ? '1' : '0'));
    $success = $status ? "💰 Price shown for all products" : "💰 Price hidden for all products";
}

// fetch all
$products = $pdo->query("SELECT p.*, c.name AS category FROM products p LEFT JOIN categories c ON p.category_id = c.id ORDER BY p.id DESC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Products - Admin - PickleHub</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.1/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="../public/assets/style.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">

<!-- jQuery (needed for DataTables + some handlers) -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

<style>
/* keep your colors/layout intact */
body{background:#f8f9fa;font-family:'Segoe UI',sans-serif;}
.table td,.table th{vertical-align:middle!important;}
.card-modern { border-radius:14px;color:#fff;box-shadow:0 6px 20px rgba(0,0,0,.12);position:relative; }
.cart-disabled{background-color:#f8d7da!important;}
.thumb{width:50px;height:50px;object-fit:cover;border-radius:6px;}
/* Mobile: reduce thumb size and stack action buttons to prevent overflow */
@media (max-width: 768px) {
  .thumb{width:40px;height:40px;}
  /* make buttons inside table cells stack vertically and be full width */
  .table td .btn { display:block; width:100%; margin-bottom:6px; box-sizing:border-box; }
  .table td form { width:100%; }
  /* shrink some columns text size */
  .table th, .table td { font-size:0.85rem; padding:0.45rem; }
  /* ensure table container allows horizontal scroll but looks tidy */
  .table-responsive { overflow-x:auto; -webkit-overflow-scrolling:touch; }
  /* edit modal inputs: full width handled by bootstrap */
}
</style>
</head>
<body>
<?php include 'header.php'; ?>

<div class="container my-4">
  <h2 class="text-success fw-bold mb-3">Products Management</h2>

  <?php if ($errors): ?><div class="alert alert-danger"><?php foreach ($errors as $e) echo h($e) . '<br>'; ?></div><?php endif; ?>
  <?php if ($success): ?><div class="alert alert-success"><?= h($success) ?></div><?php endif; ?>

  <!-- Global Toggles -->
  <form method="POST" class="mb-3 d-flex flex-wrap gap-2">
    <button name="toggle_cart" value="enable" class="btn btn-success btn-sm">Enable Add to Cart for All</button>
    <button name="toggle_cart" value="disable" class="btn btn-warning btn-sm">Disable Add to Cart for All</button>
    <button name="toggle_price" value="enable" class="btn btn-primary btn-sm">Enable Price for All</button>
    <button name="toggle_price" value="disable" class="btn btn-secondary btn-sm">Disable Price for All</button>
  </form>

  <!-- Add Product -->
  <div class="card p-3 mb-4 shadow-sm">
    <form method="POST" enctype="multipart/form-data" class="row g-2">
      <div class="col-md-3">
        <label class="form-label fw-semibold">Product Name</label>
        <input type="text" name="name" class="form-control" required>
      </div>
      <div class="col-md-3">
        <label class="form-label fw-semibold">Slug / SEO</label>
        <input type="text" name="slug" class="form-control">
      </div>
      <div class="col-md-2">
        <label class="form-label fw-semibold">Category</label>
        <select name="category_id" class="form-select" required>
          <option value="">Select</option>
          <?php foreach ($categories as $cat): ?>
            <option value="<?=h($cat['id'])?>"><?=h($cat['name'])?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2">
        <label class="form-label fw-semibold">Price (₹)</label>
        <input type="number" name="price" class="form-control" required step="0.01">
      </div>
      <div class="col-md-2">
        <label class="form-label fw-semibold">Weight</label>
        <input type="text" name="weight" class="form-control" placeholder="e.g. 500g">
      </div>
      <div class="col-md-2">
        <label class="form-label fw-semibold">Stock</label>
        <input type="number" name="stock" class="form-control" required>
      </div>
      <div class="col-md-4">
        <label class="form-label fw-semibold">Upload Image</label>
        <input type="file" name="image" class="form-control">
      </div>
      <div class="col-md-12">
        <label class="form-label fw-semibold">Description</label>
        <textarea name="description" class="form-control" rows="2"></textarea>
      </div>

      <div class="col-md-2 form-check mt-2">
        <input type="checkbox" name="is_cart_enabled" class="form-check-input" id="cart_enabled" checked>
        <label for="cart_enabled" class="form-check-label">Add to Cart</label>
      </div>
      <div class="col-md-2 form-check mt-2">
        <input type="checkbox" name="is_price_enabled" class="form-check-input" id="price_enabled" checked>
        <label for="price_enabled" class="form-check-label">Show Price</label>
      </div>
      <div class="col-md-2 form-check mt-2">
        <input type="checkbox" name="is_featured" class="form-check-input" id="featured">
        <label for="featured" class="form-check-label">Homepage</label>
      </div>
      <div class="col-md-3 mt-2">
        <button name="add_product" class="btn btn-success w-100">Add Product</button>
      </div>
    </form>
  </div>

  <!-- Product Table -->
  <h4 class="mb-3">All Products</h4>
  <div class="table-responsive bg-white shadow-sm p-2 rounded-3">
    <table id="productsTable" class="table table-striped align-middle">
      <thead class="table-success">
        <tr>
          <th>ID</th><th>Name</th><th>Price</th><th>Weight</th><th>Stock</th><th>Category</th>
          <th>Add to Cart</th><th>Price</th><th>Featured</th><th>Image</th><th>Action</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($products as $p): ?>
          <tr class="<?= !$p['is_cart_enabled'] ? 'cart-disabled' : '' ?>">
            <td><?= h($p['id']) ?></td>
            <td><?= h($p['name']) ?></td>
            <td>₹<?= h($p['price']) ?></td>
            <td><?= h($p['weight'] ?: '-') ?></td>
            <td><?= h($p['stock']) ?></td>
            <td><?= h($p['category']) ?></td>
            <td>
              <button class="btn btn-sm <?= $p['is_cart_enabled'] ? 'btn-success' : 'btn-danger' ?> toggle-cart" data-id="<?= h($p['id']) ?>">
                <?= $p['is_cart_enabled'] ? 'Enabled' : 'Disabled' ?>
              </button>
            </td>
            <td>
              <button class="btn btn-sm <?= $p['price_enabled'] ? 'btn-success' : 'btn-danger' ?> toggle-price" data-id="<?= h($p['id']) ?>">
                <?= $p['price_enabled'] ? 'Shown' : 'Hidden' ?>
              </button>
            </td>
            <td><?= $p['is_featured'] ? 'Yes' : 'No' ?></td>
            <td><?php if ($p['image']): ?><img src="../uploads/<?= h($p['image']) ?>" class="thumb"><?php endif; ?></td>
            <td>
              <button class="btn btn-sm btn-primary edit-btn" data-id="<?= h($p['id']) ?>">Edit</button>

              <form method="POST" style="display:inline-block; width:auto;" onsubmit="return confirm('Delete this product?');">
                <input type="hidden" name="id" value="<?= h($p['id']) ?>">
                <button type="submit" name="delete_product" class="btn btn-sm btn-danger">Delete</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Edit Modal -->
<div class="modal fade" id="editModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <form id="editForm" class="modal-content" enctype="multipart/form-data" method="POST">
      <div class="modal-header bg-info text-white">
        <h5 class="modal-title">Edit Product</h5>
        <button class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="id">
        <div class="mb-2"><label>Name</label><input type="text" name="name" class="form-control" required></div>
        <div class="mb-2"><label>Price</label><input type="number" step="0.01" name="price" class="form-control" required></div>
        <div class="mb-2"><label>Weight</label><input type="text" name="weight" class="form-control"></div>
        <div class="mb-2"><label>Description</label><textarea name="description" class="form-control" rows="2"></textarea></div>
        <div class="mb-2"><label>Category</label>
          <select name="category_id" class="form-select">
            <?php foreach ($categories as $cat): ?>
              <option value="<?= h($cat['id']) ?>"><?= h($cat['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="form-check"><input type="checkbox" name="is_cart_enabled" class="form-check-input" id="edit_cart"><label for="edit_cart" class="form-check-label">Add to Cart Enabled</label></div>
        <div class="form-check"><input type="checkbox" name="price_enabled" class="form-check-input" id="edit_price"><label for="edit_price" class="form-check-label">Show Price</label></div>
        <div class="form-check"><input type="checkbox" name="is_featured" class="form-check-input" id="edit_featured"><label for="edit_featured" class="form-check-label">Show on Homepage</label></div>

        <div class="mb-2"><label>Replace Image</label><input type="file" name="image" class="form-control"></div>
      </div>
      <div class="modal-footer">
        <button type="submit" class="btn btn-info">Update</button>
      </div>
    </form>
  </div>
</div>

<!-- scripts: DataTables, Bootstrap, product handlers -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.1/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

<script>
$(function(){
  // initialize DataTable
  $('#productsTable').DataTable({
    pageLength: 10,
    order: [[0, 'desc']],
    columnDefs: [
      { orderable: false, targets: [9, 10] } // image + action not sortable
    ]
  });

  // load product into modal
  $('.edit-btn').on('click', async function(){
    const id = $(this).data('id');
    try {
      const r = await fetch('product_get.php?id=' + encodeURIComponent(id));
      const p = await r.json();
      if (p && p.id) {
        $('#editForm [name=id]').val(p.id);
        $('#editForm [name=name]').val(p.name);
        $('#editForm [name=price]').val(p.price);
        $('#editForm [name=weight]').val(p.weight);
        $('#editForm [name=description]').val(p.description);
        $('#editForm [name=category_id]').val(p.category_id);
        $('#edit_cart').prop('checked', p.is_cart_enabled == 1);
        $('#edit_price').prop('checked', p.price_enabled == 1);
        $('#edit_featured').prop('checked', p.is_featured == 1);

        // show modal
        new bootstrap.Modal(document.getElementById('editModal')).show();
      } else {
        alert('Product not found');
      }
    } catch (err) {
      console.error(err);
      alert('Failed to fetch product');
    }
  });

  // submit edit via ajax
  $('#editForm').on('submit', async function(e){
    e.preventDefault();
    const fd = new FormData(this);
    try {
      const res = await fetch('product_update.php', { method: 'POST', body: fd });
      const d = await res.json();
      if (d.status === 'success') location.reload();
      else alert(d.message || 'Update failed');
    } catch (err) {
      console.error(err);
      alert('Update failed');
    }
  });

  // toggle add to cart for single product
  $(document).on('click', '.toggle-cart', async function(){
    const id = $(this).data('id');
    try {
      const r = await fetch('product_toggle_cart.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'id=' + encodeURIComponent(id)
      });
      const d = await r.json();
      if (d.status === 'success') location.reload();
      else alert(d.message || 'Failed');
    } catch (err) {
      console.error(err);
    }
  });

  // toggle price for single product
  $(document).on('click', '.toggle-price', async function(){
    const id = $(this).data('id');
    try {
      const r = await fetch('product_toggle_price.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'id=' + encodeURIComponent(id)
      });
      const d = await r.json();
      if (d.status === 'success') location.reload();
      else alert(d.message || 'Failed');
    } catch (err) {
      console.error(err);
    }
  });
});
</script>

<!-- single admin.js (kept) - controls dark mode, sidebar, quick panel, picker -->
<script src="/picklehub_project/admin/assets/js/admin.js" defer></script>
</body>
</html>
