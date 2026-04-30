<?php
require_once 'auth.php';
app_session_start();
app_no_cache();

if (app_is_logged_in()) {
    app_redirect_to_dashboard();
}

if (isset($_GET['next']) && $_GET['next'] === 'checkout') {
    $_SESSION['post_login_next'] = 'checkout';
}

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
            session_regenerate_id(true);
            $_SESSION['user_id'] = $row['id'];
            $_SESSION['username'] = $row['username'];
            $_SESSION['role'] = $row['role'];

            $next = (string)($_SESSION['post_login_next'] ?? '');
            unset($_SESSION['post_login_next']);

            if ($row['role'] === 'admin') {
                header('Location: admin_dashboard.php');
                exit();
            }

            if ($next === 'checkout') {
                header('Location: customer_dashboard.php?next=checkout');
                exit();
            }

            header('Location: customer_dashboard.php');
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
    <title>Login - ShopEasy</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="page-shell">
        <div class="auth-card">
            <div class="brand" style="display:flex; align-items:center; gap:15px; margin-bottom:30px;">
                <span class="logo" style="background:var(--primary); width:50px; height:50px; display:flex; align-items:center; justify-content:center; border-radius:12px; font-weight:800; font-size:1.5rem; color:#fff;">S</span>
                <div>
                    <h1 style="margin:0; font-size:1.5rem;">ShopEasy</h1>
                    <p style="margin:0; color:var(--text-muted); font-size:0.9rem;">Secure login</p>
                </div>
            </div>

            <?php if ($success): ?>
            <div class="flash success">✅ <?= $success ?></div>
            <?php endif; ?>

            <?php if ($error): ?>
            <div class="flash error">❌ <?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="POST">
                <div class="field">
                    <span>Username</span>
                    <input type="text" name="username" required autofocus>
                </div>

                <div class="field">
                    <span>Password</span>
                    <div style="position:relative;">
                        <input type="password" name="password" id="password" required>
                        <button type="button" id="togglePassword" style="position:absolute; right:10px; top:50%; transform:translateY(-50%); background:none; border:none; color:var(--text-muted); cursor:pointer; font-size:1.2rem;">👁️</button>
                    </div>
                </div>

                <button type="submit" class="btn">Login</button>
                
                <div style="margin-top:25px; text-align:center; font-size:0.9rem;">
                    <a href="register.php<?php echo isset($_SESSION['post_login_next']) ? '?next='.$_SESSION['post_login_next'] : ''; ?>">
                        Don't have an account? Register here
                    </a>
                </div>
            </form>
            <div style="margin-top:20px; text-align:center;">
                <a href="index.php" style="font-size:0.85rem; color:var(--text-muted);">← Back to Store</a>
            </div>
        </div>
    </div>

    <script>
        const togglePassword = document.querySelector('#togglePassword');
        const password = document.querySelector('#password');

        togglePassword.addEventListener('click', function (e) {
            const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
            password.setAttribute('type', type);
            this.textContent = type === 'password' ? '👁️' : '🔒';
        });
    </script>
</body>
</html>