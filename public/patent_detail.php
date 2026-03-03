<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../lib/Database.php';
require_once __DIR__ . '/../lib/Logger.php';

if (!isset($_SESSION['authenticated'])) {
    header('Location: login.php');
    exit;
}

$db = Database::getInstance();
$patentId = $_GET['patent_id'] ?? null;

if (!$patentId) {
    header('Location: index.php');
    exit;
}

$patent = $db->getPatent($patentId);
if (!$patent) {
    die('Patent not found');
}

$result = $db->getScreeningResult($patentId);
$analysis = $db->getAnalysis($patent['analysis_id']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patent Details - Patent Analysis MVP</title>
    <link rel="stylesheet" href="assets/styles.css">
</head>
<body>
    <div class="container">
        <header>
            <h1>🔍 Patent Analysis MVP</h1>
            <div class="header-actions">
                <span><?php echo htmlspecialchars($analysis['name']); ?></span>
                <a href="logout.php" class="btn btn-logout">Logout</a>
            </div>
        </header>

        <main>
            <section class="detail-section">
                <div class="detail-header">
                    <h2><?php echo htmlspecialchars($patent['patent_number']); ?></h2>
                    <a href="step3.php?analysis_id=<?php echo $patent['analysis_id']; ?>" class="btn btn-secondary">← Back to Results</a>
                </div>

                <?php if ($result): ?>
                    <div class="card">
                        <h3>Screening Result</h3>
                        <div class="result-summary">
                            <p><strong>Label:</strong> <span class="label-badge <?php echo htmlspecialchars($result['overall_label']); ?>">
                                <?php echo htmlspecialchars($result['overall_label']); ?>
                            </span></p>
                            <p><strong>Score:</strong> <?php echo htmlspecialchars($result['overall_score']); ?>/100</p>
                            <p><strong>Reasoning:</strong> <?php echo htmlspecialchars($result['reasoning_short']); ?></p>
                        </div>
                    </div>

                    <div class="card">
                        <h3>Per-Element Analysis</h3>
                        <?php
                            $perElem = json_decode($result['per_element_json'], true);
                            if ($perElem):
                        ?>
                            <div class="elements-breakdown">
                                <?php foreach ($perElem as $idx => $elem): ?>
                                    <div class="element-detail">
                                        <h4>Element <?php echo $idx + 1; ?></h4>
                                        <p><strong>Label:</strong> <span class="label-badge-small">
                                            <?php echo htmlspecialchars($elem['label']); ?>
                                        </span></p>
                                        <p><strong>Score:</strong> <?php echo $elem['score']; ?>/5</p>
                                        <p><strong>Rationale:</strong> <?php echo htmlspecialchars($elem['rationale']); ?></p>
                                        
                                        <?php if (!empty($elem['evidence'])): ?>
                                            <p><strong>Evidence:</strong></p>
                                            <ul>
                                                <?php foreach ($elem['evidence'] as $ev): ?>
                                                    <li>
                                                        <em><?php echo htmlspecialchars($ev['location']); ?>:</em>
                                                        <br><code><?php echo htmlspecialchars(substr($ev['snippet'], 0, 200)); ?></code>
                                                    </li>
                                                <?php endforeach; ?>
                                            </ul>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <p>No screening results for this patent yet.</p>
                    </div>
                <?php endif; ?>

                <?php if ($patent['claims_text'] || $patent['abstract_text']): ?>
                    <div class="card">
                        <h3>Patent Text</h3>
                        
                        <?php if ($patent['claims_text']): ?>
                            <div>
                                <h4>Claims</h4>
                                <pre class="patent-text"><?php echo htmlspecialchars(substr($patent['claims_text'], 0, 1000)); ?><?php if (strlen($patent['claims_text']) > 1000) echo '...'; ?></pre>
                            </div>
                        <?php endif; ?>

                        <?php if ($patent['abstract_text']): ?>
                            <div>
                                <h4>Abstract</h4>
                                <pre class="patent-text"><?php echo htmlspecialchars(substr($patent['abstract_text'], 0, 1000)); ?><?php if (strlen($patent['abstract_text']) > 1000) echo '...'; ?></pre>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <div class="step-nav">
                    <a href="step3.php?analysis_id=<?php echo $patent['analysis_id']; ?>" class="btn btn-secondary">← Back to Results</a>
                </div>
            </section>
        </main>
    </div>

    <footer>
        <p>&copy; 2026 Patent Analysis MVP</p>
    </footer>
</body>
</html>
