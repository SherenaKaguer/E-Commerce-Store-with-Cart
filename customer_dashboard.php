<?php
// ════════════════════════════════════════════════
// customer_dashboard.php — Customer's Main Shop View
// ════════════════════════════════════════════════
require_once 'auth.php';
require_once 'db.php';
app_session_start();
app_no_cache();
app_require_login('login.php');

if (app_is_admin()) {
    app_redirect('admin_dashboard.php');
}

$user_id  = (int)$_SESSION['user_id'];
$username = $_SESSION['username'];
$is_new_user = isset($_SESSION['new_user']);
unset($_SESSION['new_user']); // Only show "Welcome" once

// ── Image helper ──────────────────────────────
function product_image_src(array $row): string {
    $fallback = 'https://images.unsplash.com/photo-1560393464-5c69a73c5770?auto=format&fit=crop&w=400&q=80';
    $raw = trim((string)($row['image_url'] ?? ''));
    if ($raw === '') return $fallback;
    return $raw;
}

// If the user built a cart/wishlist while logged out, merge it into their DB after login.
if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) $_SESSION['cart'] = [];
if (!isset($_SESSION['wishlist']) || !is_array($_SESSION['wishlist'])) $_SESSION['wishlist'] = [];

// Merge Cart
if (!empty($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $item) {
        $product_id = (int)$item['product_id'];
        $quantity = max(1, (int)$item['quantity']);
        $check = mysqli_query($conn, "SELECT id, quantity FROM cart_items WHERE user_id = $user_id AND product_id = $product_id");
        if ($check && mysqli_num_rows($check) > 0) {
            mysqli_query($conn, "UPDATE cart_items SET quantity = quantity + $quantity WHERE user_id = $user_id AND product_id = $product_id");
        } else {
            mysqli_query($conn, "INSERT INTO cart_items (user_id, product_id, quantity) VALUES ($user_id, $product_id, $quantity)");
        }
    }
    $_SESSION['cart'] = [];
}

// Merge Wishlist
if (!empty($_SESSION['wishlist'])) {
    foreach ($_SESSION['wishlist'] as $product_id) {
        $product_id = (int)$product_id;
        $check = mysqli_query($conn, "SELECT id FROM wishlist WHERE user_id = $user_id AND product_id = $product_id");
        if ($check && mysqli_num_rows($check) == 0) {
            mysqli_query($conn, "INSERT INTO wishlist (user_id, product_id) VALUES ($user_id, $product_id)");
        }
    }
    $_SESSION['wishlist'] = [];
}

// ── Load cart count & total ───────────────────
$cart_count = 0; $cart_total = 0;
$cart_res = mysqli_query($conn, "SELECT c.quantity, p.price FROM cart_items c JOIN products p ON c.product_id=p.id WHERE c.user_id=$user_id");
while ($i = mysqli_fetch_assoc($cart_res)) {
    $cart_count += $i['quantity'];
    $cart_total += $i['price'] * $i['quantity'];
}

// ── Wishlist count ────────────────────────────
$wish_res = mysqli_query($conn, "SELECT COUNT(*) as count FROM wishlist WHERE user_id=$user_id");
$wish_count = (int)mysqli_fetch_assoc($wish_res)['count'];

// ── Orders (Payments) made ───────────────────
$order_res = mysqli_query($conn, "SELECT COUNT(*) as count FROM orders WHERE user_id=$user_id AND status IN ('paid','shipped','completed')");
$payment_count = (int)mysqli_fetch_assoc($order_res)['count'];

// ── Categories & products ─────────────────────
$cat_res  = mysqli_query($conn, "SELECT * FROM categories ORDER BY name");
$categories = mysqli_fetch_all($cat_res, MYSQLI_ASSOC);
$cat_filter = isset($_GET['category']) ? (int)$_GET['category'] : 0;

