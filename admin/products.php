<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) session_start();

require '../includes/db.php';
require '../includes/functions.php';
require __DIR__ . '/clear_home_cache.php';

is_admin();

/* ==========================
   CSRF TOKEN
========================== */
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

/* ==========================
   FETCH CATEGORIES
========================== */
$categories = $pdo->query("SELECT * FROM categories")->fetchAll();
$errors = [];
$success = $_SESSION['success'] ?? '';
unset($_SESSION['success']);

/* ==========================
   ADD PRODUCT
========================== */
if (isset($_POST['add_product'])) {

    if (!hash_equals($csrf_token, $_POST['csrf_token'] ?? '')) {
        $errors[] = "Invalid session token.";
    }

    $name        = trim($_POST['name'] ?? '');
    $slug        = trim($_POST['slug'] ?? '');
    $category_id = (int)($_POST['category_id'] ?? 0);
    $price       = (float)($_POST['price'] ?? 0);
    $stock       = (int)($_POST['stock'] ?? 0);
    $desc        = $_POST['description'] ?? '';
    $weight      = trim($_POST['weight'] ?? '');

    /* 🧠 AUTO CART LOGIC */
    $is_cart     = ($stock > 0 && isset($_POST['is_cart_enabled'])) ? 1 : 0;
    $price_enabled = isset($_POST['is_price_enabled']) ? 1 : 0;
    $is_featured = isset($_POST['is_featured']) ? 1 : 0;

    if ($name === '') $errors[] = "Product name required";
    if ($category_id <= 0) $errors[] = "Select category";

    if ($slug === '') {
        $slug = strtolower(preg_replace('/[^a-z0-9]+/', '-', $name));
    }

    /* UNIQUE SLUG */
    $base = $slug; $i = 1;
    while ($pdo->prepare("SELECT COUNT(*) FROM products WHERE slug=?")->execute([$slug]) && $pdo->query("SELECT FOUND_ROWS()")->fetchColumn()) {
        $slug = $base . '-' . $i++;
    }

    /* IMAGE */
    $img_name = '';
    if (!empty($_FILES['image']['tmp_name'])) {
        $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg','jpeg','png','webp']) || $_FILES['image']['size'] > 2*1024*1024) {
            $errors[] = "Invalid image";
        } else {
            if (!is_dir('../uploads')) mkdir('../uploads',0755,true);
            $img_name = uniqid('prd_').'.'.$ext;
            move_uploaded_file($_FILES['image']['tmp_name'], "../uploads/$img_name");
        }
    }

    if (!$errors) {
        $pdo->prepare("
            INSERT INTO products 
            (category_id,name,slug,description,price,stock,image,is_cart_enabled,price_enabled,is_featured,weight)
            VALUES (?,?,?,?,?,?,?,?,?,?,?)
        ")->execute([
            $category_id,$name,$slug,$desc,$price,$stock,$img_name,
            $is_cart,$price_enabled,$is_featured,$weight
        ]);

        log_admin_activity('create','product',$pdo->lastInsertId(),"Product added: $name");
        $_SESSION['success'] = "Product added successfully";
        header("Location: products.php"); exit;
    }
}

/* ==========================
   GLOBAL TOGGLES (FIXED)
========================== */
if (isset($_POST['toggle_cart']) && hash_equals($csrf_token, $_POST['csrf_token'] ?? '')) {
    if ($_POST['toggle_cart'] === 'enable') {
        $pdo->exec("UPDATE products SET is_cart_enabled=1 WHERE stock > 0");
    } else {
        $pdo->exec("UPDATE products SET is_cart_enabled=0");
    }
    log_admin_activity('update','product',null,'Global cart toggle');
}

if (isset($_POST['toggle_price']) && hash_equals($csrf_token, $_POST['csrf_token'] ?? '')) {
    $pdo->exec("UPDATE products SET price_enabled=" . ($_POST['toggle_price']==='enable'?1:0));
    log_admin_activity('update','product',null,'Global price toggle');
}

