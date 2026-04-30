<?php
require_once 'auth.php';
require_once 'db.php';
app_session_start();
app_no_cache();

app_require_login('login.php');

$user_id = (int)$_SESSION['user_id'];

// Get cart items to calculate total and verify cart is not empty
$cart_items = [];
$total = 0;
$subtotal = 0;
$res = mysqli_query($conn, "SELECT c.*, p.price, p.name, p.stock_quantity FROM cart_items c JOIN products p ON c.product_id = p.id WHERE c.user_id = $user_id");
while ($row = mysqli_fetch_assoc($res)) {
    $cart_items[] = $row;
    $subtotal += $row['price'] * $row['quantity'];
}

// Calculate taxes and shipping (simple example)
$tax_amount = $subtotal * 0.10; // 10% tax
$shipping_amount = $subtotal > 100 ? 0 : 10.00; // Free shipping over $100
$discount_amount = 0; // Could apply coupons here
$total = $subtotal + $tax_amount + $shipping_amount - $discount_amount;

if (empty($cart_items)) {
    header("Location: customer_dashboard.php");
    exit();
}

$error_msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Simulate payment processing
    $card_name = mysqli_real_escape_string($conn, $_POST['card_name'] ?? '');
    $payment_method = mysqli_real_escape_string($conn, $_POST['payment_method'] ?? 'credit_card');
    
    if ($card_name) {
        // Start transaction for data consistency
        mysqli_begin_transaction($conn);
        
        try {
            // Generate a unique order number
            $order_number = 'ORD-' . strtoupper(uniqid()) . '-' . time() . '-' . $user_id;
            
            // Get user shipping address (from session or POST)
            $shipping_address = $_SESSION['shipping_address'] ?? $_POST['address'] ?? 'No address provided';
            $billing_address = $_SESSION['billing_address'] ?? $_POST['address'] ?? 'No address provided';
            
            // 1. Create Order with all required fields
            $query = "INSERT INTO orders (
                user_id, 
                order_number, 
                total_amount, 
                subtotal, 
                tax_amount, 
                shipping_amount, 
                discount_amount, 
                status, 
                payment_status, 
                payment_method, 
                shipping_address, 
                billing_address,
                order_date
            ) VALUES (
                $user_id, 
                '$order_number', 
                $total, 
                $subtotal, 
                $tax_amount, 
                $shipping_amount, 
                $discount_amount, 
                'paid', 
                'paid', 
                '$payment_method', 
                '" . mysqli_real_escape_string($conn, $shipping_address) . "', 
                '" . mysqli_real_escape_string($conn, $billing_address) . "',
                NOW()
            )";
            
            if (!mysqli_query($conn, $query)) {
                throw new Exception("Failed to create order: " . mysqli_error($conn));
            }
            
            $order_id = mysqli_insert_id($conn);
            
            // 2. Move items to order_items with correct column names
            foreach ($cart_items as $item) {
                $pid = $item['product_id'];
                $qty = $item['quantity'];
                $price = $item['price'];
                $item_total = $price * $qty;
                
                // Insert into order_items using price_at_time and total_price
                $order_item_query = "INSERT INTO order_items (
                    order_id, 
                    product_id, 
                    quantity, 
                    price_at_time, 
                    total_price
                ) VALUES (
                    $order_id, 
                    $pid, 
                    $qty, 
                    $price, 
                    $item_total
                )";
                
                if (!mysqli_query($conn, $order_item_query)) {
                    throw new Exception("Failed to add order items: " . mysqli_error($conn));
                }
            }
            
            // Note: The stock will be automatically updated by the trigger 'update_product_stock_after_order'
            // So we don't need to manually update stock here
            
            // 3. Clear Cart
            if (!mysqli_query($conn, "DELETE FROM cart_items WHERE user_id = $user_id")) {
                throw new Exception("Failed to clear cart: " . mysqli_error($conn));
            }
            
            // Commit transaction
            mysqli_commit($conn);
            
            $_SESSION['success'] = "Payment successful! Your order #$order_number has been placed. You will receive a confirmation email shortly.";
            header("Location: customer_dashboard.php");
            exit();
            
        } catch (Exception $e) {
            // Rollback transaction on error
            mysqli_rollback($conn);
            $error_msg = "Payment failed: " . $e->getMessage();
        }
    } else {
        $error_msg = "Please enter the name on your card.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment - ShopEasy</title>
    <link rel="stylesheet" href="style.css">
</head>

<body>
    <header class="site-header">
        <div class="header-inner">
            <a href="customer_dashboard.php" class="logo-link">🛒 ShopEasy</a>
            <nav class="main-nav">
                <a href="cart.php" class="nav-pill">← Back to Cart</a>
            </nav>
        </div>
    </header>

    <main class="page-shell">
        <div class="auth-card" style="max-width: 600px;">
            <div class="brand">
                <h1 style="margin:0;">Secure Payment</h1>
                <p style="color:var(--text-muted);">Complete your purchase securely.</p>
            </div>

            <?php if ($error_msg): ?>
            <div class="flash error">❌ <?= htmlspecialchars($error_msg) ?></div>
            <?php endif; ?>

            <div
                style="background: rgba(255,255,255,0.05); padding: 20px; border-radius: 15px; margin-bottom: 30px; border: 1px solid var(--border);">
                <h3 style="margin-top:0; font-size: 1rem; color: var(--text-muted);">Order Summary</h3>
                <div style="margin-top: 10px;">
                    <?php foreach ($cart_items as $item): ?>
                    <div style="display: flex; justify-content: space-between; font-size: 0.9rem; margin-bottom: 8px;">
                        <span><?= htmlspecialchars($item['name']) ?> x<?= $item['quantity'] ?></span>
                        <span>$<?= number_format($item['price'] * $item['quantity'], 2) ?></span>
                    </div>
                    <?php endforeach; ?>

                    <div style="border-top: 1px solid var(--border); margin-top: 10px; padding-top: 10px;">
                        <div style="display: flex; justify-content: space-between; font-size: 0.9rem;">
                            <span>Subtotal:</span>
                            <span>$<?= number_format($subtotal, 2) ?></span>
                        </div>
                        <div style="display: flex; justify-content: space-between; font-size: 0.9rem;">
                            <span>Tax (10%):</span>
                            <span>$<?= number_format($tax_amount, 2) ?></span>
                        </div>
                        <div style="display: flex; justify-content: space-between; font-size: 0.9rem;">
                            <span>Shipping:</span>
                            <span><?= $shipping_amount > 0 ? '$' . number_format($shipping_amount, 2) : 'Free' ?></span>
                        </div>
                        <?php if ($discount_amount > 0): ?>
                        <div style="display: flex; justify-content: space-between; font-size: 0.9rem;">
                            <span>Discount:</span>
                            <span>-$<?= number_format($discount_amount, 2) ?></span>
                        </div>
                        <?php endif; ?>
                        <div
                            style="border-top: 1px solid var(--border); margin-top: 10px; padding-top: 10px; display: flex; justify-content: space-between; font-size: 1.2rem; font-weight: bold;">
                            <span>Total Amount:</span>
                            <span style="color: var(--primary);">$<?= number_format($total, 2) ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <form method="POST">
                <div class="field">
                    <span>Cardholder Name</span>
                    <input type="text" name="card_name" placeholder="John Doe" required>
                </div>

                <div class="field">
                    <span>Payment Method</span>
                    <select name="payment_method"
                        style="width: 100%; padding: 12px; border-radius: 12px; background: rgba(255,255,255,0.05); border: 1px solid var(--border); color: #fff;">
                        <option value="credit_card">Credit Card</option>
                        <option value="debit_card">Debit Card</option>
                        <option value="paypal">PayPal</option>
                    </select>
                </div>

                <div class="field">
                    <span>Card Number</span>
                    <input type="text" placeholder="1234 5678 9012 3456" pattern="[\d\s]{16,19}"
                        title="Valid card number" required>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                    <div class="field">
                        <span>Expiry Date</span>
                        <input type="text" placeholder="MM/YY" pattern="(0[1-9]|1[0-2])\/([0-9]{2})"
                            title="MM/YY format" required>
                    </div>
                    <div class="field">
                        <span>CVV</span>
                        <input type="password" placeholder="123" pattern="\d{3,4}" title="3 or 4 digit CVV" required>
                    </div>
                </div>

                <div class="field">
                    <span>Shipping Address</span>
                    <textarea name="address" rows="3" placeholder="Enter your full shipping address"
                        style="width: 100%; padding: 12px; border-radius: 12px; background: rgba(255,255,255,0.05); border: 1px solid var(--border); color: #fff;"
                        required></textarea>
                </div>

                <button type="submit" class="btn" style="background: var(--success); margin-top: 10px;">Pay
                    $<?= number_format($total, 2) ?></button>
            </form>

            <p style="text-align: center; font-size: 0.8rem; color: var(--text-muted); margin-top: 20px;">
                🔒 Encrypted & Secure Payment
            </p>
        </div>
    </main>

    <footer class="site-footer">
        <p>© <?= date('Y') ?> ShopEasy. All rights reserved.</p>
    </footer>
</body>

</html>