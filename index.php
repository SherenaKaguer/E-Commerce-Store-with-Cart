<?php
// ════════════════════════════════════════════════
// index.php  — Public store front (Guest View)
// ════════════════════════════════════════════════
require_once 'auth.php';
require_once 'db.php';
app_session_start();
app_no_cache();

// If already logged in, redirect to correct dashboard (except for admin viewing store)
if (app_is_logged_in()) {
    // Allow admin to view store without redirect
    if ($_SESSION['role'] !== 'admin') {
        app_redirect_to_dashboard();
    }
    $is_admin_viewing = true;
}


// ── Image helper ──────────────────────────────
function product_image_src(array $row): string {
    $fallback = 'https://images.unsplash.com/photo-1560393464-5c69a73c5770?auto=format&fit=crop&w=400&q=80';
    $raw = trim((string)($row['image_url'] ?? ''));
    if ($raw === '') return $fallback;
    return $raw;
}

// ── Guest Cart & Wishlist count ───────────
if (!isset($_SESSION['cart'])) $_SESSION['cart'] = [];
if (!isset($_SESSION['wishlist'])) $_SESSION['wishlist'] = [];

$guest_cart_count = 0;
foreach ($_SESSION['cart'] as $item) $guest_cart_count += $item['quantity'];
$guest_wish_count = count($_SESSION['wishlist']);

// ── Categories & products ─────────────────────
$cat_res  = mysqli_query($conn, "SELECT * FROM categories ORDER BY name");
$categories = mysqli_fetch_all($cat_res, MYSQLI_ASSOC);
$cat_filter = isset($_GET['category']) ? (int)$_GET['category'] : 0;

$where = $cat_filter > 0 ? "WHERE p.category_id=$cat_filter" : '';
$prod_res = mysqli_query($conn,
    "SELECT p.*,c.name as category_name
     FROM products p LEFT JOIN categories c ON p.category_id=c.id $where");
$products = mysqli_fetch_all($prod_res, MYSQLI_ASSOC);

// ── Ratings (Guest View) ─────────────────────
$ratings = [];
$rat_res = mysqli_query($conn, "SELECT product_id,AVG(rating) as avg_rating,COUNT(*) as review_count FROM reviews GROUP BY product_id");
if ($rat_res) while ($r = mysqli_fetch_assoc($rat_res)) $ratings[$r['product_id']] = $r;

// ── Guest POST Handlers ──────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $pid = (int)($_POST['product_id'] ?? 0);

    if ($action === 'guest_add_to_cart') {
        $qty = max(1, (int)$_POST['quantity']);
        $found = false;
        foreach ($_SESSION['cart'] as &$item) {
            if ($item['product_id'] == $pid) { $item['quantity'] += $qty; $found = true; break; }
        }
        unset($item);
        if (!$found) $_SESSION['cart'][] = ['product_id' => $pid, 'quantity' => $qty];
        header('Location: index.php?added=cart');
        exit();
    }

    if ($action === 'guest_add_to_wishlist') {
        if (!in_array($pid, $_SESSION['wishlist'])) {
            $_SESSION['wishlist'][] = $pid;
        }
        header('Location: index.php?added=wishlist');
        exit();
    }
}

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
    <title>ShopEasy – Premium Store</title>
    <link rel="stylesheet" href="style.css">
</head>

<body>

    <header class="site-header">
        <div class="header-inner">
            <a href="index.php" class="logo-link">🛒 ShopEasy</a>
            <nav class="main-nav">
                <a href="login.php" class="nav-pill">Login</a>
                <a href="register.php" class="nav-pill">Register</a>
                <a href="cart.php" class="nav-pill cart-pill">
                    Cart <?php if ($guest_cart_count > 0): ?><span
                        class="cart-badge"><?= $guest_cart_count ?></span><?php endif; ?>
                </a>
            </nav>
        </div>
    </header>

    <section class="hero">
        <h1>Quality Products. Best Prices.</h1>
        <p>Discover our exclusive collection of premium electronics, fashion, and books.</p>
        <a href="register.php" class="btn-hero">Join Now & Save 10%</a>
    </section>

    <div class="shop-layout">
        <aside class="sidebar">
            <div class="sidebar-block">
                <h3>📁 Categories</h3>
                <nav class="cat-nav">
                    <a href="index.php" class="cat-link <?= $cat_filter == 0 ? 'active' : '' ?>">All Products</a>
                    <?php foreach ($categories as $cat): ?>
                    <a href="?category=<?= $cat['id'] ?>"
                        class="cat-link <?= $cat_filter == (int)$cat['id'] ? 'active' : '' ?>">
                        <?= htmlspecialchars($cat['name']) ?>
                    </a>
                    <?php endforeach; ?>
                </nav>
            </div>

            <div class="sidebar-block">
                <h3>✨ Why Shop With Us?</h3>
                <ul style="list-style: none; padding: 0; font-size: 0.9rem; color: var(--text-muted);">
                    <li style="margin-bottom: 10px;">✅ Fast Delivery</li>
                    <li style="margin-bottom: 10px;">🛡️ Secure Payments</li>
                    <li style="margin-bottom: 10px;">💎 Premium Quality</li>
                </ul>
            </div>
        </aside>

        <main class="catalog">
            <div class="catalog-head">
                <h2>📦 <?= htmlspecialchars($current_cat_name) ?></h2>
                <span class="muted"><?= count($products) ?> items available</span>
            </div>

            <div class="product-grid">
                <?php foreach ($products as $p):
                $rating = $ratings[$p['id']] ?? ['avg_rating' => 0, 'review_count' => 0];
                $img    = product_image_src($p);
                $in_stock = ($p['stock_quantity'] ?? 0) > 0;
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
                                <form method="POST" class="inline-form">
                                    <input type="hidden" name="action" value="guest_add_to_cart">
                                    <input type="hidden" name="product_id" value="<?= $p['id'] ?>">
                                    <input class="qty-mini" type="number" name="quantity" value="1" min="1">
                                    <button type="submit" class="btn-sm primary">Add</button>
                                </form>
                                <form method="POST" class="inline-form">
                                    <input type="hidden" name="action" value="guest_add_to_wishlist">
                                    <input type="hidden" name="product_id" value="<?= $p['id'] ?>">
                                    <button type="submit" class="btn-sm">❤️</button>
                                </form>
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