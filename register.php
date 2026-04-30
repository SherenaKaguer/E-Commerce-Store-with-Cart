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
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username_raw = trim((string)($_POST['username'] ?? ''));
    $username = mysqli_real_escape_string($conn, $username_raw);
    $email_raw = trim($_POST['email'] ?? '');
    $email = mysqli_real_escape_string($conn, $email_raw);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    if ($email_raw === '' || !filter_var($email_raw, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please provide a valid email address.';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } else {
        $sql = "SELECT username, email FROM users WHERE username = '$username' OR email = '$email' LIMIT 1";
        $result = mysqli_query($conn, $sql);

        if ($result && $existing = mysqli_fetch_assoc($result)) {
            if (strcasecmp($existing['username'], $username_raw) === 0) {
                $error = 'Username already exists. Please choose another.';
            } else {
                $error = 'Email already exists. Please use another email.';
            }
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $insert_sql = "INSERT INTO users (username, password, email, role) VALUES ('$username', '$hashed_password', '$email', 'user')";
            if (mysqli_query($conn, $insert_sql)) {
                session_regenerate_id(true);
                $_SESSION['user_id'] = mysqli_insert_id($conn);
                $_SESSION['username'] = $username_raw;
                $_SESSION['role'] = 'user';
                $_SESSION['new_user'] = true;
                $_SESSION['success'] = 'Registration successful! Welcome to ShopEasy.';

                $next = (string)($_SESSION['post_login_next'] ?? '');
                unset($_SESSION['post_login_next']);

                if ($next === 'checkout') {
                    header('Location: customer_dashboard.php?next=checkout');
                    exit();
                }

                header('Location: customer_dashboard.php');
                exit();
            } else {
                $error = 'Error during registration. Please try again.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - ShopEasy</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="page-shell">
        <div class="auth-card">
            <div class="brand" style="display:flex; align-items:center; gap:15px; margin-bottom:30px;">
                <span class="logo" style="background:var(--primary); width:50px; height:50px; display:flex; align-items:center; justify-content:center; border-radius:12px; font-weight:800; font-size:1.5rem; color:#fff;">S</span>
                <div>
                    <h1 style="margin:0; font-size:1.5rem;">ShopEasy</h1>
                    <p style="margin:0; color:var(--text-muted); font-size:0.9rem;">Join our community</p>
                </div>
            </div>

            <?php if ($error): ?>
            <div class="flash error">❌ <?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="POST">
                <div class="field">
                    <span>Username</span>
                    <input type="text" name="username" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" required autofocus>
                </div>

                <div class="field">
                    <span>Email Address</span>
                    <input type="email" name="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
                </div>

                <div class="field">
                    <span>Password</span>
                    <div style="position:relative;">
                        <input type="password" name="password" id="password" required>
                        <button type="button" class="togglePassword" data-target="password" style="position:absolute; right:10px; top:50%; transform:translateY(-50%); background:none; border:none; color:var(--text-muted); cursor:pointer; font-size:1.2rem;">👁️</button>
                    </div>
                </div>

                <div class="field">
                    <span>Confirm Password</span>
                    <div style="position:relative;">
                        <input type="password" name="confirm_password" id="confirm_password" required>
                        <button type="button" class="togglePassword" data-target="confirm_password" style="position:absolute; right:10px; top:50%; transform:translateY(-50%); background:none; border:none; color:var(--text-muted); cursor:pointer; font-size:1.2rem;">👁️</button>
                    </div>
                </div>

                <button type="submit" class="btn">Create Account</button>
                
                <div style="margin-top:25px; text-align:center; font-size:0.9rem;">
                    <a href="login.php<?php echo isset($_SESSION['post_login_next']) ? '?next='.$_SESSION['post_login_next'] : ''; ?>">
                        Already have an account? Login here
                    </a>
                </div>
            </form>
            <div style="margin-top:20px; text-align:center;">
                <a href="index.php" style="font-size:0.85rem; color:var(--text-muted);">← Back to Store</a>
            </div>
        </div>
    </div>

    <script>
        document.querySelectorAll('.togglePassword').forEach(button => {
            button.addEventListener('click', function() {
                const targetId = this.getAttribute('data-target');
                const input = document.getElementById(targetId);
                const type = input.getAttribute('type') === 'password' ? 'text' : 'password';
                input.setAttribute('type', type);
                this.textContent = type === 'password' ? '👁️' : '🔒';
            });
        });
    </script>
</body>
</html>
<?php
mysqli_close($conn);
?>