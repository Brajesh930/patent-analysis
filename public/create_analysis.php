<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../lib/Database.php';
require_once __DIR__ . '/../lib/Logger.php';

if (!isset($_SESSION['authenticated'])) {
    header('Location: login.php');
    exit;
}

$db = Database::getInstance();
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'] ?? '';
    $seedType = $_POST['seed_type'] ?? '';
    $seedValue = $_POST['seed_value'] ?? '';
    
    if (empty($name) || empty($seedType) || empty($seedValue)) {
        $error = 'All fields are required.';
    } else {
        try {
            $analysisId = $db->createAnalysis($name, $seedType, $seedValue);
            Logger::info("Analysis created: ID=$analysisId, Name=$name");
            header('Location: step1.php?analysis_id=' . $analysisId);
            exit;
        } catch (Exception $e) {
            $error = 'Failed to create analysis: ' . $e->getMessage();
            Logger::error("Failed to create analysis: " . $e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Analysis - Patent Analysis MVP</title>
    <link rel="stylesheet" href="assets/styles.css">
</head>
<body>
    <div class="container">
        <header>
            <h1>🔍 Patent Analysis MVP</h1>
            <div class="header-actions">
                <a href="index.php" class="link">← Back to Analyses</a>
                <a href="logout.php" class="btn btn-logout">Logout</a>
            </div>
        </header>

        <main>
            <section class="form-section">
                <h2>Create New Analysis</h2>

                <?php if ($error): ?>
                    <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>

                <form method="POST" class="form">
                    <div class="form-group">
                        <label for="name">Analysis Name:</label>
                        <input type="text" id="name" name="name" required placeholder="e.g., AI Patent Analysis 2026">
                    </div>

                    <div class="form-group">
                        <label for="seed_type">Seed Input Type:</label>
                        <select id="seed_type" name="seed_type" required>
                            <option value="">-- Select --</option>
                            <option value="patent_number">Patent Number</option>
                            <option value="free_text">Free Text</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="seed_value">Seed Value:</label>
                        <textarea id="seed_value" name="seed_value" rows="6" required 
                                  placeholder="Enter patent number (e.g., US10123456B2) or free text"></textarea>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">Create Analysis</button>
                        <a href="index.php" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </section>
        </main>
    </div>

    <footer>
        <p>&copy; 2026 Patent Analysis MVP</p>
    </footer>
</body>
</html>
