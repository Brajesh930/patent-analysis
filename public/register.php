<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../lib/Database.php';
require_once __DIR__ . '/../lib/Logger.php';

$error = '';
$success = '';

// Define available models for each provider
$modelsByProvider = [
    'openai' => [
        'gpt-4-turbo' => 'GPT-4 Turbo',
        'gpt-4' => 'GPT-4',
        'gpt-4o' => 'GPT-4o',
        'gpt-3.5-turbo' => 'GPT-3.5 Turbo',
    ],
    'google' => [
        'gemini-2.5-flash' => 'Gemini 2.5 Flash',
        'gemini-2.0-flash' => 'Gemini 2.0 Flash',
    ],
    'deepseek' => [
        'deepseek-chat' => 'Deepseek Chat',
    ],
    'azure' => [
        'gpt-4' => 'GPT-4',
        'gpt-4-turbo' => 'GPT-4 Turbo',
    ],
    'anthropic' => [
        'claude-3-opus-20240229' => 'Claude 3 Opus',
        'claude-3-sonnet-20240229' => 'Claude 3 Sonnet',
        'claude-3-haiku-20240307' => 'Claude 3 Haiku',
    ],
    'ollama' => [
        'mistral' => 'Mistral',
        'llama2' => 'Llama 2',
        'neural-chat' => 'Neural Chat',
    ],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $email = trim($_POST['email'] ?? '');
    $apiKey = trim($_POST['api_key'] ?? '');
    $aiProvider = $_POST['ai_provider'] ?? 'google';
    $aiModel = $_POST['ai_model'] ?? 'gemini-2.5-flash';
    
    // Validation
    if (empty($username) || empty($password) || empty($confirmPassword)) {
        $error = 'Username and password are required.';
    } elseif (strlen($username) < 3) {
        $error = 'Username must be at least 3 characters.';
    } elseif (strlen($password) < 4) {
        $error = 'Password must be at least 4 characters.';
    } elseif ($password !== $confirmPassword) {
        $error = 'Passwords do not match.';
    } elseif (!isset($modelsByProvider[$aiProvider])) {
        $error = 'Invalid AI provider selected.';
    } else {
        $db = Database::getInstance();
        
        // Check if username exists
        if ($db->userExists($username)) {
            $error = 'Username already exists. Please choose another.';
        } else {
            try {
                $db->createUser($username, $password, $email, $apiKey, $aiProvider, $aiModel);
                $success = 'Registration successful! Your account is pending approval by an administrator. You will be able to login once approved.';
                Logger::info("New user registered: $username (pending approval)");
            } catch (Exception $e) {
                $error = 'Registration failed. Please try again.';
                Logger::error("Registration failed for $username: " . $e->getMessage());
            }
        }
    }
}

