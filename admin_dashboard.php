<?php
session_start();

require_once 'db.php';

// Check if user is admin
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: login.php');
    exit();
}

$admin_name = htmlspecialchars($_SESSION['username'] ?? 'Admin');

// ── Email function ──────────────────────────────
function sendOrderEmail($to, $subject, $message) {
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: ShopEasy <noreply@shopeasy.com>" . "\r\n";
    return mail($to, $subject, $message, $headers);
}

// ── Handle all POST actions ─────────────────────
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // PRODUCT: Add
    if ($action === 'add_product') {
        $name = mysqli_real_escape_string($conn, $_POST['name'] ?? '');
        $price = (float)($_POST['price'] ?? 0);
        $stock = (int)($_POST['stock'] ?? 0);
        $category_id = (int)($_POST['category_id'] ?? 0);
        $description = mysqli_real_escape_string($conn, $_POST['description'] ?? '');
        $image_url = mysqli_real_escape_string($conn, $_POST['image_url'] ?? '');
        $slug = strtolower(str_replace(' ', '-', $name));
        
        if ($name && $price > 0) {
            mysqli_query($conn, "INSERT INTO products (name, slug, price, stock_quantity, category_id, description, image_url) 
                                VALUES ('$name', '$slug', $price, $stock, $category_id, '$description', '$image_url')");
            $success = "✅ Product '$name' added successfully!";
        } else {
            $error = "❌ Product name and price are required.";
        }
    }
    
    // PRODUCT: Update Price
    if ($action === 'update_price') {
        $id = (int)($_POST['product_id'] ?? 0);
        $price = (float)($_POST['price'] ?? 0);
        mysqli_query($conn, "UPDATE products SET price=$price WHERE id=$id");
        $success = "✅ Price updated!";
    }
    
    // PRODUCT: Update Stock
    if ($action === 'update_stock') {
        $id = (int)($_POST['product_id'] ?? 0);
        $stock = (int)($_POST['stock'] ?? 0);
        mysqli_query($conn, "UPDATE products SET stock_quantity=$stock WHERE id=$id");
        $success = "✅ Stock updated!";
    }
    
    // PRODUCT: Delete
    if ($action === 'delete_product') {
        $id = (int)($_POST['product_id'] ?? 0);
        mysqli_query($conn, "DELETE FROM products WHERE id=$id");
        $success = "✅ Product deleted!";
    }
    
    // ORDER: Update Status & Send Email
    if ($action === 'update_order_status') {
        $order_id = (int)($_POST['order_id'] ?? 0);
        $status = mysqli_real_escape_string($conn, $_POST['status'] ?? 'pending');
        $allowed = ['pending', 'processing', 'paid', 'shipped', 'delivered', 'completed', 'cancelled'];
        
        if (in_array($status, $allowed)) {
            // Get order and user details
            $order_query = mysqli_query($conn, "SELECT o.*, u.email, u.username FROM orders o JOIN users u ON o.user_id = u.id WHERE o.id = $order_id");
            $order = mysqli_fetch_assoc($order_query);
            
            mysqli_query($conn, "UPDATE orders SET status='$status' WHERE id=$order_id");
            
            // Send email notification
            if ($order) {
                $subject = "Order #{$order['order_number']} Status Update";
                $message = "
                <html>
                <head><title>Order Status Update</title></head>
                <body style='font-family: Arial, sans-serif;'>
                    <h2>Hello {$order['username']},</h2>
                    <p>Your order <strong>#{$order['order_number']}</strong> has been updated to: <strong style='color: #7e8bff;'>" . strtoupper($status) . "</strong></p>
                    <p>Order Total: <strong>$" . number_format($order['total_amount'], 2) . "</strong></p>";
                
                if ($status == 'shipped') {
                    $tracking = "TRK" . rand(100000, 999999);
                    mysqli_query($conn, "UPDATE orders SET tracking_number='$tracking' WHERE id=$order_id");
                    $message .= "<p>Tracking Number: <strong>$tracking</strong></p>
                                <p>Your order is on its way! Estimated delivery: 3-5 business days.</p>";
                } elseif ($status == 'paid') {
                    $message .= "<p>✅ Payment confirmed! We're processing your order.</p>";
                } elseif ($status == 'cancelled') {
                    $message .= "<p>⚠️ Your order has been cancelled. Please contact support if this was a mistake.</p>";
                } elseif ($status == 'completed') {
                    $message .= "<p>🎉 Thank you for shopping with us! We hope you enjoy your purchase.</p>";
                }
                
                $message .= "<p>Thank you for shopping with ShopEasy!</p>
                            <a href='customer_dashboard.php' style='background: #7e8bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>View Your Orders</a>
                            </body></html>";
                
                sendOrderEmail($order['email'], $subject, $message);
                $success = "✅ Order #$order_id updated to " . ucfirst($status) . " and email sent to customer!";
            } else {
                $success = "✅ Order #$order_id updated to " . ucfirst($status);
            }
        }
    }
    
    // CATEGORY: Add
    if ($action === 'add_category') {
        $name = mysqli_real_escape_string($conn, $_POST['cat_name'] ?? '');
        $slug = strtolower(str_replace(' ', '-', $name));
        if ($name) {
            mysqli_query($conn, "INSERT INTO categories (name, slug) VALUES ('$name', '$slug')");
            $success = "✅ Category '$name' added!";
        }
    }
    
    // CATEGORY: Delete
    if ($action === 'delete_category') {
        $id = (int)($_POST['cat_id'] ?? 0);
        mysqli_query($conn, "DELETE FROM categories WHERE id=$id");
        $success = "✅ Category deleted!";
    }
    
    // USER: Toggle Premium Status
    if ($action === 'toggle_premium') {
        $user_id = (int)($_POST['user_id'] ?? 0);
        $is_premium = (int)($_POST['is_premium'] ?? 0);
        $new_premium = $is_premium ? 0 : 1;
        mysqli_query($conn, "UPDATE users SET is_premium=$new_premium WHERE id=$user_id");
        $success = $new_premium ? "✅ User upgraded to Premium!" : "✅ Premium status removed.";
    }
    
    // USER: Toggle Status
    if ($action === 'toggle_user') {
        $user_id = (int)($_POST['user_id'] ?? 0);
        $current = (int)($_POST['current_status'] ?? 1);
        $new = $current ? 0 : 1;
        mysqli_query($conn, "UPDATE users SET is_active=$new WHERE id=$user_id");
        $success = "✅ User status updated!";
    }
    
    // USER: Delete
    if ($action === 'delete_user') {
        $user_id = (int)($_POST['user_id'] ?? 0);
        if ($user_id != $_SESSION['user_id']) {
            mysqli_query($conn, "DELETE FROM users WHERE id=$user_id");
            $success = "✅ User deleted!";
        } else {
            $error = "❌ You cannot delete your own account!";
        }
    }
    
    // COUPON: Add
    if ($action === 'add_coupon') {
        $code = strtoupper(mysqli_real_escape_string($conn, $_POST['code'] ?? ''));
        $discount_type = mysqli_real_escape_string($conn, $_POST['discount_type'] ?? 'percentage');
        $discount_value = (float)($_POST['discount_value'] ?? 0);
        $min_order = (float)($_POST['min_order'] ?? 0);
        $expiry_date = mysqli_real_escape_string($conn, $_POST['expiry_date'] ?? '');
        
        if ($code && $discount_value > 0) {
            mysqli_query($conn, "INSERT INTO coupons (code, discount_type, discount_value, min_order_amount, end_date) 
                                VALUES ('$code', '$discount_type', $discount_value, $min_order, '$expiry_date')");
            $success = "✅ Coupon '$code' created successfully!";
        } else {
            $error = "❌ Coupon code and value are required.";
        }
    }
    
    // COUPON: Delete
    if ($action === 'delete_coupon') {
        $id = (int)($_POST['coupon_id'] ?? 0);
        mysqli_query($conn, "DELETE FROM coupons WHERE id=$id");
        $success = "✅ Coupon deleted!";
    }
}

// First, add is_premium column to users table if it doesn't exist
$check_column = mysqli_query($conn, "SHOW COLUMNS FROM users LIKE 'is_premium'");
if (mysqli_num_rows($check_column) == 0) {
    mysqli_query($conn, "ALTER TABLE users ADD COLUMN is_premium TINYINT(1) DEFAULT 0");
}

// ── Fetch all data ──────────────────────────────
$total_products = (int)mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM products"))['c'];
$total_users = (int)mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM users WHERE role='user'"))['c'];
$premium_users = (int)mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM users WHERE is_premium=1 AND role='user'"))['c'];
$total_orders = (int)mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM orders"))['c'];
$pending_orders = (int)mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM orders WHERE status='pending'"))['c'];
$total_revenue = (float)mysqli_fetch_assoc(mysqli_query($conn, "SELECT COALESCE(SUM(total_amount),0) as t FROM orders WHERE status='completed' OR status='paid'"))['t'];

$products = mysqli_fetch_all(mysqli_query($conn, "SELECT p.*, c.name as cat_name FROM products p LEFT JOIN categories c ON p.category_id=c.id ORDER BY p.id DESC"), MYSQLI_ASSOC);
$categories = mysqli_fetch_all(mysqli_query($conn, "SELECT * FROM categories ORDER BY name"), MYSQLI_ASSOC);
$orders = mysqli_fetch_all(mysqli_query($conn, "SELECT o.*, u.username, u.email FROM orders o JOIN users u ON o.user_id=u.id ORDER BY o.order_date DESC LIMIT 50"), MYSQLI_ASSOC);
$users = mysqli_fetch_all(mysqli_query($conn, "SELECT * FROM users WHERE role='user' ORDER BY created_at DESC"), MYSQLI_ASSOC);
$coupons = mysqli_fetch_all(mysqli_query($conn, "SELECT * FROM coupons ORDER BY id DESC"), MYSQLI_ASSOC);

$tab = $_GET['tab'] ?? 'dashboard';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - ShopEasy</title>
    <link rel="stylesheet" href="style.css">
    <style>
    :root {
        --admin-bg: #0a0e27;
        --admin-card-bg: rgba(16, 22, 54, 0.95);
        --admin-text: #ffffff;
        --admin-text-muted: rgba(255, 255, 255, 0.7);
    }

    body {
        background: var(--admin-bg);
        color: var(--admin-text);
    }

    .admin-wrapper {
        min-height: 100vh;
        background: var(--admin-bg);
    }

    .admin-header {
        background: rgba(7, 11, 31, 0.95);
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        padding: 0 30px;
        position: sticky;
        top: 0;
        z-index: 100;
        backdrop-filter: blur(10px);
    }

    .admin-header-inner {
        max-width: 1400px;
        margin: 0 auto;
        display: flex;
        justify-content: space-between;
        align-items: center;
        height: 70px;
    }

    .admin-logo {
        display: flex;
        align-items: center;
        gap: 12px;
        font-size: 1.5rem;
        font-weight: 800;
        color: white;
    }

    .admin-nav {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
        background: rgba(7, 11, 31, 0.95);
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        padding: 0 30px;
        position: sticky;
        top: 70px;
        z-index: 99;
    }

    .admin-nav-inner {
        max-width: 1400px;
        margin: 0 auto;
        display: flex;
        gap: 5px;
        overflow-x: auto;
    }

    .nav-tab {
        padding: 14px 24px;
        color: rgba(255, 255, 255, 0.7);
        text-decoration: none;
        font-weight: 500;
        border-bottom: 2px solid transparent;
        transition: all 0.2s;
        white-space: nowrap;
    }

    .nav-tab:hover,
    .nav-tab.active {
        color: #7e8bff;
        border-bottom-color: #7e8bff;
    }

    .admin-content {
        max-width: 1400px;
        margin: 0 auto;
        padding: 30px;
    }

    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
        margin-bottom: 40px;
    }

    .stat-card {
        background: var(--admin-card-bg);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 20px;
        padding: 25px;
        text-align: center;
        transition: transform 0.2s;
    }

    .stat-card:hover {
        transform: translateY(-3px);
        border-color: #7e8bff;
    }

    .stat-value {
        font-size: 2rem;
        font-weight: 800;
        color: #7e8bff;
    }

    .admin-card {
        background: var(--admin-card-bg);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 20px;
        padding: 25px;
        margin-bottom: 30px;
    }

    .admin-card h3 {
        margin-top: 0;
        margin-bottom: 20px;
        color: #7e8bff;
        font-size: 1.2rem;
    }

    .form-row {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 20px;
        margin-bottom: 20px;
    }

    .form-group label {
        display: block;
        margin-bottom: 8px;
        font-size: 0.85rem;
        color: rgba(255, 255, 255, 0.7);
    }

    .form-group input,
    .form-group select,
    .form-group textarea {
        width: 100%;
        padding: 12px;
        background: rgba(0, 0, 0, 0.5);
        border: 1px solid rgba(255, 255, 255, 0.15);
        border-radius: 12px;
        color: white;
        font-size: 0.95rem;
    }

    .form-group input:focus,
    .form-group select:focus,
    .form-group textarea:focus {
        outline: none;
        border-color: #7e8bff;
        background: rgba(0, 0, 0, 0.7);
    }

    .form-group input::placeholder,
    .form-group textarea::placeholder {
        color: rgba(255, 255, 255, 0.4);
    }

    .btn {
        padding: 12px 24px;
        background: linear-gradient(135deg, #7e8bff, #ca5bff);
        color: white;
        border: none;
        border-radius: 12px;
        cursor: pointer;
        font-weight: 600;
        transition: all 0.2s;
    }

    .btn:hover {
        transform: translateY(-2px);
        opacity: 0.9;
    }

    .btn-sm {
        padding: 6px 12px;
        border-radius: 8px;
        border: none;
        cursor: pointer;
        font-size: 0.75rem;
        transition: all 0.2s;
        font-weight: 500;
    }

    .btn-edit {
        background: rgba(126, 139, 255, 0.2);
        color: #7e8bff;
    }

    .btn-edit:hover {
        background: rgba(126, 139, 255, 0.4);
    }

    .btn-delete {
        background: rgba(255, 91, 120, 0.2);
        color: #ff5b78;
    }

    .btn-delete:hover {
        background: rgba(255, 91, 120, 0.4);
    }

    .premium-badge {
        background: linear-gradient(135deg, #ffd700, #ffed4e);
        color: #000;
        padding: 2px 8px;
        border-radius: 12px;
        font-size: 0.7rem;
        font-weight: bold;
        margin-left: 5px;
    }

    table {
        width: 100%;
        border-collapse: collapse;
    }

    th,
    td {
        padding: 12px;
        text-align: left;
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    }

    th {
        background: rgba(255, 255, 255, 0.05);
        font-weight: 600;
        font-size: 0.85rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: rgba(255, 255, 255, 0.9);
    }

    tr:hover td {
        background: rgba(255, 255, 255, 0.03);
    }

    .status-badge {
        display: inline-block;
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 0.75rem;
        font-weight: 600;
    }

    .status-pending {
        background: rgba(255, 200, 70, 0.2);
        color: #ffc846;
    }

    .status-paid {
        background: rgba(94, 213, 138, 0.2);
        color: #5ed58a;
    }

    .status-shipped {
        background: rgba(116, 160, 255, 0.2);
        color: #74a0ff;
    }

    .status-completed {
        background: rgba(94, 213, 138, 0.2);
        color: #5ed58a;
    }

    .status-cancelled {
        background: rgba(255, 91, 120, 0.2);
        color: #ff5b78;
    }

    .warning-box {
        background: rgba(255, 200, 70, 0.1);
        border: 1px solid rgba(255, 200, 70, 0.2);
        border-radius: 12px;
        padding: 15px 20px;
        margin-bottom: 20px;
        color: #ffc846;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .flash {
        padding: 12px 20px;
        border-radius: 12px;
        margin-bottom: 20px;
    }

    .flash.success {
        background: rgba(94, 213, 138, 0.1);
        border: 1px solid rgba(94, 213, 138, 0.2);
        color: #5ed58a;
    }

    .flash.error {
        background: rgba(255, 91, 120, 0.1);
        border: 1px solid rgba(255, 91, 120, 0.2);
        color: #ff5b78;
    }

    @media (max-width: 768px) {
        .admin-content {
            padding: 20px;
        }

        .stats-grid {
            grid-template-columns: repeat(2, 1fr);
        }
    }
    </style>
</head>

<body>
    <div class="admin-wrapper">

        <header class="admin-header">
            <div class="admin-header-inner">
                <div class="admin-logo">🛒 ShopEasy Admin</div>
                <div>
                    <span style="margin-right: 15px; color: white;">👋 <?= $admin_name ?></span>
                    <a href="index.php" style="color: #7e8bff; margin-right: 15px;" target="_blank">View Store</a>
                    <a href="logout.php" style="color: #ff5b78;">Logout</a>
                </div>
            </div>
        </header>

        <div class="admin-nav">
            <div class="admin-nav-inner">
                <a href="?tab=dashboard" class="nav-tab <?= $tab === 'dashboard' ? 'active' : '' ?>">📊 Dashboard</a>
                <a href="?tab=products" class="nav-tab <?= $tab === 'products' ? 'active' : '' ?>">📦 Products</a>
                <a href="?tab=orders" class="nav-tab <?= $tab === 'orders' ? 'active' : '' ?>">📋 Orders</a>
                <a href="?tab=users" class="nav-tab <?= $tab === 'users' ? 'active' : '' ?>">👥 Users</a>
                <a href="?tab=categories" class="nav-tab <?= $tab === 'categories' ? 'active' : '' ?>">📁 Categories</a>
                <a href="?tab=coupons" class="nav-tab <?= $tab === 'coupons' ? 'active' : '' ?>">🏷️ Coupons</a>
            </div>
        </div>

        <main class="admin-content">

            <?php if ($success): ?>
            <div class="flash success"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
            <div class="flash error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <!-- DASHBOARD -->
            <?php if ($tab === 'dashboard'): ?>
            <div class="warning-box">
                ⚠️ <strong>Admin Mode:</strong> You are in management mode. To make purchases, please login as a regular
                user.
            </div>

            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-value"><?= $total_products ?></div>
                    <div style="color: rgba(255,255,255,0.8);">Total Products</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?= $total_users ?></div>
                    <div style="color: rgba(255,255,255,0.8);">Total Customers</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?= $premium_users ?></div>
                    <div style="color: rgba(255,255,255,0.8);">Premium Users 👑</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?= $total_orders ?></div>
                    <div style="color: rgba(255,255,255,0.8);">Total Orders</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?= $pending_orders ?></div>
                    <div style="color: rgba(255,255,255,0.8);">Pending Orders</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value">$<?= number_format($total_revenue, 2) ?></div>
                    <div style="color: rgba(255,255,255,0.8);">Total Revenue</div>
                </div>
            </div>

            <div class="admin-card">
                <h3>📋 Recent Orders</h3>
                <div style="overflow-x: auto;">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Customer</th>
                                <th>Total</th>
                                <th>Status</th>
                                <th>Date</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach (array_slice($orders, 0, 8) as $order): ?>
                            <tr>
                                <td style="color: white;">#<?= $order['id'] ?></td>
                                <td style="color: white;"><?= htmlspecialchars($order['username']) ?></td>
                                <td style="color: #7e8bff;">$<?= number_format($order['total_amount'], 2) ?></td>
                                <td><span
                                        class="status-badge status-<?= $order['status'] ?>"><?= ucfirst($order['status']) ?></span>
                                </td>
                                <td style="color: rgba(255,255,255,0.7);">
                                    <?= date('M d, Y', strtotime($order['order_date'])) ?></td>
                                <td><a href="?tab=orders" class="btn-sm btn-edit">Manage</a></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>

            <!-- PRODUCTS -->
            <?php if ($tab === 'products'): ?>
            <div class="admin-card">
                <h3>➕ Add New Product</h3>
                <form method="POST">
                    <input type="hidden" name="action" value="add_product">
                    <div class="form-row">
                        <div class="form-group"><label>Product Name *</label><input type="text" name="name" required>
                        </div>
                        <div class="form-group"><label>Price *</label><input type="number" name="price" step="0.01"
                                required></div>
                        <div class="form-group"><label>Stock Quantity</label><input type="number" name="stock"
                                value="0"></div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Category</label>
                            <select name="category_id">
                                <option value="0">-- None --</option>
                                <?php foreach ($categories as $cat): ?>
                                <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group"><label>Image URL</label><input type="text" name="image_url"
                                placeholder="https://..."></div>
                    </div>
                    <div class="form-group"><label>Description</label><textarea name="description" rows="3"></textarea>
                    </div>
                    <button type="submit" class="btn">➕ Add Product</button>
                </form>
            </div>

            <div class="admin-card">
                <h3>📦 All Products (<?= count($products) ?>)</h3>
                <div style="overflow-x: auto;">
                    <table>
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Price</th>
                                <th>Stock</th>
                                <th>Category</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($products as $p): ?>
                            <tr>
                                <td style="color: white;"><?= htmlspecialchars($p['name']) ?></td>
                                <td>
                                    <form method="POST" style="display: inline-flex; gap: 5px;">
                                        <input type="hidden" name="action" value="update_price">
                                        <input type="hidden" name="product_id" value="<?= $p['id'] ?>">
                                        <input type="number" name="price" step="0.01" value="<?= $p['price'] ?>"
                                            style="width: 100px; background: rgba(0,0,0,0.5); border: 1px solid rgba(255,255,255,0.1); color: white; padding: 5px; border-radius: 5px;">
                                        <button type="submit" class="btn-sm btn-edit">Update</button>
                                    </form>
                                </td>
                                <td>
                                    <form method="POST" style="display: inline-flex; gap: 5px;">
                                        <input type="hidden" name="action" value="update_stock">
                                        <input type="hidden" name="product_id" value="<?= $p['id'] ?>">
                                        <input type="number" name="stock" value="<?= $p['stock_quantity'] ?? 0 ?>"
                                            style="width: 80px; background: rgba(0,0,0,0.5); border: 1px solid rgba(255,255,255,0.1); color: white; padding: 5px; border-radius: 5px;">
                                        <button type="submit" class="btn-sm btn-edit">Update</button>
                                    </form>
                                </td>
                                <td style="color: rgba(255,255,255,0.8);"><?= htmlspecialchars($p['cat_name'] ?? '-') ?>
                                </td>
                                <td>
                                    <form method="POST" onsubmit="return confirm('Delete this product?')"
                                        style="display: inline;">
                                        <input type="hidden" name="action" value="delete_product">
                                        <input type="hidden" name="product_id" value="<?= $p['id'] ?>">
                                        <button type="submit" class="btn-sm btn-delete">Delete</button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>

            <!-- ORDERS -->
            <?php if ($tab === 'orders'): ?>
            <div class="admin-card">
                <h3>📋 Manage All Orders</h3>
                <div style="overflow-x: auto;">
                    <table>
                        <thead>
                            <tr>
                                <th>Order #</th>
                                <th>Customer</th>
                                <th>Total</th>
                                <th>Status</th>
                                <th>Tracking</th>
                                <th>Date</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($orders as $order): ?>
                            <tr>
                                <td style="color: white;">#<?= $order['order_number'] ?? $order['id'] ?></td>
                                <td style="color: white;"><?= htmlspecialchars($order['username']) ?></td>
                                <td style="color: #7e8bff; font-weight: bold;">
                                    $<?= number_format($order['total_amount'], 2) ?></td>
                                <td>
                                    <form method="POST" style="display: inline-flex; gap: 5px;">
                                        <input type="hidden" name="action" value="update_order_status">
                                        <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                                        <select name="status"
                                            style="background: rgba(0,0,0,0.5); border: 1px solid rgba(255,255,255,0.1); color: white; padding: 5px; border-radius: 5px;">
                                            <option value="pending"
                                                <?= $order['status']=='pending' ? 'selected' : '' ?>>Pending</option>
                                            <option value="processing"
                                                <?= $order['status']=='processing' ? 'selected' : '' ?>>Processing
                                            </option>
                                            <option value="paid" <?= $order['status']=='paid' ? 'selected' : '' ?>>✓
                                                Paid</option>
                                            <option value="shipped"
                                                <?= $order['status']=='shipped' ? 'selected' : '' ?>>Shipped</option>
                                            <option value="delivered"
                                                <?= $order['status']=='delivered' ? 'selected' : '' ?>>Delivered
                                            </option>
                                            <option value="completed"
                                                <?= $order['status']=='completed' ? 'selected' : '' ?>>Completed
                                            </option>
                                            <option value="cancelled"
                                                <?= $order['status']=='cancelled' ? 'selected' : '' ?>>Cancelled
                                            </option>
                                        </select>
                                        <button type="submit" class="btn-sm btn-edit">Update & Email</button>
                                    </form>
                                </td>
                                <td style="color: rgba(255,255,255,0.7);">
                                    <?= $order['tracking_number'] ?? 'Not assigned' ?></td>
                                <td style="color: rgba(255,255,255,0.7);">
                                    <?= date('M d, Y', strtotime($order['order_date'])) ?></td>
                                <td><button class="btn-sm btn-edit"
                                        onclick="alert('Order <?= $order['order_number'] ?? $order['id'] ?>\nCustomer: <?= addslashes($order['username']) ?>\nEmail: <?= $order['email'] ?>\nTotal: $<?= number_format($order['total_amount'], 2) ?>')">View</button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>

            <!-- USERS -->
            <?php if ($tab === 'users'): ?>
            <div class="admin-card">
                <h3>👥 Registered Customers (<?= count($users) ?>) 👑 Premium Users: <?= $premium_users ?></h3>
                <div style="overflow-x: auto;">
                    <table>
                        <thead>
                            <tr>
                                <th>Username</th>
                                <th>Email</th>
                                <th>Premium</th>
                                <th>Status</th>
                                <th>Joined</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                            <tr>
                                <td style="color: white;"><?= htmlspecialchars($user['username']) ?>
                                    <?php if($user['is_premium']): ?><span class="premium-badge">👑
                                        PREMIUM</span><?php endif; ?></td>
                                <td style="color: rgba(255,255,255,0.8);"><?= htmlspecialchars($user['email']) ?></td>
                                <td>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="toggle_premium">
                                        <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                        <input type="hidden" name="is_premium" value="<?= $user['is_premium'] ?>">
                                        <button type="submit"
                                            class="btn-sm <?= $user['is_premium'] ? 'btn-delete' : 'btn-edit' ?>">
                                            <?= $user['is_premium'] ? 'Remove Premium' : 'Make Premium' ?>
                                        </button>
                                    </form>
                                </td>
                                <td><span
                                        class="status-badge <?= $user['is_active'] ? 'status-paid' : 'status-cancelled' ?>"><?= $user['is_active'] ? 'Active' : 'Inactive' ?></span>
                                </td>
                                <td style="color: rgba(255,255,255,0.7);">
                                    <?= date('M d, Y', strtotime($user['created_at'])) ?></td>
                                <td>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="toggle_user">
                                        <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                        <input type="hidden" name="current_status" value="<?= $user['is_active'] ?>">
                                        <button type="submit"
                                            class="btn-sm btn-edit"><?= $user['is_active'] ? 'Disable' : 'Enable' ?></button>
                                    </form>
                                    <form method="POST" style="display: inline;"
                                        onsubmit="return confirm('Delete this user?')">
                                        <input type="hidden" name="action" value="delete_user">
                                        <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                        <button type="submit" class="btn-sm btn-delete">Delete</button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>

            <!-- CATEGORIES -->
            <?php if ($tab === 'categories'): ?>
            <div class="admin-card">
                <h3>➕ Add Category</h3>
                <form method="POST" style="display: flex; gap: 10px;">
                    <input type="hidden" name="action" value="add_category">
                    <input type="text" name="cat_name" placeholder="Category Name"
                        style="flex: 1; padding: 12px; background: rgba(0,0,0,0.5); border: 1px solid rgba(255,255,255,0.1); border-radius: 12px; color: white;">
                    <button type="submit" class="btn">Add Category</button>
                </form>
            </div>

            <div class="admin-card">
                <h3>📁 Categories List</h3>
                <div style="overflow-x: auto;">
                    <table>
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Products Count</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($categories as $cat): ?>
                            <?php $prod_count = (int)mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM products WHERE category_id={$cat['id']}"))['c']; ?>
                            <tr>
                                <td style="color: white;"><?= htmlspecialchars($cat['name']) ?></td>
                                <td style="color: rgba(255,255,255,0.8);"><?= $prod_count ?> products</td>
                                <td>
                                    <form method="POST"
                                        onsubmit="return confirm('Delete this category? Products will become uncategorized.')"
                                        style="display: inline;">
                                        <input type="hidden" name="action" value="delete_category">
                                        <input type="hidden" name="cat_id" value="<?= $cat['id'] ?>">
                                        <button type="submit" class="btn-sm btn-delete">Delete</button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>

            <!-- COUPONS -->
            <?php if ($tab === 'coupons'): ?>
            <div class="admin-card">
                <h3>➕ Create New Coupon</h3>
                <form method="POST">
                    <input type="hidden" name="action" value="add_coupon">
                    <div class="form-row">
                        <div class="form-group"><label>Coupon Code</label><input type="text" name="code"
                                placeholder="WELCOME10" required></div>
                        <div class="form-group">
                            <label>Discount Type</label>
                            <select name="discount_type">
                                <option value="percentage">Percentage (%)</option>
                                <option value="fixed">Fixed Amount ($)</option>
                            </select>
                        </div>
                        <div class="form-group"><label>Discount Value</label><input type="number" name="discount_value"
                                step="0.01" required></div>
                    </div>
                    <div class="form-row">
                        <div class="form-group"><label>Minimum Order Amount</label><input type="number" name="min_order"
                                step="0.01" value="0"></div>
                        <div class="form-group"><label>Expiry Date</label><input type="date" name="expiry_date"></div>
                    </div>
                    <button type="submit" class="btn">🎟️ Create Coupon</button>
                </form>
            </div>

            <div class="admin-card">
                <h3>🏷️ Active Coupons</h3>
                <div style="overflow-x: auto;">
                    <table>
                        <thead>
                            <tr>
                                <th>Code</th>
                                <th>Type</th>
                                <th>Value</th>
                                <th>Min Order</th>
                                <th>Expiry Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($coupons as $coupon): ?>
                            <tr>
                                <td style="color: #7e8bff; font-weight: bold;"><?= htmlspecialchars($coupon['code']) ?>
                                </td>
                                <td style="color: rgba(255,255,255,0.8);">
                                    <?= $coupon['discount_type'] == 'percentage' ? 'Percentage' : 'Fixed' ?></td>
                                <td style="color: white;">
                                    <?= $coupon['discount_type'] == 'percentage' ? $coupon['discount_value'] . '%' : '$' . number_format($coupon['discount_value'], 2) ?>
                                </td>
                                <td style="color: rgba(255,255,255,0.8);">
                                    $<?= number_format($coupon['min_order_amount'], 2) ?></td>
                                <td style="color: rgba(255,255,255,0.8);">
                                    <?= $coupon['end_date'] ? date('M d, Y', strtotime($coupon['end_date'])) : 'No expiry' ?>
                                </td>
                                <td>
                                    <form method="POST" onsubmit="return confirm('Delete this coupon?')"
                                        style="display: inline;">
                                        <input type="hidden" name="action" value="delete_coupon">
                                        <input type="hidden" name="coupon_id" value="<?= $coupon['id'] ?>">
                                        <button type="submit" class="btn-sm btn-delete">Delete</button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>

        </main>
    </div>
</body>

</html>