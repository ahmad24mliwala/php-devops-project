<?php
// public/checkout.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/track_visit.php'; 

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
log_visit($pdo);

// DEBUG: write to debug log
$logFile = __DIR__ . '/checkout_debug.txt';
@file_put_contents($logFile, "[".date('Y-m-d H:i:s')."] checkout.php loaded (IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'cli') . ")\n", FILE_APPEND);

// ==========================================================
// AJAX: login endpoint (for drawer / inline login)
// ==========================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_login'])) {
    header('Content-Type: application/json; charset=utf-8');

    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (!$email || !$password) {
        echo json_encode(['status' => 'error', 'message' => 'Email and password are required.']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("SELECT id,name,email,password,role,phone,address,city,state,zip FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $u = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$u) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid credentials.']);
            exit;
        }

        $hash = $u['password'] ?? '';

        // verify password
        $ok = false;
        if ($hash && password_verify($password, $hash)) $ok = true;
        // fallback (legacy plain text) - keep but discourage
        if (!$ok && $password === $hash) $ok = true;

        if (!$ok) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid credentials.']);
            exit;
        }

        // success -> set session
        $_SESSION['user'] = [
            'id' => (int)$u['id'],
            'name' => $u['name'],
            'email' => $u['email'],
            'role' => $u['role'] ?? 'customer',
            'phone' => $u['phone'] ?? '',
            'address' => $u['address'] ?? '',
            'city' => $u['city'] ?? '',
            'state' => $u['state'] ?? '',
            'zip' => $u['zip'] ?? ''
        ];

        echo json_encode(['status' => 'success', 'message' => 'Login successful']);
        exit;
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => 'Login failed: ' . $e->getMessage()]);
        exit;
    }
}

// ==========================================================
// State & district data
// ==========================================================
$india_states = [
    "Andhra Pradesh","Arunachal Pradesh","Assam","Bihar","Chhattisgarh","Goa","Gujarat",
    "Haryana","Himachal Pradesh","Jharkhand","Karnataka","Kerala","Madhya Pradesh",
    "Maharashtra","Manipur","Meghalaya","Mizoram","Nagaland","Odisha","Punjab",
    "Rajasthan","Sikkim","Tamil Nadu","Telangana","Tripura","Uttar Pradesh",
    "Uttarakhand","West Bengal","Andaman and Nicobar Islands","Chandigarh",
    "Dadra and Nagar Haveli and Daman and Diu","Delhi","Jammu and Kashmir",
    "Ladakh","Lakshadweep","Puducherry"
];

$districts_by_state = [
    "Maharashtra" => ["Mumbai","Pune","Nagpur","Nashik","Thane","Aurangabad","Solapur"],
    "Gujarat" => ["Ahmedabad","Surat","Vadodara","Rajkot","Bhavnagar","Jamnagar"],
    "Tamil Nadu" => ["Chennai","Coimbatore","Madurai","Tiruchirappalli"],
    "Kerala" => ["Thiruvananthapuram","Kochi","Kozhikode","Thrissur"],
    "Uttar Pradesh" => ["Lucknow","Kanpur","Varanasi","Agra","Noida"],
    "Delhi" => ["Central Delhi","East Delhi","North Delhi","South Delhi","West Delhi"],
    "Rajasthan" => ["Jaipur","Jodhpur","Udaipur","Kota","Ajmer"],
    "Madhya Pradesh" => ["Bhopal","Indore","Gwalior","Jabalpur","Ujjain"],
    "Karnataka" => ["Bengaluru","Mysuru","Hubballi","Mangaluru","Belagavi"],
    "Goa" => ["North Goa","South Goa"]
];

// ==========================================================
// Prepare cart items
// ==========================================================
$cart_items = [];
$total = 0;
$cart_session = $_SESSION['cart'] ?? [];
$ids = implode(',', array_map('intval', array_keys($cart_session)));

if (empty($cart_session) || !$ids) {
    // nothing to checkout
    header('Location: products.php');
    exit;
}

try {
    // Fetch products
    $stmt = $pdo->query("SELECT id, name, price, image FROM products WHERE id IN ($ids)");
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // If query failed, empty cart and redirect
    $_SESSION['cart'] = [];
    header('Location: products.php');
    exit;
}

