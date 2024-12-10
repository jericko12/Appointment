<?php
session_start();
require_once 'config/db_connect.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];

    try {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['role'] = $user['role'];
            
            if ($user['role'] == 'admin') {
                header("Location: admin/dashboard.php");
                exit();
            } elseif ($user['role'] == 'moderator') {
                header("Location: moderator/dashboard.php");
                exit();
            } else {
                header("Location: client/dashboard.php");
                exit();
            }
        } else {
            $error = 'Invalid username or password';
        }
    } catch (PDOException $e) {
        $error = "Database error: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Login</title>
    <link rel="stylesheet" href="css/auth.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
</head>
<body>
    <div class="auth-container">
        <div class="auth-box">
            <h2>Login</h2>
            <?php if ($error): ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
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
                    <i class="fas fa-sign-in-alt"></i> Login
                </button>
            </form>
            <div class="auth-links">
                <p>Don't have an account? <a href="register.php">Register here</a></p>
            </div>
        </div>
    </div>
</body>
</html> 