/* ==========================
   FETCH PRODUCTS
========================== */
$products = $pdo->query("
    SELECT p.*, c.name AS category
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    ORDER BY p.sort_order ASC, p.id DESC
")->fetchAll();

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
/* Drag handle visuals */
.drag-handle {
  cursor: grab;
  user-select: none;
  font-size: 18px;
}

.drag-handle:active {
  cursor: grabbing;
}

/* Highlight row while dragging */
.sortable-ghost {
  background: #e9f5ff !important;
  opacity: 0.8;
}

/* Improve touch dragging */
@media (max-width: 768px) {
  .drag-handle {
    font-size: 22px;
    padding: 8px;
  }
}


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
  <input type="hidden" name="csrf_token" value="<?= h($csrf_token) ?>">

    <button name="toggle_cart" value="enable" class="btn btn-success btn-sm">Enable Add to Cart for All</button>
    <button name="toggle_cart" value="disable" class="btn btn-warning btn-sm">Disable Add to Cart for All</button>
    <button name="toggle_price" value="enable" class="btn btn-primary btn-sm">Enable Price for All</button>
    <button name="toggle_price" value="disable" class="btn btn-secondary btn-sm">Disable Price for All</button>
  </form>

  <!-- Add Product -->
  <div class="card p-3 mb-4 shadow-sm">
    <form method="POST" enctype="multipart/form-data" class="row g-2">
  <input type="hidden" name="csrf_token" value="<?= h($csrf_token) ?>">
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
  
    
    <table id="productsTable" class="table table-striped">

  <thead class="table-success">
    <tr>
      <th>ID</th>
      <th>Name</th>
      <th>Price</th>
      <th>Weight</th>
      <th>Stock</th>
      <th>Category</th>
      <th>Cart</th>
      <th>Price</th>
      <th>Featured</th>
      <th>Image</th>
      <th>Action</th>
      <th>Order</th>
    </tr>
  </thead>

  <!-- ✅ SORTABLE CONTAINER -->
  <tbody id="sortableProducts">
    <?php foreach ($products as $p): ?>
      <tr data-id="<?= h($p['id']) ?>"
          class="<?= !$p['is_cart_enabled'] ? 'cart-disabled' : '' ?>">

        <td><?= h($p['id']) ?></td>
        <td><?= h($p['name']) ?></td>
        <td>₹<?= h($p['price']) ?></td>
        <td><?= h($p['weight'] ?: '-') ?></td>
        <td><?= h($p['stock']) ?></td>
        <td><?= h($p['category']) ?></td>

        <td>
          <button class="btn btn-sm <?= $p['is_cart_enabled'] ? 'btn-success' : 'btn-danger' ?> toggle-cart"
                  data-id="<?= h($p['id']) ?>">
            <?= $p['is_cart_enabled'] ? 'Enabled' : 'Disabled' ?>
          </button>
        </td>

        <td>
          <button class="btn btn-sm <?= $p['price_enabled'] ? 'btn-success' : 'btn-danger' ?> toggle-price"
                  data-id="<?= h($p['id']) ?>">
            <?= $p['price_enabled'] ? 'Shown' : 'Hidden' ?>
          </button>
        </td>

        <td><?= $p['is_featured'] ? 'Yes' : 'No' ?></td>

        <td>
          <?php if ($p['image']): ?>
            <img src="../uploads/<?= h($p['image']) ?>" class="thumb">
          <?php endif; ?>
        </td>

        <td>
          <button class="btn btn-sm btn-primary edit-btn"
                  data-id="<?= h($p['id']) ?>">Edit</button>

          <form method="POST" style="display:inline"
                onsubmit="return confirm('Delete this product?');">
            <input type="hidden" name="csrf_token" value="<?= h($csrf_token) ?>">
            <input type="hidden" name="id" value="<?= h($p['id']) ?>">
            <button type="submit" name="delete_product"
                    class="btn btn-sm btn-danger">Delete</button>
          </form>
        </td>

        <!-- ✅ DRAG HANDLE -->
        <td class="text-center">
          <span class="drag-handle">↕️</span>
        </td>

      </tr>
    <?php endforeach; ?>
  </tbody>
</table>


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
        <div class="mb-2"><label>Stock</label><input type="number" name="stock" class="form-control" min="0" required></div>

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

<!-- Popular products -->
<h4 class="mt-5 mb-3">Popular Products (Homepage Order)</h4>

<div class="table-responsive bg-white shadow-sm p-2 rounded-3">
  <table class="table table-striped align-middle">
    <thead class="table-warning">
      <tr>
        <th>Name</th>
        <th>Image</th>
        <th>Order</th>
      </tr>
    </thead>

    <tbody id="sortableFeatured">
      <?php
      $featured = $pdo->query("
        SELECT id, name, image 
        FROM products 
        WHERE is_featured = 1
        ORDER BY featured_order ASC, id DESC
      ")->fetchAll();

      foreach ($featured as $p):
      ?>
        <tr data-id="<?= $p['id'] ?>">
          <td><?= h($p['name']) ?></td>
          <td>
            <?php if ($p['image']): ?>
              <img src="../uploads/<?= h($p['image']) ?>" class="thumb">
            <?php endif; ?>
          </td>
          <td class="text-center">
            <span class="drag-handle">↕️</span>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>


<!-- scripts: DataTables, Bootstrap, product handlers -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.1/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>


<script>
const CSRF = '<?= h($csrf_token) ?>';

$(document).ready(function () {

  /* ==========================
     DATATABLE INIT (NO SORTING)
  ========================== */
  const table = $('#productsTable').DataTable({
    paging: true,
    searching: true,
    ordering: false,
    info: true,
    destroy: true
  });

  /* ==========================
     EDIT PRODUCT MODAL
  ========================== */
  $(document).on('click', '.edit-btn', async function () {
    const id = $(this).data('id');

    try {
      const r = await fetch('product_get.php?id=' + encodeURIComponent(id));
      const p = await r.json();

      if (!p || !p.id) {
        alert('Product not found');
        return;
      }

      $('#editForm [name=id]').val(p.id);
      $('#editForm [name=name]').val(p.name);
      $('#editForm [name=price]').val(p.price);
      $('#editForm [name=weight]').val(p.weight);
      $('#editForm [name=stock]').val(p.stock);
      $('#editForm [name=description]').val(p.description);
      $('#editForm [name=category_id]').val(p.category_id);

      $('#edit_cart').prop('checked', p.is_cart_enabled == 1);
      $('#edit_price').prop('checked', p.price_enabled == 1);
      $('#edit_featured').prop('checked', p.is_featured == 1);

      new bootstrap.Modal(document.getElementById('editModal')).show();

    } catch (err) {
      console.error(err);
      alert('Failed to load product');
    }
  });

  /* ==========================
     EDIT SUBMIT
  ========================== */
  $('#editForm').on('submit', async function(e){
    e.preventDefault();
    const fd = new FormData(this);

    const res = await fetch('product_update.php', {
      method: 'POST',
      body: fd
    });

    const d = await res.json();
    if (d.status === 'success') location.reload();
    else alert(d.message || 'Update failed');
  });

  /* ==========================
     TOGGLE CART
  ========================== */
  $(document).on('click', '.toggle-cart', async function(){
    const id = $(this).data('id');

    const r = await fetch('product_toggle_cart.php', {
      method: 'POST',
      headers: {'Content-Type':'application/x-www-form-urlencoded'},
      body: `id=${id}&csrf_token=${CSRF}`
    });

    const d = await r.json();
    if (d.status === 'success') location.reload();
    else alert('Failed');
  });

  /* ==========================
     TOGGLE PRICE
  ========================== */
  $(document).on('click', '.toggle-price', async function(){
    const id = $(this).data('id');

    const r = await fetch('product_toggle_price.php', {
      method: 'POST',
      headers: {'Content-Type':'application/x-www-form-urlencoded'},
      body: `id=${id}&csrf_token=${CSRF}`
    });

    const d = await r.json();
    if (d.status === 'success') location.reload();
    else alert('Failed');
  });

});
</script>


<script>
Sortable.create(document.getElementById('sortableProducts'), {
  animation: 150,
  handle: '.drag-handle',
  ghostClass: 'sortable-ghost',
  delay: 150,
  delayOnTouchOnly: true,

  onEnd: function () {
    let order = [];

    document.querySelectorAll('#sortableProducts tr').forEach((row, index) => {
      order.push({
        id: row.dataset.id,
        position: index + 1
      });
    });

    fetch('product_reorder_bulk.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        csrf_token: '<?= h($csrf_token) ?>',
        order: order
      })
    })
    .then(r => r.json())
    .then(d => {
      if (!d.success) alert('Reorder failed');
    });
  }
});
</script>


<script>
Sortable.create(document.getElementById('sortableFeatured'), {
  animation: 150,
  handle: '.drag-handle',
  ghostClass: 'sortable-ghost',
  delay: 150,
  delayOnTouchOnly: true,

  onEnd: function () {
    let order = [];

    document.querySelectorAll('#sortableFeatured tr').forEach((row, index) => {
      order.push({
        id: row.dataset.id,
        position: index + 1
      });
    });

    fetch('product_reorder_featured.php', {
      method: 'POST',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify({
        csrf_token: '<?= h($csrf_token) ?>',
        order: order
      })
    })
    .then(r => r.json())
    .then(d => {
      if (!d.success) alert('Featured reorder failed');
    });
  }
});
</script>






<!-- single admin.js (kept) - controls dark mode, sidebar, quick panel, picker -->
<script src="/picklehub_project/admin/assets/js/admin.js" defer></script>
</body>
</html>
