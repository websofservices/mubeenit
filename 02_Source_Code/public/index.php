<?php
/**
 * MTNMANGOES – Online Mango Sales and Management System
 * Fully functional web application matching the FYP Synopsis
 */
session_start();
require_once __DIR__ . '/../config/database.php';

// DEMO auto-login for screenshots: ?demo=customer|farmer|admin
if (isset($_GET['demo']) && in_array($_GET['demo'], ['customer','farmer','admin'], true)) {
    $db = getDB();
    $map = ['customer'=>'ahmed@customer.com','farmer'=>'ali@farmer.com','admin'=>'admin@mtnmangoes.com'];
    $stmt = $db->prepare('SELECT id FROM users WHERE email = ?');
    $stmt->execute([$map[$_GET['demo']]]);
    $uid = $stmt->fetchColumn();
    if ($uid) { $_SESSION['user_id'] = (int)$uid; }
}


// ---------- Helpers ----------
function isLoggedIn() { return isset($_SESSION['user_id']); }
function currentUser() {
    if (!isLoggedIn()) return null;
    static $user = null;
    if ($user === null) {
        $stmt = getDB()->prepare("SELECT * FROM users WHERE id = ? AND is_active = 1");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();
    }
    return $user;
}
function requireAuth($roles = null) {
    if (!isLoggedIn()) { header('Location: ?page=login'); exit; }
    if ($roles) {
        $u = currentUser();
        if (!$u || !in_array($u['role'], (array)$roles)) {
            header('Location: ?page=home'); exit;
        }
    }
}
function flash($msg, $type = 'success') {
    $_SESSION['flash'] = ['msg' => $msg, 'type' => $type];
}
function getFlash() {
    if (isset($_SESSION['flash'])) {
        $f = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $f;
    }
    return null;
}
function e($str) { return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8'); }
function money($n) { return 'Rs. ' . number_format($n, 0); }

// ---------- POST Actions ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $db = getDB();

    if ($action === 'register') {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $role = $_POST['role'] ?? 'customer';
        $phone = trim($_POST['phone'] ?? '');
        $city = trim($_POST['city'] ?? '');
        if (!in_array($role, ['customer', 'farmer'])) $role = 'customer';
        if ($name && $email && strlen($password) >= 6) {
            try {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $db->prepare("INSERT INTO users (name, email, password, role, phone, city) VALUES (?,?,?,?,?,?)")
                   ->execute([$name, $email, $hash, $role, $phone, $city]);
                flash('Registration successful! Please login.');
                header('Location: ?page=login'); exit;
            } catch (PDOException $ex) {
                flash('Email already registered.', 'danger');
            }
        } else {
            flash('Please fill all required fields (password min 6 chars).', 'danger');
        }
        header('Location: ?page=register'); exit;
    }

    if ($action === 'login') {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $stmt = $db->prepare("SELECT * FROM users WHERE email = ? AND is_active = 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            flash('Welcome back, ' . $user['name'] . '!');
            if ($user['role'] === 'admin') header('Location: ?page=admin');
            elseif ($user['role'] === 'farmer') header('Location: ?page=farmer');
            else header('Location: ?page=home');
            exit;
        }
        flash('Invalid email or password.', 'danger');
        header('Location: ?page=login'); exit;
    }

    if ($action === 'logout') {
        session_destroy();
        header('Location: ?page=home'); exit;
    }

    if ($action === 'add_to_cart' && isLoggedIn()) {
        requireAuth('customer');
        $pid = (int)$_POST['product_id'];
        $qty = max(0.5, (float)$_POST['quantity']);
        $db->prepare("INSERT INTO cart (user_id, product_id, quantity) VALUES (?,?,?)
                      ON CONFLICT(user_id, product_id) DO UPDATE SET quantity = quantity + excluded.quantity")
           ->execute([$_SESSION['user_id'], $pid, $qty]);
        flash('Added to cart!');
        header('Location: ?page=cart'); exit;
    }

    if ($action === 'update_cart' && isLoggedIn()) {
        requireAuth('customer');
        foreach ($_POST['qty'] ?? [] as $cid => $qty) {
            $qty = (float)$qty;
            if ($qty <= 0) {
                $db->prepare("DELETE FROM cart WHERE id = ? AND user_id = ?")->execute([(int)$cid, $_SESSION['user_id']]);
            } else {
                $db->prepare("UPDATE cart SET quantity = ? WHERE id = ? AND user_id = ?")->execute([$qty, (int)$cid, $_SESSION['user_id']]);
            }
        }
        flash('Cart updated.');
        header('Location: ?page=cart'); exit;
    }

    if ($action === 'place_order' && isLoggedIn()) {
        requireAuth('customer');
        $address = trim($_POST['address'] ?? '');
        $city = trim($_POST['city'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $notes = trim($_POST['notes'] ?? '');
        $payment = $_POST['payment_method'] ?? 'cod';

        $items = $db->prepare("SELECT c.*, p.name, p.price, p.stock, p.farmer_id, p.unit
                               FROM cart c JOIN products p ON c.product_id = p.id
                               WHERE c.user_id = ? AND p.is_available = 1");
        $items->execute([$_SESSION['user_id']]);
        $cartItems = $items->fetchAll();
        if (!$cartItems) {
            flash('Cart is empty.', 'danger');
            header('Location: ?page=cart'); exit;
        }

        $total = 0;
        foreach ($cartItems as $it) {
            if ($it['quantity'] > $it['stock']) {
                flash('Insufficient stock for ' . $it['name'], 'danger');
                header('Location: ?page=cart'); exit;
            }
            $total += $it['quantity'] * $it['price'];
        }

        $db->beginTransaction();
        try {
            $db->prepare("INSERT INTO orders (customer_id, total_amount, payment_method, shipping_address, shipping_city, phone, notes)
                          VALUES (?,?,?,?,?,?,?)")
               ->execute([$_SESSION['user_id'], $total, $payment, $address, $city, $phone, $notes]);
            $orderId = $db->lastInsertId();

            $itemStmt = $db->prepare("INSERT INTO order_items (order_id, product_id, farmer_id, quantity, price, subtotal) VALUES (?,?,?,?,?,?)");
            $stockStmt = $db->prepare("UPDATE products SET stock = stock - ? WHERE id = ?");
            foreach ($cartItems as $it) {
                $sub = $it['quantity'] * $it['price'];
                $itemStmt->execute([$orderId, $it['product_id'], $it['farmer_id'], $it['quantity'], $it['price'], $sub]);
                $stockStmt->execute([$it['quantity'], $it['product_id']]);
            }
            $db->prepare("DELETE FROM cart WHERE user_id = ?")->execute([$_SESSION['user_id']]);
            $db->commit();
            flash('Order #' . $orderId . ' placed successfully! Total: ' . money($total));
            header('Location: ?page=orders'); exit;
        } catch (Exception $ex) {
            $db->rollBack();
            flash('Order failed: ' . $ex->getMessage(), 'danger');
            header('Location: ?page=cart'); exit;
        }
    }

    if ($action === 'add_product' && isLoggedIn()) {
        requireAuth('farmer');
        $name = trim($_POST['name'] ?? '');
        $variety = trim($_POST['variety'] ?? '');
        $desc = trim($_POST['description'] ?? '');
        $price = (float)$_POST['price'];
        $stock = (int)$_POST['stock'];
        $unit = $_POST['unit'] ?? 'kg';
        if ($name && $variety && $price > 0) {
            $db->prepare("INSERT INTO products (farmer_id, name, variety, description, price, stock, unit) VALUES (?,?,?,?,?,?,?)")
               ->execute([$_SESSION['user_id'], $name, $variety, $desc, $price, $stock, $unit]);
            flash('Product added successfully!');
        } else {
            flash('Please fill required fields.', 'danger');
        }
        header('Location: ?page=farmer'); exit;
    }

    if ($action === 'update_stock' && isLoggedIn()) {
        requireAuth('farmer');
        $pid = (int)$_POST['product_id'];
        $stock = (int)$_POST['stock'];
        $db->prepare("UPDATE products SET stock = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ? AND farmer_id = ?")
           ->execute([$stock, $pid, $_SESSION['user_id']]);
        flash('Stock updated.');
        header('Location: ?page=farmer'); exit;
    }

    if ($action === 'update_order_status' && isLoggedIn()) {
        requireAuth(['admin', 'farmer']);
        $oid = (int)$_POST['order_id'];
        $status = $_POST['status'] ?? 'pending';
        $allowed = ['pending','confirmed','processing','shipped','delivered','cancelled'];
        if (in_array($status, $allowed)) {
            $db->prepare("UPDATE orders SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?")
               ->execute([$status, $oid]);
            flash('Order status updated.');
        }
        $redirect = currentUser()['role'] === 'admin' ? 'admin' : 'farmer';
        header("Location: ?page=$redirect"); exit;
    }

    if ($action === 'toggle_product' && isLoggedIn()) {
        requireAuth('admin');
        $pid = (int)$_POST['product_id'];
        $db->prepare("UPDATE products SET is_available = 1 - is_available WHERE id = ?")->execute([$pid]);
        flash('Product availability toggled.');
        header('Location: ?page=admin'); exit;
    }

    header('Location: ?page=home'); exit;
}

// ---------- Page Routing ----------
$page = $_GET['page'] ?? 'home';
$user = currentUser();
$db = getDB();

// Data for views
$products = [];
$cart = [];
$orders = [];
$myProducts = [];
$stats = [];

if ($page === 'home' || $page === 'products') {
    $products = $db->query("SELECT p.*, u.name as farmer_name, u.city as farmer_city
                            FROM products p JOIN users u ON p.farmer_id = u.id
                            WHERE p.is_available = 1 AND p.stock > 0
                            ORDER BY p.created_at DESC")->fetchAll();
}

if ($page === 'cart' && isLoggedIn()) {
    requireAuth('customer');
    $stmt = $db->prepare("SELECT c.id as cart_id, c.quantity, p.*, u.name as farmer_name
                          FROM cart c JOIN products p ON c.product_id = p.id
                          JOIN users u ON p.farmer_id = u.id
                          WHERE c.user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $cart = $stmt->fetchAll();
}

if ($page === 'orders' && isLoggedIn()) {
    requireAuth('customer');
    $stmt = $db->prepare("SELECT * FROM orders WHERE customer_id = ? ORDER BY created_at DESC");
    $stmt->execute([$_SESSION['user_id']]);
    $orders = $stmt->fetchAll();
}

if ($page === 'farmer' && isLoggedIn()) {
    requireAuth('farmer');
    $stmt = $db->prepare("SELECT * FROM products WHERE farmer_id = ? ORDER BY created_at DESC");
    $stmt->execute([$_SESSION['user_id']]);
    $myProducts = $stmt->fetchAll();

    $stmt = $db->prepare("SELECT o.*, oi.quantity, oi.price as item_price, p.name as product_name
                          FROM order_items oi
                          JOIN orders o ON oi.order_id = o.id
                          JOIN products p ON oi.product_id = p.id
                          WHERE oi.farmer_id = ?
                          ORDER BY o.created_at DESC LIMIT 20");
    $stmt->execute([$_SESSION['user_id']]);
    $orders = $stmt->fetchAll();
}

if ($page === 'admin' && isLoggedIn()) {
    requireAuth('admin');
    $stats = [
        'users' => $db->query("SELECT COUNT(*) FROM users")->fetchColumn(),
        'farmers' => $db->query("SELECT COUNT(*) FROM users WHERE role='farmer'")->fetchColumn(),
        'customers' => $db->query("SELECT COUNT(*) FROM users WHERE role='customer'")->fetchColumn(),
        'products' => $db->query("SELECT COUNT(*) FROM products")->fetchColumn(),
        'orders' => $db->query("SELECT COUNT(*) FROM orders")->fetchColumn(),
        'revenue' => $db->query("SELECT COALESCE(SUM(total_amount),0) FROM orders WHERE payment_status != 'failed'")->fetchColumn(),
    ];
    $orders = $db->query("SELECT o.*, u.name as customer_name FROM orders o JOIN users u ON o.customer_id = u.id ORDER BY o.created_at DESC LIMIT 30")->fetchAll();
    $products = $db->query("SELECT p.*, u.name as farmer_name FROM products p JOIN users u ON p.farmer_id = u.id ORDER BY p.id DESC")->fetchAll();
    $allUsers = $db->query("SELECT id, name, email, role, city, created_at, is_active FROM users ORDER BY id")->fetchAll();
}

if ($page === 'order_detail' && isLoggedIn()) {
    $oid = (int)($_GET['id'] ?? 0);
    $stmt = $db->prepare("SELECT o.*, u.name as customer_name FROM orders o JOIN users u ON o.customer_id = u.id WHERE o.id = ?");
    $stmt->execute([$oid]);
    $order = $stmt->fetch();
    $items = $db->prepare("SELECT oi.*, p.name, p.variety, u.name as farmer_name
                           FROM order_items oi JOIN products p ON oi.product_id = p.id
                           JOIN users u ON oi.farmer_id = u.id WHERE oi.order_id = ?");
    $items->execute([$oid]);
    $orderItems = $items->fetchAll();
}

// ---------- HTML Output ----------
$flash = getFlash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MTNMANGOES – Online Mango Sales & Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        :root { --mango: #f4a261; --mango-dark: #e76f51; --green: #2a9d8f; }
        body { background: #f8f9fa; min-height: 100vh; display: flex; flex-direction: column; }
        .navbar { background: linear-gradient(135deg, #2a9d8f, #264653) !important; }
        .btn-mango { background: var(--mango); border-color: var(--mango); color: #fff; }
        .btn-mango:hover { background: var(--mango-dark); border-color: var(--mango-dark); color: #fff; }
        .card-product { transition: transform .2s, box-shadow .2s; }
        .card-product:hover { transform: translateY(-4px); box-shadow: 0 8px 20px rgba(0,0,0,.12); }
        .hero { background: linear-gradient(rgba(42,157,143,.85), rgba(38,70,83,.9)), url('https://images.unsplash.com/photo-1553279768-865429fa0078?w=1200') center/cover; color: #fff; padding: 4rem 0; border-radius: 0 0 1.5rem 1.5rem; }
        .stat-card { border-left: 4px solid var(--green); }
        footer { margin-top: auto; background: #264653; color: #fff; }
        .badge-status-pending { background: #ffc107; color: #000; }
        .badge-status-confirmed, .badge-status-processing { background: #0d6efd; }
        .badge-status-shipped { background: #6f42c1; }
        .badge-status-delivered { background: #198754; }
        .badge-status-cancelled { background: #dc3545; }
    </style>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark sticky-top shadow">
    <div class="container">
        <a class="navbar-brand fw-bold" href="?page=home"><i class="bi bi-basket2-fill me-1"></i> MTNMANGOES</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#nav"><span class="navbar-toggler-icon"></span></button>
        <div class="collapse navbar-collapse" id="nav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item"><a class="nav-link" href="?page=home">Home</a></li>
                <li class="nav-item"><a class="nav-link" href="?page=products">Mangoes</a></li>
                <?php if ($user && $user['role'] === 'customer'): ?>
                    <li class="nav-item"><a class="nav-link" href="?page=cart"><i class="bi bi-cart3"></i> Cart</a></li>
                    <li class="nav-item"><a class="nav-link" href="?page=orders">My Orders</a></li>
                <?php endif; ?>
                <?php if ($user && $user['role'] === 'farmer'): ?>
                    <li class="nav-item"><a class="nav-link" href="?page=farmer">Farmer Panel</a></li>
                <?php endif; ?>
                <?php if ($user && $user['role'] === 'admin'): ?>
                    <li class="nav-item"><a class="nav-link" href="?page=admin">Admin Dashboard</a></li>
                <?php endif; ?>
            </ul>
            <ul class="navbar-nav">
                <?php if ($user): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
                            <i class="bi bi-person-circle"></i> <?= e($user['name']) ?> (<?= e($user['role']) ?>)
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><form method="post"><input type="hidden" name="action" value="logout"><button class="dropdown-item">Logout</button></form></li>
                        </ul>
                    </li>
                <?php else: ?>
                    <li class="nav-item"><a class="nav-link" href="?page=login">Login</a></li>
                    <li class="nav-item"><a class="nav-link" href="?page=register">Register</a></li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>

<?php if ($flash): ?>
<div class="container mt-3">
    <div class="alert alert-<?= e($flash['type']) ?> alert-dismissible fade show">
        <?= e($flash['msg']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
</div>
<?php endif; ?>

<main class="flex-grow-1">
<?php
// ===== PAGE CONTENT =====
if ($page === 'home'):
?>
<div class="hero text-center mb-5">
    <div class="container">
        <h1 class="display-4 fw-bold">Fresh Mangoes Direct from Farm</h1>
        <p class="lead">Connecting Pakistani farmers with customers – no middlemen, fair prices, premium quality.</p>
        <a href="?page=products" class="btn btn-mango btn-lg mt-2"><i class="bi bi-shop"></i> Browse Mangoes</a>
    </div>
</div>
<div class="container">
    <h2 class="mb-4 text-center">Featured Mango Varieties</h2>
    <div class="row g-4">
        <?php foreach (array_slice($products, 0, 6) as $p): ?>
        <div class="col-md-4 col-sm-6">
            <div class="card card-product h-100 shadow-sm">
                <div class="card-body">
                    <span class="badge bg-success mb-2"><?= e($p['variety']) ?></span>
                    <h5 class="card-title"><?= e($p['name']) ?></h5>
                    <p class="card-text text-muted small"><?= e(substr($p['description'], 0, 80)) ?>...</p>
                    <p class="mb-1"><strong><?= money($p['price']) ?></strong> / <?= e($p['unit']) ?></p>
                    <p class="small text-muted"><i class="bi bi-geo-alt"></i> <?= e($p['farmer_name']) ?>, <?= e($p['farmer_city']) ?></p>
                    <p class="small">Stock: <?= $p['stock'] ?> <?= e($p['unit']) ?></p>
                    <?php if ($user && $user['role'] === 'customer'): ?>
                    <form method="post" class="d-flex gap-2">
                        <input type="hidden" name="action" value="add_to_cart">
                        <input type="hidden" name="product_id" value="<?= $p['id'] ?>">
                        <input type="number" name="quantity" value="1" min="0.5" step="0.5" class="form-control form-control-sm" style="width:80px">
                        <button class="btn btn-mango btn-sm flex-grow-1"><i class="bi bi-cart-plus"></i> Add</button>
                    </form>
                    <?php elseif (!$user): ?>
                    <a href="?page=login" class="btn btn-outline-secondary btn-sm w-100">Login to Order</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <div class="text-center mt-4">
        <a href="?page=products" class="btn btn-outline-success">View All Products</a>
    </div>
</div>

<?php elseif ($page === 'products'): ?>
<div class="container py-4">
    <h2 class="mb-4">All Available Mangoes</h2>
    <div class="row g-4">
        <?php foreach ($products as $p): ?>
        <div class="col-lg-3 col-md-4 col-sm-6">
            <div class="card card-product h-100 shadow-sm">
                <div class="card-body d-flex flex-column">
                    <span class="badge bg-success mb-2 align-self-start"><?= e($p['variety']) ?></span>
                    <h5 class="card-title"><?= e($p['name']) ?></h5>
                    <p class="card-text text-muted small flex-grow-1"><?= e($p['description']) ?></p>
                    <p class="mb-1"><strong class="text-success"><?= money($p['price']) ?></strong> / <?= e($p['unit']) ?></p>
                    <p class="small text-muted mb-2"><i class="bi bi-person"></i> <?= e($p['farmer_name']) ?> · <?= e($p['farmer_city']) ?></p>
                    <p class="small">In stock: <strong><?= $p['stock'] ?></strong> <?= e($p['unit']) ?></p>
                    <?php if ($user && $user['role'] === 'customer'): ?>
                    <form method="post" class="d-flex gap-2 mt-auto">
                        <input type="hidden" name="action" value="add_to_cart">
                        <input type="hidden" name="product_id" value="<?= $p['id'] ?>">
                        <input type="number" name="quantity" value="1" min="0.5" step="0.5" class="form-control form-control-sm" style="width:70px">
                        <button class="btn btn-mango btn-sm flex-grow-1">Add to Cart</button>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
        <?php if (!$products): ?>
        <div class="col-12"><div class="alert alert-info">No products available at the moment.</div></div>
        <?php endif; ?>
    </div>
</div>

<?php elseif ($page === 'login'): ?>
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-5">
            <div class="card shadow">
                <div class="card-body p-4">
                    <h3 class="text-center mb-4">Login</h3>
                    <form method="post">
                        <input type="hidden" name="action" value="login">
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control" required placeholder="you@example.com">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Password</label>
                            <input type="password" name="password" class="form-control" required>
                        </div>
                        <button class="btn btn-mango w-100">Login</button>
                    </form>
                    <p class="text-center mt-3 mb-0">No account? <a href="?page=register">Register</a></p>
                    <hr>
                    <p class="small text-muted mb-0">Demo: admin@mtnmangoes.com / ali@farmer.com / ahmed@customer.com<br>Password: <code>password123</code></p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php elseif ($page === 'register'): ?>
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card shadow">
                <div class="card-body p-4">
                    <h3 class="text-center mb-4">Create Account</h3>
                    <form method="post">
                        <input type="hidden" name="action" value="register">
                        <div class="mb-3">
                            <label class="form-label">Full Name *</label>
                            <input type="text" name="name" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email *</label>
                            <input type="email" name="email" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Password * (min 6)</label>
                            <input type="password" name="password" class="form-control" required minlength="6">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">I am a</label>
                            <select name="role" class="form-select">
                                <option value="customer">Customer (want to buy mangoes)</option>
                                <option value="farmer">Farmer (want to sell mangoes)</option>
                            </select>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Phone</label>
                                <input type="text" name="phone" class="form-control">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">City</label>
                                <input type="text" name="city" class="form-control">
                            </div>
                        </div>
                        <button class="btn btn-mango w-100">Register</button>
                    </form>
                    <p class="text-center mt-3 mb-0">Already have account? <a href="?page=login">Login</a></p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php elseif ($page === 'cart'): ?>
<div class="container py-4">
    <h2 class="mb-4">Shopping Cart</h2>
    <?php if (!$cart): ?>
        <div class="alert alert-info">Your cart is empty. <a href="?page=products">Browse mangoes</a></div>
    <?php else: ?>
        <form method="post">
            <input type="hidden" name="action" value="update_cart">
            <div class="table-responsive">
                <table class="table table-bordered bg-white">
                    <thead class="table-light">
                        <tr><th>Product</th><th>Price</th><th>Qty</th><th>Subtotal</th><th></th></tr>
                    </thead>
                    <tbody>
                    <?php $grand = 0; foreach ($cart as $c): $sub = $c['quantity'] * $c['price']; $grand += $sub; ?>
                        <tr>
                            <td>
                                <strong><?= e($c['name']) ?></strong><br>
                                <small class="text-muted"><?= e($c['variety']) ?> · <?= e($c['farmer_name']) ?></small>
                            </td>
                            <td><?= money($c['price']) ?> / <?= e($c['unit']) ?></td>
                            <td style="width:120px">
                                <input type="number" name="qty[<?= $c['cart_id'] ?>]" value="<?= $c['quantity'] ?>" min="0" step="0.5" class="form-control form-control-sm">
                            </td>
                            <td><?= money($sub) ?></td>
                            <td class="text-muted small">Set qty 0 to remove</td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr class="table-light"><th colspan="3" class="text-end">Total</th><th colspan="2"><?= money($grand) ?></th></tr>
                    </tfoot>
                </table>
            </div>
            <button class="btn btn-outline-secondary me-2">Update Cart</button>
        </form>

        <div class="card mt-4 shadow-sm">
            <div class="card-header bg-success text-white">Checkout</div>
            <div class="card-body">
                <form method="post">
                    <input type="hidden" name="action" value="place_order">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Shipping Address *</label>
                            <textarea name="address" class="form-control" rows="2" required><?= e($user['address'] ?? '') ?></textarea>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">City *</label>
                            <input type="text" name="city" class="form-control" value="<?= e($user['city'] ?? '') ?>" required>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Phone *</label>
                            <input type="text" name="phone" class="form-control" value="<?= e($user['phone'] ?? '') ?>" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Notes (optional)</label>
                        <input type="text" name="notes" class="form-control" placeholder="Any special instructions">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Payment Method</label>
                        <select name="payment_method" class="form-select" style="max-width:250px">
                            <option value="cod">Cash on Delivery (COD)</option>
                            <option value="stripe">Stripe (Demo)</option>
                            <option value="paypal">PayPal (Demo)</option>
                        </select>
                        <div class="form-text">Real payment gateways can be integrated later (Stripe/PayPal keys).</div>
                    </div>
                    <button class="btn btn-mango btn-lg">Place Order – <?= money($grand) ?></button>
                </form>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php elseif ($page === 'orders'): ?>
<div class="container py-4">
    <h2 class="mb-4">My Orders</h2>
    <?php if (!$orders): ?>
        <div class="alert alert-info">No orders yet.</div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover bg-white shadow-sm">
                <thead class="table-light">
                    <tr><th>#</th><th>Date</th><th>Total</th><th>Status</th><th>Payment</th><th></th></tr>
                </thead>
                <tbody>
                <?php foreach ($orders as $o): ?>
                    <tr>
                        <td>#<?= $o['id'] ?></td>
                        <td><?= date('d M Y H:i', strtotime($o['created_at'])) ?></td>
                        <td><?= money($o['total_amount']) ?></td>
                        <td><span class="badge badge-status-<?= e($o['status']) ?>"><?= e(ucfirst($o['status'])) ?></span></td>
                        <td><?= e(strtoupper($o['payment_method'])) ?> · <?= e($o['payment_status']) ?></td>
                        <td><a href="?page=order_detail&id=<?= $o['id'] ?>" class="btn btn-sm btn-outline-primary">Details</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php elseif ($page === 'order_detail' && isset($order) && $order): ?>
<div class="container py-4">
    <a href="?page=orders" class="btn btn-sm btn-outline-secondary mb-3">← Back</a>
    <div class="card shadow-sm">
        <div class="card-header d-flex justify-content-between">
            <span>Order #<?= $order['id'] ?></span>
            <span class="badge badge-status-<?= e($order['status']) ?>"><?= e(ucfirst($order['status'])) ?></span>
        </div>
        <div class="card-body">
            <div class="row mb-3">
                <div class="col-md-6">
                    <p><strong>Customer:</strong> <?= e($order['customer_name']) ?></p>
                    <p><strong>Address:</strong> <?= e($order['shipping_address']) ?>, <?= e($order['shipping_city']) ?></p>
                    <p><strong>Phone:</strong> <?= e($order['phone']) ?></p>
                </div>
                <div class="col-md-6">
                    <p><strong>Date:</strong> <?= date('d M Y H:i', strtotime($order['created_at'])) ?></p>
                    <p><strong>Payment:</strong> <?= e(strtoupper($order['payment_method'])) ?> (<?= e($order['payment_status']) ?>)</p>
                    <p><strong>Total:</strong> <?= money($order['total_amount']) ?></p>
                </div>
            </div>
            <table class="table table-sm">
                <thead><tr><th>Product</th><th>Farmer</th><th>Qty</th><th>Price</th><th>Subtotal</th></tr></thead>
                <tbody>
                <?php foreach ($orderItems as $it): ?>
                    <tr>
                        <td><?= e($it['name']) ?> (<?= e($it['variety']) ?>)</td>
                        <td><?= e($it['farmer_name']) ?></td>
                        <td><?= $it['quantity'] ?></td>
                        <td><?= money($it['price']) ?></td>
                        <td><?= money($it['subtotal']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php elseif ($page === 'farmer'): ?>
<div class="container py-4">
    <h2 class="mb-4">Farmer Panel – <?= e($user['name']) ?></h2>
    <div class="row">
        <div class="col-lg-4 mb-4">
            <div class="card shadow-sm">
                <div class="card-header bg-success text-white">Add New Product</div>
                <div class="card-body">
                    <form method="post">
                        <input type="hidden" name="action" value="add_product">
                        <div class="mb-2">
                            <label class="form-label">Product Name *</label>
                            <input type="text" name="name" class="form-control" required placeholder="e.g. Chaunsa Mango">
                        </div>
                        <div class="mb-2">
                            <label class="form-label">Variety *</label>
                            <input type="text" name="variety" class="form-control" required placeholder="e.g. Chaunsa">
                        </div>
                        <div class="mb-2">
                            <label class="form-label">Description</label>
                            <textarea name="description" class="form-control" rows="2"></textarea>
                        </div>
                        <div class="row">
                            <div class="col-6 mb-2">
                                <label class="form-label">Price (Rs) *</label>
                                <input type="number" name="price" class="form-control" required min="1" step="1">
                            </div>
                            <div class="col-6 mb-2">
                                <label class="form-label">Stock *</label>
                                <input type="number" name="stock" class="form-control" required min="0">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Unit</label>
                            <select name="unit" class="form-select">
                                <option value="kg">kg</option>
                                <option value="box">box</option>
                                <option value="dozen">dozen</option>
                            </select>
                        </div>
                        <button class="btn btn-mango w-100">Add Product</button>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-lg-8">
            <h5>My Products</h5>
            <div class="table-responsive">
                <table class="table table-sm table-bordered bg-white">
                    <thead class="table-light"><tr><th>Name</th><th>Variety</th><th>Price</th><th>Stock</th><th>Update Stock</th></tr></thead>
                    <tbody>
                    <?php foreach ($myProducts as $p): ?>
                        <tr>
                            <td><?= e($p['name']) ?></td>
                            <td><?= e($p['variety']) ?></td>
                            <td><?= money($p['price']) ?></td>
                            <td><?= $p['stock'] ?> <?= e($p['unit']) ?></td>
                            <td>
                                <form method="post" class="d-flex gap-1">
                                    <input type="hidden" name="action" value="update_stock">
                                    <input type="hidden" name="product_id" value="<?= $p['id'] ?>">
                                    <input type="number" name="stock" value="<?= $p['stock'] ?>" class="form-control form-control-sm" style="width:80px">
                                    <button class="btn btn-sm btn-outline-primary">Save</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$myProducts): ?><tr><td colspan="5" class="text-muted">No products yet. Add one on the left.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>

            <h5 class="mt-4">Recent Orders for My Products</h5>
            <div class="table-responsive">
                <table class="table table-sm table-hover bg-white">
                    <thead class="table-light"><tr><th>Order#</th><th>Product</th><th>Qty</th><th>Amount</th><th>Status</th><th>Date</th></tr></thead>
                    <tbody>
                    <?php foreach ($orders as $o): ?>
                        <tr>
                            <td>#<?= $o['id'] ?></td>
                            <td><?= e($o['product_name']) ?></td>
                            <td><?= $o['quantity'] ?></td>
                            <td><?= money($o['quantity'] * $o['item_price']) ?></td>
                            <td><span class="badge badge-status-<?= e($o['status']) ?>"><?= e(ucfirst($o['status'])) ?></span></td>
                            <td><?= date('d M Y', strtotime($o['created_at'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$orders): ?><tr><td colspan="6" class="text-muted">No orders yet.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php elseif ($page === 'admin'): ?>
<div class="container py-4">
    <h2 class="mb-4">Admin Dashboard</h2>
    <div class="row g-3 mb-4">
        <div class="col-md-2 col-6"><div class="card stat-card shadow-sm"><div class="card-body text-center"><div class="fs-3 fw-bold"><?= $stats['users'] ?></div><div class="small text-muted">Users</div></div></div></div>
        <div class="col-md-2 col-6"><div class="card stat-card shadow-sm"><div class="card-body text-center"><div class="fs-3 fw-bold"><?= $stats['farmers'] ?></div><div class="small text-muted">Farmers</div></div></div></div>
        <div class="col-md-2 col-6"><div class="card stat-card shadow-sm"><div class="card-body text-center"><div class="fs-3 fw-bold"><?= $stats['customers'] ?></div><div class="small text-muted">Customers</div></div></div></div>
        <div class="col-md-2 col-6"><div class="card stat-card shadow-sm"><div class="card-body text-center"><div class="fs-3 fw-bold"><?= $stats['products'] ?></div><div class="small text-muted">Products</div></div></div></div>
        <div class="col-md-2 col-6"><div class="card stat-card shadow-sm"><div class="card-body text-center"><div class="fs-3 fw-bold"><?= $stats['orders'] ?></div><div class="small text-muted">Orders</div></div></div></div>
        <div class="col-md-2 col-6"><div class="card stat-card shadow-sm"><div class="card-body text-center"><div class="fs-4 fw-bold text-success"><?= money($stats['revenue']) ?></div><div class="small text-muted">Revenue</div></div></div></div>
    </div>

    <ul class="nav nav-tabs mb-3" role="tablist">
        <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-orders">Orders</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-products">Products</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-users">Users</button></li>
    </ul>
    <div class="tab-content">
        <div class="tab-pane fade show active" id="tab-orders">
            <div class="table-responsive">
                <table class="table table-sm table-hover bg-white">
                    <thead class="table-light"><tr><th>#</th><th>Customer</th><th>Total</th><th>Status</th><th>Payment</th><th>Date</th><th>Action</th></tr></thead>
                    <tbody>
                    <?php foreach ($orders as $o): ?>
                        <tr>
                            <td>#<?= $o['id'] ?></td>
                            <td><?= e($o['customer_name']) ?></td>
                            <td><?= money($o['total_amount']) ?></td>
                            <td><span class="badge badge-status-<?= e($o['status']) ?>"><?= e(ucfirst($o['status'])) ?></span></td>
                            <td><?= e($o['payment_method']) ?></td>
                            <td><?= date('d M Y', strtotime($o['created_at'])) ?></td>
                            <td>
                                <form method="post" class="d-flex gap-1">
                                    <input type="hidden" name="action" value="update_order_status">
                                    <input type="hidden" name="order_id" value="<?= $o['id'] ?>">
                                    <select name="status" class="form-select form-select-sm" style="width:120px">
                                        <?php foreach (['pending','confirmed','processing','shipped','delivered','cancelled'] as $s): ?>
                                        <option value="<?= $s ?>" <?= $o['status']===$s?'selected':'' ?>><?= ucfirst($s) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button class="btn btn-sm btn-primary">Save</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="tab-pane fade" id="tab-products">
            <div class="table-responsive">
                <table class="table table-sm table-hover bg-white">
                    <thead class="table-light"><tr><th>ID</th><th>Name</th><th>Farmer</th><th>Price</th><th>Stock</th><th>Status</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($products as $p): ?>
                        <tr>
                            <td><?= $p['id'] ?></td>
                            <td><?= e($p['name']) ?> <small class="text-muted">(<?= e($p['variety']) ?>)</small></td>
                            <td><?= e($p['farmer_name']) ?></td>
                            <td><?= money($p['price']) ?></td>
                            <td><?= $p['stock'] ?></td>
                            <td><?= $p['is_available'] ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-secondary">Hidden</span>' ?></td>
                            <td>
                                <form method="post">
                                    <input type="hidden" name="action" value="toggle_product">
                                    <input type="hidden" name="product_id" value="<?= $p['id'] ?>">
                                    <button class="btn btn-sm btn-outline-secondary"><?= $p['is_available'] ? 'Hide' : 'Show' ?></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="tab-pane fade" id="tab-users">
            <div class="table-responsive">
                <table class="table table-sm table-hover bg-white">
                    <thead class="table-light"><tr><th>ID</th><th>Name</th><th>Email</th><th>Role</th><th>City</th><th>Joined</th></tr></thead>
                    <tbody>
                    <?php foreach ($allUsers as $u): ?>
                        <tr>
                            <td><?= $u['id'] ?></td>
                            <td><?= e($u['name']) ?></td>
                            <td><?= e($u['email']) ?></td>
                            <td><span class="badge bg-<?= $u['role']==='admin'?'danger':($u['role']==='farmer'?'success':'primary') ?>"><?= e($u['role']) ?></span></td>
                            <td><?= e($u['city']) ?></td>
                            <td><?= date('d M Y', strtotime($u['created_at'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php else: ?>
<div class="container py-5 text-center">
    <h2>Page not found</h2>
    <a href="?page=home" class="btn btn-mango">Go Home</a>
</div>
<?php endif; ?>
</main>

<footer class="py-4 mt-5">
    <div class="container text-center">
        <p class="mb-1 fw-bold">MTNMANGOES – Online Mango Sales and Management System</p>
        <p class="small mb-0 opacity-75">Final Year Project · BS IT · Govt. Graduate College Muzaffargarh · <?= date('Y') ?></p>
        <p class="small opacity-75">Submitted by Muhammad Mubeen (2022-GCBM-559) · Supervisor: Arslan Munir</p>
    </div>
</footer>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