$currentProvider = $_POST['ai_provider'] ?? 'google';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Patent Analysis MVP</title>
    <link rel="stylesheet" href="assets/styles.css">
    <style>
        .register-container {
            max-width: 500px;
            margin: 40px auto;
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .register-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .register-header h1 {
            color: #0066cc;
            margin-bottom: 10px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
        }
        
        .form-group input,
        .form-group select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            font-family: inherit;
            box-sizing: border-box;
        }
        
        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #0066cc;
            box-shadow: 0 0 0 3px rgba(0, 102, 204, 0.1);
        }
        
        .form-group small {
            display: block;
            margin-top: 5px;
            color: #666;
            font-size: 12px;
        }
        
        .alert {
            padding: 12px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .btn-block {
            width: 100%;
            padding: 12px;
            font-size: 16px;
        }
        
        .login-link {
            text-align: center;
            margin-top: 20px;
            color: #666;
        }
        
        .login-link a {
            color: #0066cc;
            text-decoration: none;
        }
        
        .login-link a:hover {
            text-decoration: underline;
        }
        
        .ai-config-section {
            background: #f9f9f9;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .ai-config-section h3 {
            margin-top: 0;
            margin-bottom: 15px;
            color: #333;
            font-size: 16px;
        }
    </style>
</head>
<body class="login-page">
    <div class="login-container">
        <div class="register-container">
            <div class="register-header">
                <h1>📝 Register</h1>
                <p>Create your account to use Patent Analysis</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                <div class="login-link">
                    <a href="login.php">← Back to Login</a>
                </div>
            <?php else: ?>
                <form method="POST" class="register-form">
                    <div class="form-group">
                        <label for="username">Username *</label>
                        <input type="text" id="username" name="username" required minlength="3" 
                               value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">
                        <small>At least 3 characters</small>
                    </div>

                    <div class="form-group">
                        <label for="email">Email (optional)</label>
                        <input type="email" id="email" name="email" 
                               value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                    </div>

                    <div class="form-group">
                        <label for="password">Password *</label>
                        <input type="password" id="password" name="password" required minlength="4">
                        <small>At least 4 characters</small>
                    </div>

                    <div class="form-group">
                        <label for="confirm_password">Confirm Password *</label>
                        <input type="password" id="confirm_password" name="confirm_password" required>
                    </div>

                    <div class="ai-config-section">
                        <h3>🤖 AI Configuration</h3>
                        <p style="font-size: 12px; color: #666; margin-bottom: 15px;">
                            Configure your own AI settings. Each user has their own API key and configuration.
                        </p>
                        
                        <div class="form-group">
                            <label for="ai_provider">AI Provider</label>
                            <select id="ai_provider" name="ai_provider" onchange="updateModels()">
                                <option value="google" <?php echo $currentProvider === 'google' ? 'selected' : ''; ?>>
                                    Google AI (Gemini) - Free tier available
                                </option>
                                <option value="openai" <?php echo $currentProvider === 'openai' ? 'selected' : ''; ?>>
                                    OpenAI (GPT-4, GPT-3.5) - Paid
                                </option>
                                <option value="anthropic" <?php echo $currentProvider === 'anthropic' ? 'selected' : ''; ?>>
                                    Anthropic (Claude) - Paid
                                </option>
                                <option value="deepseek" <?php echo $currentProvider === 'deepseek' ? 'selected' : ''; ?>>
                                    Deepseek - Paid
                                </option>
                                <option value="azure" <?php echo $currentProvider === 'azure' ? 'selected' : ''; ?>>
                                    Azure OpenAI - Paid
                                </option>
                                <option value="ollama" <?php echo $currentProvider === 'ollama' ? 'selected' : ''; ?>>
                                    Ollama (Local) - Free
                                </option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="ai_model">AI Model</label>
                            <select id="ai_model" name="ai_model">
                                <?php 
                                $models = $modelsByProvider[$currentProvider] ?? [];
                                foreach ($models as $modelId => $modelLabel):
                                ?>
                                    <option value="<?php echo $modelId; ?>" 
                                            <?php echo ($_POST['ai_model'] ?? 'gemini-2.5-flash') === $modelId ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($modelLabel); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="api_key">API Key</label>
                            <input type="password" id="api_key" name="api_key" 
                                   value="<?php echo htmlspecialchars($_POST['api_key'] ?? ''); ?>"
                                   placeholder="Enter your API key">
                            <small>Your API key is stored securely</small>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary btn-block">Register</button>
                </form>

                <div class="login-link">
                    Already have an account? <a href="login.php">Login here</a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        const modelsByProvider = <?php echo json_encode($modelsByProvider); ?>;
        
        function updateModels() {
            const provider = document.getElementById('ai_provider').value;
            const modelSelect = document.getElementById('ai_model');
            const models = modelsByProvider[provider] || {};
            
            modelSelect.innerHTML = '';
            
            for (const [modelId, modelLabel] of Object.entries(models)) {
                const option = document.createElement('option');
                option.value = modelId;
                option.textContent = modelLabel;
                modelSelect.appendChild(option);
            }
        }
    </script>
</body>
</html>

