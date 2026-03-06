<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../lib/Database.php';
require_once __DIR__ . '/../lib/Logger.php';

// Check authentication
if (!isset($_SESSION['authenticated'])) {
    header('Location: login.php');
    exit;
}

// Load unified AI service (consolidated from config/ai-config.php and lib/AiProvider.php)
require_once __DIR__ . '/../lib/AiApiService.php';

$configFile = __DIR__ . '/../data/ai_settings.json';
$settings = [];
$message = '';
$errorMsg = '';

// Load existing settings
if (file_exists($configFile)) {
    $settings = json_decode(file_get_contents($configFile), true) ?: [];
}

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

// Check if form submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_config') {
    try {
        $provider = $_POST['ai_provider'] ?? 'google';
        $model = $_POST['ai_model'] ?? '';
        $apiKey = $_POST['api_key'] ?? '';
        $useMock = isset($_POST['use_mock']) ? 1 : 0;
        $useMockPatent = isset($_POST['use_mock_patent']) ? 1 : 0;
        
        if (!$model) {
            throw new Exception('Please select a model.');
        }
        
        $settings = [
            'provider' => $provider,
            'model' => $model,
            'api_key' => $apiKey,
            'use_mock' => $useMock,
            'use_mock_patent' => $useMockPatent,
            'updated_at' => date('Y-m-d H:i:s')
        ];
        
        // Create data directory if it doesn't exist
        if (!is_dir(dirname($configFile))) {
            mkdir(dirname($configFile), 0755, true);
        }
        
        file_put_contents($configFile, json_encode($settings, JSON_PRETTY_PRINT));
        
        $_SESSION['ai_provider'] = $provider;
        $_SESSION['ai_model'] = $model;
        $_SESSION['use_mock'] = $useMock;
        $_SESSION['use_mock_patent'] = $useMockPatent;
        
        $message = 'Configuration saved successfully!';
        Logger::info("AI configuration updated: Provider=$provider, Model=$model, UseMock=$useMock, UseMockPatent=$useMockPatent");
    } catch (Exception $e) {
        $errorMsg = 'Error saving configuration: ' . $e->getMessage();
        Logger::error("Failed to save AI config: " . $e->getMessage());
    }
}