foreach ($products as $p) {
    $qty = $cart_session[$p['id']]['qty'] ?? 1;
    $p['qty'] = $qty;
    $p['subtotal'] = $p['price'] * $qty;
    $p['image_path'] = $p['image']
        ? 'image.php?file=' . urlencode($p['image'])
        : 'image.php?file=product_placeholder.jpg';

    $total += $p['subtotal'];
    $cart_items[] = $p;
}

// ==========================================================
// AJAX: Checkout submission
// - Server enforces login here even if UI opens drawer client-side
// ==========================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) {
    header('Content-Type: application/json; charset=utf-8');

    // enforce login
    if (!isset($_SESSION['user']['id'])) {
        echo json_encode(['status' => 'error', 'message' => 'You must be logged in to place an order.', 'code' => 'login_required']);
        exit;
    }

    $name  = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $district = trim($_POST['district'] ?? '');
    $state = trim($_POST['state'] ?? '');
    $zip = trim($_POST['zip'] ?? '');
    $payment_method = $_POST['payment_method'] ?? 'cod';

    $errors = [];
    if (!$name) $errors[] = 'Name is required.';
    if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email required.';
    if (!$phone) $errors[] = 'Phone is required.';
    if (!$address) $errors[] = 'Address is required.';
    if (!$district) $errors[] = 'District is required.';
    if (!$state || !in_array($state, $india_states)) $errors[] = 'Select a valid state.';
    if (!$zip) $errors[] = 'Pincode is required.';

    if ($errors) {
        echo json_encode(['status' => 'error', 'message' => implode('<br>', $errors)]);
        exit;
    }

    try {
        $pdo->beginTransaction();
        $shipping = "$address, $district, $state - $zip, India";
        $user_id = $_SESSION['user']['id'];
        $status = ($payment_method === 'cod') ? 'pending' : 'processing';

        $ins = $pdo->prepare("INSERT INTO orders (user_id,name,email,phone,shipping_address,total_amount,payment_method,status,order_date,created_at)
                       VALUES (?,?,?,?,?,?,?,?,NOW(),NOW())");
        $ins->execute([$user_id, $name, $email, $phone, $shipping, $total, $payment_method, $status]);

        $order_id = $pdo->lastInsertId();

        $stmt_item = $pdo->prepare("INSERT INTO order_items (order_id,product_id,product_name,quantity,price) VALUES (?,?,?,?,?)");
        foreach ($cart_items as $item) {
            $stmt_item->execute([$order_id, $item['id'], $item['name'], $item['qty'], $item['price']]);
        }

        $upd = $pdo->prepare("UPDATE users SET phone=?,address=?,city=?,state=?,zip=?,country=? WHERE id=?");
        $upd->execute([$phone, $address, $district, $state, $zip, 'India', $user_id]);

        $pdo->commit();
        $_SESSION['cart'] = [];
        $_SESSION['just_order_id'] = $order_id;

        echo json_encode(['status' => 'success', 'order_id' => $order_id, 'redirect' => "invoice.php?order_id=$order_id"]);
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['status' => 'error', 'message' => 'Order failed: ' . $e->getMessage()]);
    }
    exit;
}

