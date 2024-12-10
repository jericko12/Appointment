<?php
require_once 'config/db_connect.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $role = 'client'; // Default role is client

    $stmt = $pdo->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, ?)");
    $stmt->execute([$username, $password, $role]);
    
    header("Location: login.php");
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Register</title>
    <link rel="stylesheet" href="css/auth.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
</head>
<body>
    <div class="auth-container">
        <div class="auth-box">
            <h2>Register</h2>
            <form method="POST">
                <div class="input-group">
                    <i class="fas fa-user floating-icon"></i>
                    <input type="text" name="username" id="username" placeholder=" " required>
                    <label for="username">Username</label>
                </div>
                <div class="input-group">
                    <i class="fas fa-lock floating-icon"></i>
                    <input type="password" name="password" id="password" placeholder=" " required>
                    <label for="password">Password</label>
                </div>
                <button type="submit" class="auth-btn">
                    <i class="fas fa-user-plus"></i> Register
                </button>
            </form>
            <div class="auth-links">
                <p>Already have an account? <a href="login.php">Login here</a></p>
            </div>
        </div>
    </div>
</body>
</html> 