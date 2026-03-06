<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../lib/Database.php';
require_once __DIR__ . '/../lib/Logger.php';

if (!isset($_SESSION['authenticated'])) {
    header('Location: login.php');
    exit;
}

$db = Database::getInstance();
$analysisId = $_GET['analysis_id'] ?? null;

if (!$analysisId) {
    header('Location: index.php');
    exit;
}

$analysis = $db->getAnalysis($analysisId);
if (!$analysis) {
    die('Analysis not found');
}

// Get ranked results
$results = $db->getAnalysisResults($analysisId);

// Handle export
if (isset($_GET['export'])) {
    $exportType = $_GET['export'];
    
    if ($exportType === 'csv') {
        // Generate CSV
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $analysis['name'] . '_results.csv"');
        
        $fp = fopen('php://output', 'w');
        fputcsv($fp, ['Patent Number', 'Label', 'Score', 'Reasoning', 'Per-Element Summary']);
        
        foreach ($results as $result) {
            $perElemSummary = '';
            if ($result['per_element_json']) {
                $perElem = json_decode($result['per_element_json'], true);
                $counts = ['explicit' => 0, 'implicit' => 0, 'partial' => 0, 'none' => 0];
                foreach ($perElem as $elem) {
                    $counts[$elem['label']]++;
                }
                $perElemSummary = implode('; ', array_map(
                    fn($k, $v) => "$k: $v",
                    array_keys($counts),
                    array_values($counts)
                ));
            }
            
            fputcsv($fp, [
                $result['patent_number'],
                $result['overall_label'] ?? '',
                $result['overall_score'] ?? '',
                $result['reasoning_short'] ?? '',
                $perElemSummary
            ]);
        }
        
        fclose($fp);
        Logger::info("Exported CSV for analysis $analysisId");
        exit;
    }
    
    if ($exportType === 'html') {
        // Generate HTML report
        ob_start();
        ?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($analysis['name']); ?> - Patent Analysis Report</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; background: #f9f9f9; }
        .header { background: #333; color: white; padding: 20px; border-radius: 5px; }
        .summary { background: #e8f4f8; padding: 15px; margin: 20px 0; border-left: 4px solid #0066cc; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; background: white; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #f2f2f2; font-weight: bold; }
        tr:hover { background: #f5f5f5; }
        .relevant { color: green; font-weight: bold; }
        .borderline { color: orange; font-weight: bold; }
        .not_relevant { color: red; font-weight: bold; }
        .patent-detail { background: white; padding: 15px; margin: 20px 0; border: 1px solid #ddd; border-radius: 5px; }
        .element-score { display: inline-block; background: #e0e0e0; padding: 5px 10px; margin: 2px; border-radius: 3px; }
    </style>
</head>
<body>
    <div class="header">
        <h1><?php echo htmlspecialchars($analysis['name']); ?></h1>
        <p>Analysis Report</p>
        <p><small>Generated: <?php echo date('Y-m-d H:i:s'); ?></small></p>
    </div>

    <div class="summary">
        <h2>Summary</h2>
        <p><strong>Total Patents Analyzed:</strong> <?php echo count($results); ?></p>
        <p><strong>Seed Type:</strong> <?php echo htmlspecialchars($analysis['seed_type']); ?></p>
        <p><strong>Analysis Created:</strong> <?php echo date('Y-m-d H:i', strtotime($analysis['created_at'])); ?></p>
    </div>

    <h2>Results Overview</h2>
    <table>
        <thead>
            <tr>
                <th>Patent Number</th>
                <th>Relevance</th>
                <th>Score</th>
                <th>Reasoning</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($results as $result): ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($result['patent_number']); ?></strong></td>
                    <td><span class="<?php echo htmlspecialchars($result['overall_label']); ?>">
                        <?php echo htmlspecialchars($result['overall_label'] ?? 'Not Analyzed'); ?>
                    </span></td>
                    <td><?php echo htmlspecialchars($result['overall_score'] ?? '—'); ?></td>
                    <td><?php echo htmlspecialchars($result['reasoning_short'] ?? ''); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <h2>Detailed Results</h2>
    <?php foreach ($results as $result): 
        if ($result['per_element_json']) {
            $perElem = json_decode($result['per_element_json'], true);
    ?>
        <div class="patent-detail">
            <h3><?php echo htmlspecialchars($result['patent_number']); ?></h3>
            <p><strong>Overall Score:</strong> <?php echo htmlspecialchars($result['overall_score']); ?>/100</p>
            <p><strong>Label:</strong> <span class="<?php echo htmlspecialchars($result['overall_label']); ?>">
                <?php echo htmlspecialchars($result['overall_label']); ?>
            </span></p>
            
            <p><strong>Per-Element Breakdown:</strong></p>
            <div>
                <?php foreach ($perElem as $elem): ?>
                    <div class="element-score">
                        Element <?php echo $elem['element_index'] + 1; ?>: 
                        <strong><?php echo htmlspecialchars($elem['label']); ?></strong> 
                        (<?php echo $elem['score']; ?>/5)
                    </div>
                <?php endforeach; ?>
            </div>

            <p><strong>Reasoning:</strong> <?php echo htmlspecialchars($result['reasoning_short']); ?></p>
        </div>
    <?php }
    endforeach; ?>

    <footer style="text-align: center; margin-top: 40px; color: #999; border-top: 1px solid #ddd; padding-top: 20px;">
        <p>&copy; 2026 Patent Analysis MVP | Report Generated: <?php echo date('Y-m-d H:i:s'); ?></p>
    </footer>
</body>
</html>
        <?php
        $html = ob_get_clean();
        header('Content-Type: text/html; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $analysis['name'] . '_report.html"');
        echo $html;
        Logger::info("Exported HTML report for analysis $analysisId");
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Step 3: Results & Export - Patent Analysis MVP</title>
    <link rel="stylesheet" href="assets/styles.css">
</head>
<body>
    <div class="container">
        <header>
            <h1>🔍 Patent Analysis MVP</h1>
            <div class="header-actions">
                <span>Analysis: <?php echo htmlspecialchars($analysis['name']); ?></span>
                <a href="diagnostics.php" class="btn" style="background:#6c757d; color:white; padding:8px 12px; font-size:12px; margin-right:5px;">🔧 Diagnostics</a>
                <a href="ai_config.php" class="btn" style="background:#6c757d; color:white; padding:8px 12px; font-size:12px; margin-right:10px;">⚙️ AI Config</a>
                <a href="logout.php" class="btn btn-logout">Logout</a>
            </div>
        </header>

        <main>
            <nav class="workflow-nav">
                <a href="step1.php?analysis_id=<?php echo $analysisId; ?>" class="nav-step">Step 1: Seed</a>
                <a href="step2.php?analysis_id=<?php echo $analysisId; ?>" class="nav-step">Step 2: Screening</a>
                <a href="step3.php?analysis_id=<?php echo $analysisId; ?>" class="nav-step active">Step 3: Report</a>
            </nav>

            <section class="step-section">
                <h2>STEP 3: Ranking + Report Export</h2>

                <div class="card">
                    <h3>Results Summary</h3>
                    <p><strong>Total Patents:</strong> <?php echo count($results); ?></p>
                    <?php
                        $relevant = array_filter($results, fn($r) => $r['overall_label'] === 'relevant');
                        $borderline = array_filter($results, fn($r) => $r['overall_label'] === 'borderline');
                        $notRelevant = array_filter($results, fn($r) => $r['overall_label'] === 'not_relevant');
                    ?>
                    <p><span class="badge badge-success">Relevant: <?php echo count($relevant); ?></span>
                       <span class="badge badge-warning">Borderline: <?php echo count($borderline); ?></span>
                       <span class="badge badge-error">Not Relevant: <?php echo count($notRelevant); ?></span>
                    </p>
                </div>

                <?php if (!empty($results)): ?>
                    <div class="card">
                        <h3>Ranked Results (by score, descending)</h3>
                        
                        <div class="export-buttons">
                            <a href="step3.php?analysis_id=<?php echo $analysisId; ?>&export=csv" class="btn btn-primary">📥 Export CSV</a>
                            <a href="step3.php?analysis_id=<?php echo $analysisId; ?>&export=html" class="btn btn-primary">📄 Export HTML Report</a>
                        </div>

                        <table class="results-table">
                            <thead>
                                <tr>
                                    <th>Rank</th>
                                    <th>Patent Number</th>
                                    <th>Label</th>
                                    <th>Score</th>
                                    <th>Reasoning</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                    $rank = 1;
                                    foreach ($results as $result):
                                        if ($result['overall_label']):
                                ?>
                                    <tr>
                                        <td><?php echo $rank++; ?></td>
                                        <td><strong><?php echo htmlspecialchars($result['patent_number']); ?></strong></td>
                                        <td>
                                            <span class="label-badge <?php echo htmlspecialchars($result['overall_label']); ?>">
                                                <?php echo htmlspecialchars($result['overall_label']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($result['overall_score']); ?></td>
                                        <td><?php echo htmlspecialchars(substr($result['reasoning_short'], 0, 60)); ?></td>
                                        <td>
                                            <a href="patent_detail.php?patent_id=<?php echo $result['id']; ?>" class="link">Details</a>
                                        </td>
                                    </tr>
                                <?php 
                                        endif;
                                    endforeach;
                                ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <p>No screening results yet. Please complete Step 2.</p>
                    </div>
                <?php endif; ?>

                <!-- Navigation -->
                <div class="step-nav">
                    <a href="step2.php?analysis_id=<?php echo $analysisId; ?>" class="btn btn-secondary">← Step 2</a>
                    <a href="index.php" class="btn btn-secondary">Back to Analyses</a>
                </div>
            </section>
        </main>
    </div>

    <footer>
        <p>&copy; 2026 Patent Analysis MVP</p>
    </footer>
</body>
</html>
