<?php
require_once 'db.php';
require_once 'auth.php';
app_session_start();

$admin_user = 'admin';
$admin_pass = 'password123';
$hashed_pass = password_hash($admin_pass, PASSWORD_DEFAULT);

$sql = "REPLACE INTO users (id, username, password, email, full_name, role) VALUES 
        (100, '$admin_user', '$hashed_pass', 'admin@shop.com', 'Administrator', 'admin')";

echo "<div style='font-family: sans-serif; padding: 20px; background: #070b1f; color: #fff; min-height: 100vh;'>";
echo "<h1>🛠️ Admin Account Repair Tool</h1>";

if (mysqli_query($conn, $sql)) {
    echo "<div style='background: rgba(94, 213, 138, 0.2); border: 1px solid #5ed58a; padding: 15px; border-radius: 10px; color: #5ed58a; margin-bottom: 20px;'>
            ✅ SUCCESS: The admin account has been updated!
          </div>";
    echo "<p><strong>Credentials:</strong></p>";
    echo "<ul>
            <li>Username: <code>$admin_user</code></li>
            <li>Password: <code>$admin_pass</code></li>
          </ul>";
    echo "<p>Please <a href='login.php' style='color: #7e8bff;'>go to the login page</a> and try again.</p>";
} else {
    echo "<div style='background: rgba(255, 91, 120, 0.2); border: 1px solid #ff5b78; padding: 15px; border-radius: 10px; color: #ff5b78;'>
            ❌ ERROR: Could not update the account. " . mysqli_error($conn) . "
          </div>";
}

echo "<hr style='border: 0; border-top: 1px solid rgba(255,255,255,0.1); margin: 30px 0;'>";
echo "<p style='font-size: 0.8rem; color: #777;'>Note: This script should be deleted after use for security.</p>";
echo "</div>";
?>