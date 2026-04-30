<?php
require_once 'auth.php';
require_once 'db.php';
app_session_start();
app_no_cache();

app_require_login('login.php');

$user_id = (int)$_SESSION['user_id'];

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $pid = (int)($_POST['product_id'] ?? 0);
    $wid = (int)($_POST['wish_id'] ?? 0);

    if ($action === 'add_to_cart') {
        // Check if already in cart
        $check = mysqli_query($conn, "SELECT id, quantity FROM cart_items WHERE user_id=$user_id AND product_id=$pid");
        if (mysqli_num_rows($check) > 0) {
            mysqli_query($conn, "UPDATE cart_items SET quantity = quantity + 1 WHERE user_id=$user_id AND product_id=$pid");
        } else {
            mysqli_query($conn, "INSERT INTO cart_items (user_id, product_id, quantity) VALUES ($user_id, $pid, 1)");
        }
        // Optionally remove from wishlist after adding to cart
        mysqli_query($conn, "DELETE FROM wishlist WHERE user_id=$user_id AND product_id=$pid");
        $_SESSION['success'] = "Item added to cart!";
    }

    if ($action === 'remove') {
        mysqli_query($conn, "DELETE FROM wishlist WHERE id=$wid AND user_id=$user_id");
        $_SESSION['success'] = "Item removed from wishlist.";
    }

    header("Location: wishlist.php");
    exit();
}

$wish_items = [];
$res = mysqli_query($conn, "SELECT w.id as wish_id, p.* FROM wishlist w JOIN products p ON w.product_id = p.id WHERE w.user_id = $user_id");
while ($row = mysqli_fetch_assoc($res)) {
    $wish_items[] = $row;
}

$success = $_SESSION['success'] ?? ''; unset($_SESSION['success']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Wishlist - ShopEasy</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <header class="site-header">
        <div class="header-inner">
            <a href="customer_dashboard.php" class="logo-link">🛒 ShopEasy</a>
            <nav class="main-nav">
                <a href="customer_dashboard.php" class="nav-pill">Store</a>
                <a href="cart.php" class="nav-pill">Cart</a>
                <a href="logout.php" class="nav-pill danger-pill">Logout</a>
            </nav>
        </div>
    </header>

    <main class="page-shell" style="align-items: flex-start; display: block;">
        <div class="hero" style="padding: 40px; margin-top: 0;">
            <h1>My Wishlist</h1>
            <p>Save items you love for later.</p>
        </div>

        <?php if ($success): ?>
            <div class="flash success">✅ <?= $success ?></div>
        <?php endif; ?>

        <div class="product-grid" style="max-width: 1200px; margin: 0 auto;">
            <?php if (empty($wish_items)): ?>
                <div class="auth-card" style="grid-column: 1 / -1; text-align: center; padding: 40px;">
                    <p style="color: var(--text-muted);">Your wishlist is empty.</p>
                    <a href="customer_dashboard.php" class="btn" style="display: inline-block; margin-top: 20px; width: auto; padding: 12px 30px;">Browse Products</a>
                </div>
            <?php else: ?>
                <?php foreach ($wish_items as $p): ?>
                    <article class="product-card">
                        <div class="product-img-wrap">
                            <img src="<?= htmlspecialchars($p['image_url']) ?>" alt="<?= htmlspecialchars($p['name']) ?>">
                        </div>
                        <div class="product-body">
                            <h3><?= htmlspecialchars($p['name']) ?></h3>
                            <p class="price">$<?= number_format($p['price'], 2) ?></p>
                            <div class="product-footer" style="margin-top: 20px;">
                                <form method="POST" style="display: flex; gap: 10px;">
                                    <input type="hidden" name="product_id" value="<?= $p['id'] ?>">
                                    <input type="hidden" name="wish_id" value="<?= $p['wish_id'] ?>">
                                    <button type="submit" name="action" value="add_to_cart" class="btn primary" style="flex: 2;">Add to Cart</button>
                                    <button type="submit" name="action" value="remove" class="btn" style="flex: 1; background: rgba(255,255,255,0.1);">🗑</button>
                                </form>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </main>

    <footer class="site-footer">
        <p>© <?= date('Y') ?> ShopEasy. All rights reserved.</p>
    </footer>
</body>
</html>