// Get current settings from file or defaults
$currentProvider = $settings['provider'] ?? 'google';
$currentModel = $settings['model'] ?? 'gemini-2.5-flash';
$currentApiKey = $settings['api_key'] ?? '';
$useMockMode = $settings['use_mock'] ?? false;
$useMockPatentMode = $settings['use_mock_patent'] ?? false;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Configuration - Patent Analysis MVP</title>
    <link rel="stylesheet" href="assets/styles.css">
    <style>
        .config-container {
            max-width: 600px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .config-header {
            margin-bottom: 30px;
            text-align: center;
        }
        
        .config-header h2 {
            color: #0066cc;
            margin-bottom: 10px;
        }
        
        .config-header p {
            color: #666;
            font-size: 14px;
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
        
        .form-group select,
        .form-group input {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            font-family: inherit;
        }
        
        .form-group select:focus,
        .form-group input:focus {
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
        
        .provider-info {
            background: #f0f8ff;
            padding: 15px;
            border-radius: 4px;
            margin-top: 10px;
            font-size: 12px;
            color: #555;
            border-left: 4px solid #0066cc;
        }
        
        .checkbox-group {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .checkbox-group input {
            width: auto;
            margin-right: 10px;
            cursor: pointer;
        }
        
        .checkbox-group label {
            margin: 0;
            cursor: pointer;
            font-weight: normal;
        }
        
        .button-group {
            display: flex;
            gap: 10px;
            margin-top: 30px;
        }
        
        .button-group .btn {
            flex: 1;
            padding: 12px;
            font-size: 14px;
            font-weight: 600;
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
        
        .models-grid {
            background: #f9f9f9;
            padding: 12px;
            border-radius: 4px;
            margin-top: 10px;
        }
        
        .test-connection {
            margin-top: 20px;
            padding: 15px;
            background: #fffbea;
            border-left: 4px solid #ffc107;
            border-radius: 4px;
        }
        
        .test-connection button {
            margin-top: 10px;
            padding: 8px 15px;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1>🔍 Patent Analysis MVP</h1>
            <div class="header-actions">
                <span>Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?></span>
                <a href="logout.php" class="btn btn-logout">Logout</a>
            </div>
        </header>

        <main>
            <div class="config-container">
                <div class="config-header">
                    <h2>⚙️ AI Configuration</h2>
                    <p>Configure your AI provider, model, and API key</p>
                </div>

                <?php if ($message): ?>
                    <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
                <?php endif; ?>
                
                <?php if ($errorMsg): ?>
                    <div class="alert alert-error"><?php echo htmlspecialchars($errorMsg); ?></div>
                <?php endif; ?>

                <form method="POST">
                    <input type="hidden" name="action" value="save_config">

                    <!-- AI Provider Selection -->
                    <div class="form-group">
                        <label for="ai_provider">AI Provider:</label>
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
                                Ollama (Local) - Free, runs locally
                            </option>
                        </select>
                        <small>Choose your preferred AI provider</small>
                    </div>

                    <!-- Model Selection -->
                    <div class="form-group">
                        <label for="ai_model">AI Model:</label>
                        <select id="ai_model" name="ai_model">
                            <?php 
                            $models = $modelsByProvider[$currentProvider] ?? [];
                            foreach ($models as $modelId => $modelLabel):
                            ?>
                                <option value="<?php echo $modelId; ?>" 
                                        <?php echo $currentModel === $modelId ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($modelLabel); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small id="modelInfo" style="display:block; margin-top:8px; color:#666;"></small>
                    </div>

                    <!-- API Key Input -->
                    <div class="form-group">
                        <label for="api_key">API Key:</label>
                        <input type="password" id="api_key" name="api_key" 
                               value="<?php echo htmlspecialchars($currentApiKey); ?>" 
                               placeholder="Enter your API key">
                        <small>Your API key is stored securely and used only for API calls</small>
                    </div>

                    <!-- Mock Mode Toggle -->
                    <div class="checkbox-group">
                        <input type="checkbox" id="use_mock" name="use_mock" 
                               <?php echo $useMockMode ? 'checked' : ''; ?>>
                        <label for="use_mock">Use Mock Mode for AI (for testing without API calls)</label>
                    </div>

                    <!-- Patent API Mock Mode Toggle -->
                    <div class="checkbox-group">
                        <input type="checkbox" id="use_mock_patent" name="use_mock_patent" 
                               <?php echo $useMockPatentMode ? 'checked' : ''; ?>>
                        <label for="use_mock_patent">Use Mock Patent API (for testing without real patent data)</label>
                    </div>

                    <!-- Provider Info -->
                    <div class="provider-info" id="providerInfo">
                        <strong>ℹ️ Google AI (Gemini)</strong><br>
                        Free tier available. Rate limits: 60 requests/min for free tier.<br>
                        <a href="https://ai.google.dev/" target="_blank" style="color:#0066cc;">Get API Key →</a>
                    </div>

                    <!-- Buttons -->
                    <div class="button-group">
                        <a href="index.php" class="btn btn-secondary">← Back</a>
                        <button type="submit" class="btn btn-primary">💾 Save Configuration</button>
                    </div>
                </form>

                <!-- Test Connection -->
                <div class="test-connection">
                    <strong>ℹ️ Note:</strong> After saving, your configuration will be active immediately. 
                    The application will use this AI provider for:
                    <ul style="margin: 10px 0 0 20px;">
                        <li>Generating element context</li>
                        <li>Screening patents for relevance</li>
                        <li>All AI-powered features</li>
                    </ul>
                </div>
            </div>
        </main>
    </div>

    <footer>
        <p>&copy; 2026 Patent Analysis MVP</p>
    </footer>

    <script>
        const modelsByProvider = <?php echo json_encode($modelsByProvider); ?>;
        const currentModel = '<?php echo htmlspecialchars($currentModel); ?>';
        const currentProvider = '<?php echo htmlspecialchars($currentProvider); ?>';
        
        const providerInfos = {
            'google': {
                name: 'Google AI (Gemini)',
                info: 'Free tier available. Rate limits: 60 requests/min for free tier.<br><a href="https://ai.google.dev/" target="_blank" style="color:#0066cc;">Get API Key →</a>',
                note: 'Requires an API key. Sign up at ai.google.dev'
            },
            'openai': {
                name: 'OpenAI',
                info: 'Paid service. Pricing: $0.03 per 1K input tokens for GPT-3.5, $0.15 per 1K for GPT-4.<br><a href="https://platform.openai.com/" target="_blank" style="color:#0066cc;">Get API Key →</a>',
                note: 'Requires valid payment method'
            },
            'anthropic': {
                name: 'Anthropic Claude',
                info: 'Paid service. Pricing varies by model.<br><a href="https://www.anthropic.com/" target="_blank" style="color:#0066cc;">Get API Key →</a>',
                note: 'Claude models are excellent for reasoning tasks'
            },
            'deepseek': {
                name: 'Deepseek',
                info: 'Paid service.<br><a href="https://www.deepseek.com/" target="_blank" style="color:#0066cc;">Get API Key →</a>',
                note: 'Cost-effective alternative to OpenAI'
            },
            'azure': {
                name: 'Azure OpenAI',
                info: 'Paid enterprise service. Integrated with Azure.<br><a href="https://azure.microsoft.com/en-us/products/ai-services/openai-service/" target="_blank" style="color:#0066cc;">Get API Key →</a>',
                note: 'Requires Azure subscription'
            },
            'ollama': {
                name: 'Ollama (Local)',
                info: 'Free, runs on your machine. No API key needed.<br><a href="https://ollama.ai/" target="_blank" style="color:#0066cc;">Download Ollama →</a>',
                note: 'Models run locally - fast but resource-intensive'
            }
        };

        function updateModels() {
            const provider = document.getElementById('ai_provider').value;
            const modelSelect = document.getElementById('ai_model');
            const models = modelsByProvider[provider] || {};
            
            // Store current selection before clearing
            let selectedModel = modelSelect.value;
            
            // Clear existing options
            modelSelect.innerHTML = '';
            
            // Add new options
            for (const [modelId, modelLabel] of Object.entries(models)) {
                const option = document.createElement('option');
                option.value = modelId;
                option.textContent = modelLabel;
                
                // Check if this is the current saved model from PHP or previously selected
                if (modelId === currentModel && provider === currentProvider) {
                    option.selected = true;
                    selectedModel = modelId;
                } else if (modelId === selectedModel) {
                    option.selected = true;
                }
                
                modelSelect.appendChild(option);
            }
            
            // If nothing selected, select the first one
            if (!selectedModel && modelSelect.options.length > 0) {
                modelSelect.options[0].selected = true;
            }
            
            // Update provider info
            const info = providerInfos[provider] || {};
            document.getElementById('providerInfo').innerHTML = `
                <strong>ℹ️ ${info.name}</strong><br>
                ${info.info || ''}<br>
                <small>${info.note || ''}</small>
            `;
        }

        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            updateModels();
        });
    </script>
</body>
</html>
