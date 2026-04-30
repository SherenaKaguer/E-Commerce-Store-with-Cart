<?php
require_once 'auth.php';
require_once 'db.php';
app_session_start();
app_no_cache();

$is_logged_in = app_is_logged_in();
$user_id = $_SESSION['user_id'] ?? null;

// Handle updates/removals
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update' && $is_logged_in) {
        foreach ($_POST['quantity'] as $cid => $qty) {
            $q = max(1, (int)$qty);
            mysqli_query($conn, "UPDATE cart_items SET quantity=$q WHERE id=" . (int)$cid . " AND user_id=$user_id");
        }
        $_SESSION['success'] = "Cart updated.";
    }
    
    if ($action === 'remove' && $is_logged_in) {
        $cid = (int)$_POST['cart_id'];
        mysqli_query($conn, "DELETE FROM cart_items WHERE id=$cid AND user_id=$user_id");
        $_SESSION['success'] = "Item removed.";
    }

    if ($action === 'checkout' && $is_logged_in) {
        header("Location: payment.php");
        exit();
    }
    
    header("Location: cart.php");
    exit();
}

$cart_items = [];
$total = 0;

if ($is_logged_in) {
    $res = mysqli_query($conn, "SELECT c.*, p.name, p.price, p.image_url FROM cart_items c JOIN products p ON c.product_id = p.id WHERE c.user_id = $user_id");
    while ($row = mysqli_fetch_assoc($res)) {
        $cart_items[] = $row;
        $total += $row['price'] * $row['quantity'];
    }
} else {
    // Guest cart from session
    if (!empty($_SESSION['cart'])) {
        foreach ($_SESSION['cart'] as $item) {
            $pid = (int)$item['product_id'];
            $res = mysqli_query($conn, "SELECT * FROM products WHERE id = $pid");
            if ($p = mysqli_fetch_assoc($res)) {
                $p['quantity'] = $item['quantity'];
                $p['id'] = 0; // No cart_item id for guests
                $cart_items[] = $p;
                $total += $p['price'] * $item['quantity'];
            }
        }
    }
}

$success = $_SESSION['success'] ?? ''; unset($_SESSION['success']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your Cart - ShopEasy</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <header class="site-header">
        <div class="header-inner">
            <a href="index.php" class="logo-link">🛒 ShopEasy</a>
            <nav class="main-nav">
                <a href="index.php" class="nav-pill">Store</a>
                <?php if ($is_logged_in): ?>
                    <a href="customer_dashboard.php" class="nav-pill">Account</a>
                <?php else: ?>
                    <a href="login.php" class="nav-pill">Login</a>
                <?php endif; ?>
            </nav>
        </div>
    </header>

    <main class="page-shell" style="align-items: flex-start; display: block;">
        <div class="hero" style="padding: 40px; margin-top: 0;">
            <h1>Shopping Cart</h1>
            <p>Review your items before checkout.</p>
        </div>

        <?php if ($success): ?>
            <div class="flash success">✅ <?= $success ?></div>
        <?php endif; ?>

        <div class="auth-card" style="max-width: 1000px; margin: 0 auto;">
            <?php if (empty($cart_items)): ?>
                <div style="text-align: center; padding: 40px;">
                    <p style="font-size: 1.2rem; color: var(--text-muted);">Your cart is empty.</p>
                    <a href="index.php" class="btn" style="display: inline-block; margin-top: 20px; width: auto; padding: 12px 30px;">Start Shopping</a>
                </div>
            <?php else: ?>
                <form method="POST">
                    <input type="hidden" name="action" value="update">
                    <div style="overflow-x: auto;">
                        <table style="width: 100%; border-collapse: collapse; margin-bottom: 30px;">
                            <thead>
                                <tr style="border-bottom: 1px solid var(--border); text-align: left; color: var(--text-muted);">
                                    <th style="padding: 15px;">Product</th>
                                    <th style="padding: 15px;">Price</th>
                                    <th style="padding: 15px;">Quantity</th>
                                    <th style="padding: 15px;">Subtotal</th>
                                    <th style="padding: 15px;"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($cart_items as $item): ?>
                                    <tr style="border-bottom: 1px solid var(--border);">
                                        <td style="padding: 15px;">
                                            <div style="display: flex; align-items: center; gap: 15px;">
                                                <img src="<?= htmlspecialchars($item['image_url']) ?>" style="width: 60px; height: 60px; object-fit: cover; border-radius: 10px;">
                                                <strong><?= htmlspecialchars($item['name']) ?></strong>
                                            </div>
                                        </td>
                                        <td style="padding: 15px;">$<?= number_format($item['price'], 2) ?></td>
                                        <td style="padding: 15px;">
                                            <?php if ($is_logged_in): ?>
                                                <input type="number" name="quantity[<?= $item['id'] ?>]" value="<?= $item['quantity'] ?>" min="1" class="qty-mini" style="width: 70px; padding: 8px;">
                                            <?php else: ?>
                                                <?= $item['quantity'] ?>
                                            <?php endif; ?>
                                        </td>
                                        <td style="padding: 15px; font-weight: bold;">$<?= number_format($item['price'] * $item['quantity'], 2) ?></td>
                                        <td style="padding: 15px; text-align: right;">
                                            <?php if ($is_logged_in): ?>
                                                <button type="submit" name="action" value="remove" onclick="this.form.cart_id.value='<?= $item['id'] ?>'" class="icon-btn">🗑</button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <input type="hidden" name="cart_id" value="">
                    
                    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 20px;">
                        <div>
                            <?php if ($is_logged_in): ?>
                                <button type="submit" class="btn" style="width: auto; background: rgba(255,255,255,0.1);">Update Cart</button>
                            <?php endif; ?>
                        </div>
                        <div style="text-align: right;">
                            <p style="font-size: 1.2rem; margin-bottom: 10px;">Total: <strong style="color: var(--primary); font-size: 1.8rem;">$<?= number_format($total, 2) ?></strong></p>
                            <?php if ($is_logged_in): ?>
                                <button type="submit" name="action" value="checkout" class="btn" style="width: 250px; background: var(--success);">Proceed to Checkout</button>
                            <?php else: ?>
                                <a href="login.php?next=checkout" class="btn" style="display: inline-block; width: 250px;">Login to Checkout</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </main>

    <footer class="site-footer">
        <p>© <?= date('Y') ?> ShopEasy. All rights reserved.</p>
    </footer>
</body>
</html>