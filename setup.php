<?php
// setup.php - Database installation script
require_once 'db.php';

echo "<!DOCTYPE html>
<html>
<head>
    <title>Database Setup</title>
    <style>
        body { font-family: monospace; padding: 20px; background: #0d122c; color: white; }
        .success { color: #5ed58a; }
        .error { color: #ff5858; }
    </style>
</head>
<body>
<h1>🔧 E-Commerce Database Setup</h1>";

$sql_file = file_get_contents('database.sql');
if ($sql_file === false) {
    echo "<p class='error'>❌ Could not read database.sql file</p>";
} else {
    $commands = array_filter(array_map('trim', explode(';', $sql_file)));
    $success_count = 0;
    $error_count = 0;
    
    foreach ($commands as $command) {
        if (!empty($command)) {
            if (mysqli_query($conn, $command)) {
                $success_count++;
            } else {
                $error_count++;
                echo "<p class='error'>❌ Error: " . htmlspecialchars(mysqli_error($conn)) . "</p>";
            }
        }
    }
    
    echo "<p class='success'>✅ Successful queries: $success_count</p>";
    if ($error_count > 0) {
        echo "<p class='error'>❌ Failed queries: $error_count</p>";
    }
}

echo "<hr>";
echo "<p class='success'>🔐 Test Login Credentials:</p>";
echo "<ul>";
echo "<li>Username: <strong>admin</strong></li>";
echo "<li>Password: <strong>password123</strong></li>";
echo "</ul>";
echo "<p><a href='login.php' style='color: #7e8bff;'>→ Go to Login Page</a></p>";
echo "</body></html>";
