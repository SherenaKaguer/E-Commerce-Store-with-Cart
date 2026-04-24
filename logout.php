<?php
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    session_unset();
    session_destroy();
    header('Location: login.php?message=logout');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logout - E-Commerce Store</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="page-shell">
        <div class="auth-card">
            <div class="brand">
                <span class="logo">E</span>
                <div>
                    <h1>ShopEasy</h1>
                    <p>Confirm your session logout</p>
                </div>
            </div>

            <h2>Logout</h2>
            <p class="logout-text">Ready to finish your session? Click the button below to sign out safely.</p>
            <?php if (isset($_SESSION['username'])): ?>
                <p class="status-line">Signed in as <strong><?php echo htmlspecialchars($_SESSION['username']); ?></strong></p>
            <?php endif; ?>

            <form method="POST" class="logout-form">
                <button type="submit" class="btn danger">Logout now</button>
                <a href="index.php" class="secondary-link">Return to store</a>
            </form>
        </div>
    </div>
</body>
</html>

