<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../lib/Database.php';
require_once __DIR__ . '/../lib/Logger.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        $error = 'Username and password are required.';
    } else {
        $db = Database::getInstance();
        $user = $db->getUserByUsername($username);
        
        if ($user) {
            // Database user - verify password and check status
            if (password_verify($password, $user['password'])) {
                if ($user['status'] === 'approved') {
                    // Login successful
                    $_SESSION['authenticated'] = true;
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['role'] = $user['role'];
                    $_SESSION['ai_provider'] = $user['ai_provider'];
                    $_SESSION['ai_model'] = $user['ai_model'];
                    $_SESSION['use_mock'] = $user['use_mock'];
                    $_SESSION['use_mock_patent'] = $user['use_mock_patent'];
                    
                    Logger::info("User logged in: $username (ID: {$user['id']}, Role: {$user['role']})");
                    header('Location: index.php');
                    exit;
                } elseif ($user['status'] === 'pending') {
                    $error = 'Your account is pending approval. Please wait for an administrator to approve your account.';
                    Logger::info("Pending login attempt for user: $username");
                } elseif ($user['status'] === 'rejected') {
                    $error = 'Your account has been rejected. Please contact the administrator.';
                    Logger::info("Rejected login attempt for user: $username");
                } else {
                    $error = 'Your account is not active. Please contact the administrator.';
                }
            } else {
                $error = 'Invalid credentials';
                Logger::info("Failed login attempt for user: $username (wrong password)");
            }
        } else {
            // Check for hardcoded admin (for backward compatibility)
            if ($username === ADMIN_USERNAME && $password === ADMIN_PASSWORD) {
                $_SESSION['authenticated'] = true;
                $_SESSION['user_id'] = 0;
                $_SESSION['username'] = $username;
                $_SESSION['role'] = 'admin';
                $_SESSION['ai_provider'] = 'google';
                $_SESSION['ai_model'] = 'gemini-2.5-flash';
                Logger::info("Admin logged in: $username");
                header('Location: index.php');
                exit;
            } else {
                $error = 'Invalid credentials';
                Logger::info("Failed login attempt for user: $username");
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
    <title>Login - Patent Analysis MVP</title>
    <link rel="stylesheet" href="assets/styles.css">
</head>
<body class="login-page">
    <div class="login-container">
        <div class="login-box">
            <h1>Patent Analysis MVP</h1>
            <p class="subtitle">Login</p>

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
                <p><small>New user? <a href="register.php">Register here</a></small></p>
                <p><small>Admin: Default credentials are admin/admin</small></p>
            </div>
        </div>
    </div>
</body>
</html>