// Helper flags for template
$saved_district = $_SESSION['user']['city'] ?? '';
$saved_state = $_SESSION['user']['state'] ?? '';
$is_logged_in = isset($_SESSION['user']['id']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Checkout - Avoji Foods</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.1/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
body { background:#f8f9fa; font-family:"Segoe UI",sans-serif; }
.cart-table img { width:80px; height:80px; object-fit:cover; }
@media(max-width:768px){
  .cart-table img{width:60px;height:60px;}
  .btn-lg{width:100%;}
  .form-control,.form-select{font-size:15px;}
}
#overlay{display:none;position:fixed;top:0;left:0;width:100%;height:100%;
background:rgba(255,255,255,0.9);z-index:9999;text-align:center;padding-top:30%;}
.spinner-border{width:3rem;height:3rem;color:#28a745;}
#successBox { display:none; text-align:center; }

/* Sliding login drawer */
#loginDrawerOverlay {
  position: fixed;
  inset: 0;
  background: rgba(0,0,0,0.45);
  z-index: 1100;
  display: none;
}
#loginDrawer {
  position: fixed;
  top: 0;
  right: -420px;
  width: 380px;
  height: 100%;
  background: #fff;
  z-index: 1101;
  box-shadow: -12px 0 30px rgba(0,0,0,0.15);
  transition: right 0.28s cubic-bezier(.2,.9,.3,1);
  padding: 18px;
  overflow-y: auto;
}
#loginDrawer.open { right: 0; }
#loginDrawer .drawer-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:12px; }
#loginDrawer .drawer-brand { font-weight:700; font-size:1.2rem; color:#198754; }
#loginDrawer .close-drawer { background:transparent; border:0; font-size:22px; line-height:1; }
#loginDrawer .login-note { font-size:0.95rem; color:#444; margin-bottom:14px; }
#loginDrawer .form-control{margin-bottom:10px;}
#loginDrawer .login-error{color:#a94442; margin-bottom:8px; display:none;}
#loginDrawer .login-success{color:#155724; margin-bottom:8px; display:none;}
.drawer-footer { position: absolute; bottom: 14px; left: 18px; right: 18px; font-size: .9rem; color: #666;}
@media(max-width:576px){
  #loginDrawer { width: 100%; right: -100%; }
  #loginDrawer.open { right: 0; }
}
</style>
</head>
<body>
<?php include __DIR__.'/includes/header.php'; ?>

<div class="container my-5">
  <h2 class="text-center text-success fw-bold mb-4">Checkout</h2>

  <div class="row g-4" id="checkoutSection">
    <!-- Order Summary -->
    <div class="col-lg-6 col-12">
      <h4 class="text-primary">Order Summary</h4>
      <table class="table table-hover align-middle cart-table bg-white rounded shadow-sm">
        <thead class="table-success">
          <tr><th>Product</th><th class="text-center">Qty</th><th class="text-end">Subtotal</th></tr>
        </thead>
        <tbody>
          <?php foreach($cart_items as $item): ?>
          <tr>
            <td><img src="<?= h($item['image_path']) ?>" class="me-2 rounded" alt=""> <?= h($item['name']) ?></td>
            <td class="text-center"><?= h($item['qty']) ?></td>
            <td class="text-end">₹<?= number_format($item['subtotal'],2) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
        <tfoot>
          <tr>
            <th colspan="2" class="text-end">Total:</th>
            <th class="text-end text-success">₹<?= number_format($total,2) ?></th>
          </tr>
        </tfoot>
      </table>
    </div>

    <!-- Billing Form -->
    <div class="col-lg-6 col-12">
      <h4 class="text-primary">Billing Details</h4>
      <form id="checkoutForm" class="row g-3">
        <input type="hidden" name="ajax" value="1">
        <div class="col-12"><input type="text" name="name" class="form-control" value="<?= h($_SESSION['user']['name'] ?? '') ?>" placeholder="Full Name" required></div>
        <div class="col-12"><input type="email" name="email" class="form-control" value="<?= h($_SESSION['user']['email'] ?? '') ?>" placeholder="Email" required></div>
        <div class="col-12"><input type="tel" name="phone" class="form-control" placeholder="Phone" value="<?= h($_SESSION['user']['phone'] ?? '') ?>" required></div>
        <div class="col-12"><textarea name="address" class="form-control" placeholder="Address" rows="2" required><?= h($_SESSION['user']['address'] ?? '') ?></textarea></div>

        <div class="col-md-6">
          <label class="form-label">State</label>
          <select name="state" id="stateSelect" class="form-select" required>
            <option value="">Select State</option>
            <?php foreach($india_states as $st): ?>
              <option value="<?= h($st) ?>" <?= ($saved_state === $st) ? 'selected' : '' ?>><?= h($st) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-md-6">
          <label class="form-label">District</label>
          <select name="district" id="districtSelect" class="form-select" required>
            <option value="">Select District</option>
          </select>
        </div>

        <div class="col-md-4"><input type="text" name="zip" class="form-control" placeholder="Pincode" value="<?= h($_SESSION['user']['zip'] ?? '') ?>" required></div>

        <div class="col-12 mt-3">
          <h5>Payment Method</h5>
          <div class="form-check"><input class="form-check-input" type="radio" name="payment_method" value="cod" checked> <label class="form-check-label">Cash on Delivery (COD)</label></div>
          <div class="form-check"><input class="form-check-input" type="radio" name="payment_method" value="online"> <label class="form-check-label">Online Payment (Coming Soon)</label></div>
        </div>

        <div class="col-12 text-end">
          <button type="submit" id="placeOrderBtn" class="btn btn-success btn-lg w-100">Place Order</button>
        </div>
      </form>
      <div id="messageBox" class="mt-3"></div>

      <?php if (!$is_logged_in): ?>
        <div class="alert alert-warning mt-3">You are not logged in. You must sign in to place an order. Click <strong>Place Order</strong> to sign in.</div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Order Success -->
  <div id="successBox" class="p-4 bg-white rounded shadow-sm mt-5">
    <h3 class="text-success fw-bold mb-3">🎉 Order Placed Successfully!</h3>
    <p id="successText"></p>
    <div class="d-flex justify-content-center gap-3 mt-3 flex-wrap">
      <a href="products.php" class="btn btn-outline-success">🛍 Continue Shopping</a>
      <a href="index.php" class="btn btn-outline-secondary">🏠 Back to Home</a>
    </div>
  </div>
</div>

<div id="overlay"><div class="spinner-border" role="status"></div><p class="mt-3 fw-bold text-success">Processing your order...</p></div>

<?php include __DIR__.'/includes/footer.php'; ?>

<!-- Sliding Login Drawer -->
<div id="loginDrawerOverlay" aria-hidden="true"></div>
<aside id="loginDrawer" aria-hidden="true" aria-labelledby="loginDrawerTitle">
  <div class="drawer-header">
    <div>
      <div class="drawer-brand">Avoji Foods — Sign in</div>
      <div class="login-note">Sign in to complete your order. Your cart will be preserved.</div>
    </div>
    <button class="close-drawer" id="closeDrawer" aria-label="Close">&times;</button>
  </div>

  <div id="loginMessages">
    <div class="login-error" id="loginError"></div>
    <div class="login-success" id="loginSuccess"></div>
  </div>

  <form id="drawerLoginForm" autocomplete="off" novalidate>
    <div class="mb-2">
      <label class="form-label">Email</label>
      <input type="email" name="email" id="drawerEmail" class="form-control" required>
    </div>
    <div class="mb-2">
      <label class="form-label">Password</label>
      <input type="password" name="password" id="drawerPassword" class="form-control" required>
    </div>

    <div class="d-grid gap-2 mt-3">
      <button type="submit" class="btn btn-success">Sign In & Continue</button>
      <button type="button" id="drawerCloseBtn" class="btn btn-outline-secondary">Cancel</button>
    </div>

    <div class="drawer-footer">
      <p>Don't have an account? <a href="register.php">Register here</a>.</p>
    </div>
  </form>
</aside>

<script>
const districtData = <?= json_encode($districts_by_state, JSON_HEX_TAG) ?>;
const savedDistrict = <?= json_encode($saved_district, JSON_HEX_TAG) ?>;
const savedState = <?= json_encode($saved_state, JSON_HEX_TAG) ?>;
let attemptedCheckout = false;
let isLoggedIn = <?= $is_logged_in ? 'true' : 'false' ?>;

document.addEventListener("DOMContentLoaded", () => {
  const stateSelect = document.getElementById('stateSelect');
  const districtSelect = document.getElementById('districtSelect');
  const form = document.getElementById('checkoutForm');
  const overlay = document.getElementById('overlay');
  const msg = document.getElementById('messageBox');
  const successBox = document.getElementById('successBox');
  const successText = document.getElementById('successText');
  const checkoutSection = document.getElementById('checkoutSection');

  // Drawer elements
  const loginDrawer = document.getElementById('loginDrawer');
  const loginOverlay = document.getElementById('loginDrawerOverlay');
  const drawerForm = document.getElementById('drawerLoginForm');
  const closeDrawerBtn = document.getElementById('closeDrawer');
  const drawerCloseBtn = document.getElementById('drawerCloseBtn');
  const loginError = document.getElementById('loginError');
  const loginSuccess = document.getElementById('loginSuccess');

  function openDrawer() {
    loginOverlay.style.display = 'block';
    loginDrawer.classList.add('open');
    loginDrawer.setAttribute('aria-hidden','false');
    loginOverlay.setAttribute('aria-hidden','false');
    document.body.style.overflow = 'hidden';
    document.getElementById('drawerEmail').focus();
  }
  function closeDrawer() {
    loginOverlay.style.display = 'none';
    loginDrawer.classList.remove('open');
    loginDrawer.setAttribute('aria-hidden','true');
    loginOverlay.setAttribute('aria-hidden','true');
    document.body.style.overflow = '';
    loginError.style.display = 'none';
    loginSuccess.style.display = 'none';
    attemptedCheckout = false;
  }

  // populate districts
  function populateDistricts(state){
    districtSelect.innerHTML='<option value="">Select District</option>';
    if(districtData[state]){
      districtData[state].forEach(d=>{
        const opt=document.createElement('option');
        opt.value=d; opt.textContent=d;
        districtSelect.appendChild(opt);
      });
      if(savedDistrict && districtData[state].includes(savedDistrict))
        districtSelect.value=savedDistrict;
    }
  }

  if(stateSelect.value) populateDistricts(stateSelect.value);
  stateSelect.addEventListener('change', e=>populateDistricts(e.target.value));

  // Place order button opens drawer for guests
  const placeBtn = document.getElementById('placeOrderBtn');
  placeBtn.addEventListener('click', (e) => {
    if (!isLoggedIn) {
      e.preventDefault();
      attemptedCheckout = true;
      openDrawer();
    }
  });

  // Checkout AJAX POST
  form.addEventListener('submit', async e=>{
    e.preventDefault();
    if (!isLoggedIn) {
      attemptedCheckout = true;
      openDrawer();
      return;
    }

    overlay.style.display='block';
    msg.innerHTML='';
    const fd=new FormData(form);
    try{
      const res=await fetch('checkout.php',{method:'POST',body:fd});
      const data=await res.json();
      overlay.style.display='none';
      if(data.status==='success'){
        window.open(data.redirect,'_blank');
        checkoutSection.style.display='none';
        successBox.style.display='block';
        successText.innerHTML=`Your Order <strong>#${data.order_id}</strong> has been placed successfully.<br>The invoice has been opened in a new tab.`;
      } else {
        if (data.code === 'login_required') {
          attemptedCheckout = true;
          openDrawer();
          msg.innerHTML = `<div class="alert alert-warning">Please sign in to continue.</div>`;
        } else {
          msg.innerHTML=`<div class="alert alert-danger">${data.message}</div>`;
        }
      }
    } catch(err){
      overlay.style.display='none';
      msg.innerHTML='<div class="alert alert-danger">Network error — please retry.</div>';
    }
  });

  // Drawer controls
  closeDrawerBtn.addEventListener('click', closeDrawer);
  drawerCloseBtn.addEventListener('click', closeDrawer);
  loginOverlay.addEventListener('click', closeDrawer);
  document.addEventListener('keydown', (ev) => { if (ev.key === 'Escape') closeDrawer(); });

  // Drawer login submit (AJAX)
  drawerForm.addEventListener('submit', async (ev) => {
    ev.preventDefault();
    loginError.style.display='none';
    loginSuccess.style.display='none';
    const email = document.getElementById('drawerEmail').value.trim();
    const password = document.getElementById('drawerPassword').value.trim();
    if (!email || !password) {
      loginError.textContent = 'Email and password are required.';
      loginError.style.display = 'block';
      return;
    }
    const submitBtn = drawerForm.querySelector('button[type="submit"]');
    submitBtn.disabled = true;
    submitBtn.textContent = 'Signing in...';

    try {
      const payload = new FormData();
      payload.append('ajax_login', '1');
      payload.append('email', email);
      payload.append('password', password);

      const res = await fetch('checkout.php', { method: 'POST', body: payload });
      const data = await res.json();

      if (data.status === 'success') {
        loginSuccess.textContent = data.message || 'Logged in successfully.';
        loginSuccess.style.display = 'block';
        loginError.style.display = 'none';
        isLoggedIn = true;

        setTimeout(() => {
          closeDrawer();
          submitBtn.disabled = false;
          submitBtn.textContent = 'Sign In & Continue';
          if (attemptedCheckout) {
            // auto submit the checkout form
            form.requestSubmit ? form.requestSubmit() : form.submit();
          }
        }, 600);
      } else {
        loginError.textContent = data.message || 'Login failed.';
        loginError.style.display = 'block';
        submitBtn.disabled = false;
        submitBtn.textContent = 'Sign In & Continue';
      }
    } catch (err) {
      loginError.textContent = 'Network error — try again.';
      loginError.style.display = 'block';
      submitBtn.disabled = false;
      submitBtn.textContent = 'Sign In & Continue';
    }
  });

  // Do not auto-open drawer on load; only when user attempts checkout
});
</script>
</body>
</html>