$where = $cat_filter > 0 ? "WHERE p.category_id=$cat_filter" : '';
$prod_res = mysqli_query($conn,
    "SELECT p.*,c.name as category_name
     FROM products p LEFT JOIN categories c ON p.category_id=c.id $where");
$products = mysqli_fetch_all($prod_res, MYSQLI_ASSOC);

// ── Ratings (Guest View logic synced) ──────────
$ratings = [];
$rat_res = mysqli_query($conn, "SELECT product_id,AVG(rating) as avg_rating,COUNT(*) as review_count FROM reviews GROUP BY product_id");
if ($rat_res) while ($r = mysqli_fetch_assoc($rat_res)) $ratings[$r['product_id']] = $r;

// ── POST Handlers (Add to Cart / Wishlist) ───
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $pid = (int)($_POST['product_id'] ?? 0);

    if ($action === 'add_to_cart') {
        $qty = max(1, (int)$_POST['quantity']);
        $check = mysqli_query($conn, "SELECT id,quantity FROM cart_items WHERE user_id=$user_id AND product_id=$pid");
        if (mysqli_num_rows($check) > 0) {
            $row = mysqli_fetch_assoc($check);
            $nq = $row['quantity'] + $qty;
            mysqli_query($conn, "UPDATE cart_items SET quantity=$nq WHERE id={$row['id']}");
        } else {
            mysqli_query($conn, "INSERT INTO cart_items (user_id,product_id,quantity) VALUES ($user_id,$pid,$qty)");
        }
        $_SESSION['success'] = 'Added to cart!';
        header('Location: customer_dashboard.php'); exit();
    }

    if ($action === 'add_to_wishlist') {
        $check = mysqli_query($conn, "SELECT id FROM wishlist WHERE user_id=$user_id AND product_id=$pid");
        if (mysqli_num_rows($check) == 0) {
            mysqli_query($conn, "INSERT INTO wishlist (user_id,product_id) VALUES ($user_id,$pid)");
            $_SESSION['success'] = 'Added to wishlist!';
        } else {
            $_SESSION['error'] = 'Already in wishlist!';
        }
        header('Location: customer_dashboard.php'); exit();
    }
}

