<?php
session_start();
require_once 'db.php';

$error = '';
$success = '';

if (isset($_GET['message']) && $_GET['message'] === 'logout') {
    $success = 'You have successfully logged out.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = mysqli_real_escape_string($conn, trim($_POST['username']));
    $password = $_POST['password'];

    $sql = "SELECT id, username, password, role FROM users WHERE username = '$username' LIMIT 1";
    $result = mysqli_query($conn, $sql);

    if ($result && $row = mysqli_fetch_assoc($result)) {
        if (password_verify($password, $row['password'])) {
            $_SESSION['user_id'] = $row['id'];
            $_SESSION['username'] = $row['username'];
            $_SESSION['role'] = $row['role'];
            header('Location: index.php');
            exit();
        }

        $error = 'Invalid password.';
    } else {
        $error = 'User not found.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - E-Commerce Store</title>
    <link rel="stylesheet" href="style.css">
</head>

<body>
    <div class="page-shell">
        <div class="auth-card">
            <div class="brand">
                <span class="logo">E</span>
                <div>
                    <h1>ShopEasy</h1>
                    <p>Secure storefront login</p>
                </div>
            </div>

            <?php if ($success): ?>
            <div class="message success"><?php echo $success; ?></div>
            <?php endif; ?>

            <?php if ($error): ?>
            <div class="message error"><?php echo $error; ?></div>
            <?php endif; ?>

            <form method="POST" class="auth-form" novalidate>
                <label class="field">
                    <span>Username</span>
                    <input type="text" name="username" placeholder="Enter your username" required autofocus>
                </label>

                <label class="field">
                    <span>Password</span>
                    <div class="password-wrap">
                        <input type="password" name="password" placeholder="Enter your password" required
                            id="passwordInput">
                        <button type="button" class="toggle-password" id="togglePassword"
                            aria-label="Show password">Show</button>
                    </div>
                </label>

                <button type="submit" class="btn">Sign In</button>
            </form>

            <div class="meta">
                <p>Need help? <a href="#">Contact support</a></p>
                <a href="logout.php" class="secondary-link">Logout page</a>
            </div>
        </div>
    </div>

    <script>
    const passwordInput = document.getElementById('passwordInput');
    const togglePassword = document.getElementById('togglePassword');

    togglePassword.addEventListener('click', () => {
        const isPassword = passwordInput.type === 'password';
        passwordInput.type = isPassword ? 'text' : 'password';
        togglePassword.textContent = isPassword ? 'Hide' : 'Show';
    });
    </script>
</body>

</html>