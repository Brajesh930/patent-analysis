<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../lib/Database.php';
require_once __DIR__ . '/../lib/Logger.php';

// Check authentication
if (!isset($_SESSION['authenticated'])) {
    header('Location: login.php');
    exit;
}

$db = Database::getInstance();
$analyses = $db->getAllAnalyses();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patent Analysis MVP</title>
    <link rel="stylesheet" href="assets/styles.css">
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
            <section class="analyses-section">
                <div class="section-header">
                    <h2>My Analyses</h2>
                    <a href="create_analysis.php" class="btn btn-primary">+ Create New Analysis</a>
                </div>

                <?php if (empty($analyses)): ?>
                    <div class="empty-state">
                        <p>No analyses yet. <a href="create_analysis.php">Create your first one.</a></p>
                    </div>
                <?php else: ?>
                    <table class="analyses-table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Seed Type</th>
                                <th>Seed Value</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($analyses as $analysis): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($analysis['name']); ?></td>
                                    <td><?php echo htmlspecialchars($analysis['seed_type']); ?></td>
                                    <td><?php echo htmlspecialchars(substr($analysis['seed_value'], 0, 40)); ?></td>
                                    <td><?php echo date('Y-m-d H:i', strtotime($analysis['created_at'])); ?></td>
                                    <td>
                                        <a href="step1.php?analysis_id=<?php echo $analysis['id']; ?>" class="link">Step 1</a>
                                        <a href="step2.php?analysis_id=<?php echo $analysis['id']; ?>" class="link">Step 2</a>
                                        <a href="step3.php?analysis_id=<?php echo $analysis['id']; ?>" class="link">Step 3</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </section>
        </main>
    </div>

    <footer>
        <p>&copy; 2026 Patent Analysis MVP | <a href="https://example.com">Documentation</a></p>
    </footer>
</body>
</html>