$success = $_SESSION['success'] ?? ''; unset($_SESSION['success']);
$error   = $_SESSION['error']   ?? ''; unset($_SESSION['error']);
$current_cat_name = 'All Products';
if ($cat_filter > 0) {
    foreach ($categories as $c) if ((int)$c['id'] === $cat_filter) { $current_cat_name = $c['name']; break; }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shop - ShopEasy</title>
    <link rel="stylesheet" href="style.css">
</head>

<body>

    <header class="site-header">
        <div class="header-inner">
            <a href="customer_dashboard.php" class="logo-link">🛒 ShopEasy</a>
            <nav class="main-nav">
                <span class="nav-pill"><?= $is_new_user ? 'Welcome' : 'Welcome back' ?>,
                    <?= htmlspecialchars($username) ?></span>
                <a href="logout.php" class="nav-pill danger-pill">Logout</a>
            </nav>
        </div>
    </header>

    <?php if ($success): ?>
    <div class="flash success">✅ <?= htmlspecialchars($success) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
    <div class="flash error">❌ <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <!-- ═══ ACCOUNT SUMMARY ══════════════════════════ -->
    <section class="hero" style="padding: 40px 20px; margin-bottom: 20px;">
        <h1>Explore Our Products</h1>
        <div
            style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 20px; margin-top: 30px; max-width: 900px; margin-left: auto; margin-right: auto;">
            <a href="cart.php" style="text-decoration: none;">
                <div
                    style="background: rgba(255,255,255,0.1); padding: 20px; border-radius: 20px; border: 1px solid var(--border);">
                    <span
                        style="display: block; font-size: 0.8rem; text-transform: uppercase; color: var(--text-muted);">Cart</span>
                    <span style="font-size: 1.5rem; font-weight: 800; color: #fff;"><?= $cart_count ?> items</span>
                </div>
            </a>
            <a href="wishlist.php" style="text-decoration: none;">
                <div
                    style="background: rgba(255,255,255,0.1); padding: 20px; border-radius: 20px; border: 1px solid var(--border);">
                    <span
                        style="display: block; font-size: 0.8rem; text-transform: uppercase; color: var(--text-muted);">Wishlist</span>
                    <span style="font-size: 1.5rem; font-weight: 800; color: #fff;"><?= $wish_count ?> items</span>
                </div>
            </a>
            <div
                style="background: rgba(255,255,255,0.1); padding: 20px; border-radius: 20px; border: 1px solid var(--border);">
                <span
                    style="display: block; font-size: 0.8rem; text-transform: uppercase; color: var(--text-muted);">Payments</span>
                <span style="font-size: 1.5rem; font-weight: 800; color: #fff;"><?= $payment_count ?> made</span>
            </div>
        </div>
    </section>

    <div class="shop-layout">
        <aside class="sidebar">
            <div class="sidebar-block">
                <h3>📁 Categories</h3>
                <nav class="cat-nav">
                    <a href="customer_dashboard.php" class="cat-link <?= $cat_filter == 0 ? 'active' : '' ?>">All
                        Products</a>
                    <?php foreach ($categories as $cat): ?>
                    <a href="?category=<?= $cat['id'] ?>"
                        class="cat-link <?= $cat_filter == (int)$cat['id'] ? 'active' : '' ?>">
                        <?= htmlspecialchars($cat['name']) ?>
                    </a>
                    <?php endforeach; ?>
                </nav>
            </div>
        </aside>

        <main class="catalog">
            <div class="catalog-head">
                <h2>📦 <?= htmlspecialchars($current_cat_name) ?></h2>
                <span class="muted"><?= count($products) ?> items</span>
            </div>

            <div class="product-grid">
                <?php foreach ($products as $p):
                $rating = $ratings[$p['id']] ?? ['avg_rating' => 0, 'review_count' => 0];
                $img    = product_image_src($p);
                // Use 'stock_quantity' or 'stock' for consistency with index.php
                $in_stock = ($p['stock_quantity'] ?? $p['stock'] ?? 0) > 0;
            ?>
                <article class="product-card">
                    <div class="product-img-wrap">
                        <img src="<?= htmlspecialchars($img) ?>" alt="<?= htmlspecialchars($p['name']) ?>"
                            loading="lazy">
                        <span class="stock-tag <?= $in_stock ? 'in' : 'out' ?>">
                            <?= $in_stock ? '✓ In Stock' : '✗ Out of Stock' ?>
                        </span>
                    </div>
                    <div class="product-body">
                        <span class="cat-badge"><?= htmlspecialchars($p['category_name'] ?? 'Uncategorized') ?></span>
                        <h3><?= htmlspecialchars($p['name']) ?></h3>
                        <div class="stars">
                            <?php $avg = round($rating['avg_rating']); for ($i=1;$i<=5;$i++) echo $i<=$avg ? '★' : '☆'; ?>
                            <span class="muted small">(<?= $rating['review_count'] ?>)</span>
                        </div>
                        <p class="product-desc"><?= htmlspecialchars(substr($p['description'] ?? '', 0, 80)) ?>...</p>
                        <div class="product-footer">
                            <span class="price">$<?= number_format($p['price'], 2) ?></span>
                            <div class="product-actions">
                                <?php if ($in_stock): ?>
                                <form method="POST" class="inline-form">
                                    <input type="hidden" name="action" value="add_to_cart">
                                    <input type="hidden" name="product_id" value="<?= $p['id'] ?>">
                                    <input class="qty-mini" type="number" name="quantity" value="1" min="1">
                                    <button type="submit" class="btn-sm primary">Add</button>
                                </form>
                                <form method="POST" class="inline-form">
                                    <input type="hidden" name="action" value="add_to_wishlist">
                                    <input type="hidden" name="product_id" value="<?= $p['id'] ?>">
                                    <button type="submit" class="btn-sm">❤️</button>
                                </form>
                                <?php else: ?>
                                <span class="muted">Unavailable</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </article>
                <?php endforeach; ?>
            </div>
        </main>
    </div>

    <footer class="site-footer">
        <p>© <?= date('Y') ?> ShopEasy. All rights reserved.</p>
    </footer>

</body>

</html>