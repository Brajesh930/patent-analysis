<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../lib/Logger.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if ($username === ADMIN_USERNAME && $password === ADMIN_PASSWORD) {
        $_SESSION['authenticated'] = true;
        $_SESSION['username'] = $username;
        Logger::info("User logged in: $username");
        header('Location: index.php');
        exit;
    } else {
        $error = 'Invalid credentials';
        Logger::info("Failed login attempt for user: $username");
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Patent Analysis MVP</title>
    <link rel="stylesheet" href="assets/styles.css">
</head>
<body class="login-page">
    <div class="login-container">
        <div class="login-box">
            <h1>Patent Analysis MVP</h1>
            <p class="subtitle">Admin Login</p>

            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form method="POST" class="login-form">
                <div class="form-group">
                    <label for="username">Username:</label>
                    <input type="text" id="username" name="username" required autofocus>
                </div>

                <div class="form-group">
                    <label for="password">Password:</label>
                    <input type="password" id="password" name="password" required>
                </div>

                <button type="submit" class="btn btn-primary btn-block">Login</button>
            </form>

            <div class="login-info">
                <p><small>MVP: Default credentials are admin/admin</small></p>
            </div>
        </div>
    </div>
</body>
</html>
