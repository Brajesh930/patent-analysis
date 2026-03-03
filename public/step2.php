<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../lib/Database.php';
require_once __DIR__ . '/../lib/Logger.php';
require_once __DIR__ . '/../lib/PatentProvider.php';
require_once __DIR__ . '/../lib/AiProvider.php';

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

$keyElements = $db->getKeyElements($analysisId);
$patents = $db->getPatentsByAnalysis($analysisId);
$error = '';
$success = '';
$progress = '';

// Handle patent upload/paste
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    if ($action === 'add_patents') {
        $patentInput = $_POST['patent_input'] ?? '';
        $scope = $_POST['text_scope'] ?? 'claims+abstract';
        
        $patentNumbers = PatentProvider::parsePatentList($patentInput);
        
        if (empty($patentNumbers)) {
            $error = 'No patent numbers found.';
        } else if (count($patentNumbers) > MAX_PATENTS_MVP) {
            $error = 'Too many patents (max ' . MAX_PATENTS_MVP . ').';
        } else {
            try {
                foreach ($patentNumbers as $num) {
                    $existing = array_filter($patents, fn($p) => $p['patent_number'] === $num);
                    if (empty($existing)) {
                        $db->addPatent($analysisId, $num);
                    }
                }
                $patents = $db->getPatentsByAnalysis($analysisId);
                $success = 'Added ' . count($patentNumbers) . ' patents.';
                Logger::info("Added " . count($patentNumbers) . " patents to analysis $analysisId");
            } catch (Exception $e) {
                $error = 'Failed to add patents: ' . $e->getMessage();
            }
        }
    }
    
    if ($action === 'start_screening') {
        $scope = $_POST['text_scope'] ?? 'claims+abstract';
        $unscreenedPatents = array_filter($patents, fn($p) => !$db->getScreeningResult($p['id']));
        
        if (empty($unscreenedPatents)) {
            $error = 'All patents already screened.';
        } else if (empty($keyElements)) {
            $error = 'No key elements defined. Please complete Step 1.';
        } else {
            try {
                $elemContexts = [];
                foreach ($keyElements as $elem) {
                    if ($elem['context_json']) {
                        $elemContexts[] = json_decode($elem['context_json'], true);
                    }
                }
                
                if (empty($elemContexts)) {
                    $error = 'No approved key elements with context. Please complete Step 1.';
                } else {
                    $screened = 0;
                    $total = count($unscreenedPatents);
                    
                    foreach ($unscreenedPatents as $idx => $patent) {
                        // Fetch patent text if not already done
                        if (!$patent['claims_text']) {
                            $fetchResult = PatentProvider::fetchPatent($patent['patent_number'], $scope);
                            if ($fetchResult['status'] === 'ok') {
                                $db->updatePatentText(
                                    $patent['id'],
                                    $fetchResult['claims'],
                                    $fetchResult['abstract'],
                                    'ok'
                                );
                                $patent['claims_text'] = $fetchResult['claims'];
                                $patent['abstract_text'] = $fetchResult['abstract'];
                            } else {
                                $db->updatePatentText($patent['id'], '', '', 'failed', $fetchResult['error'] ?? 'Unknown');
                                $screened++;
                                $progress = "Processing $screened / $total patents";
                                continue;
                            }
                        }
                        
                        // Screen the patent
                        $patentText = ($patent['claims_text'] ?? '') . "\n" . ($patent['abstract_text'] ?? '');
                        $screenResult = AiProvider::screenPatent(json_encode($elemContexts), $patentText);
                        
                        if ($screenResult && isset($screenResult['overall'])) {
                            $db->addScreeningResult(
                                $patent['id'],
                                $screenResult['overall']['label'],
                                $screenResult['overall']['score'],
                                $screenResult['overall']['reasoning_short'],
                                json_encode($screenResult['per_element'])
                            );
                            $screened++;
                        }
                        
                        $progress = "Processing $screened / $total patents";
                        usleep(AI_SLEEP_MS * 1000);
                    }
                    
                    $patents = $db->getPatentsByAnalysis($analysisId);
                    $success = "Screening complete! Processed $screened patents.";
                    Logger::info("Completed screening of $screened patents for analysis $analysisId");
                }
            } catch (Exception $e) {
                $error = 'Screening failed: ' . $e->getMessage();
                Logger::error("Screening error: " . $e->getMessage());
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
    <title>Step 2: Patent Screening - Patent Analysis MVP</title>
    <link rel="stylesheet" href="assets/styles.css">
</head>
<body>
    <div class="container">
        <header>
            <h1>🔍 Patent Analysis MVP</h1>
            <div class="header-actions">
                <span>Analysis: <?php echo htmlspecialchars($analysis['name']); ?></span>
                <a href="logout.php" class="btn btn-logout">Logout</a>
            </div>
        </header>

        <main>
            <nav class="workflow-nav">
                <a href="step1.php?analysis_id=<?php echo $analysisId; ?>" class="nav-step">Step 1: Seed</a>
                <a href="step2.php?analysis_id=<?php echo $analysisId; ?>" class="nav-step active">Step 2: Screening</a>
                <a href="step3.php?analysis_id=<?php echo $analysisId; ?>" class="nav-step">Step 3: Report</a>
            </nav>

            <section class="step-section">
                <h2>STEP 2: Bulk Patent Upload + Relevance Screening</h2>

                <?php if ($error): ?>
                    <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                <?php endif; ?>
                <?php if ($progress): ?>
                    <div class="alert alert-info"><?php echo htmlspecialchars($progress); ?></div>
                <?php endif; ?>

                <!-- Patent Upload -->
                <div class="card">
                    <h3>Add Patents for Screening</h3>
                    <form method="POST">
                        <input type="hidden" name="action" value="add_patents">
                        
                        <div class="form-group">
                            <label for="patent_input">Patent Numbers:</label>
                            <textarea id="patent_input" name="patent_input" rows="5" 
                                      placeholder="Paste patent numbers (one per line)&#10;e.g.,&#10;US10123456B2&#10;US10123457B2"></textarea>
                        </div>

                        <div class="form-group">
                            <label for="text_scope">Text Scope:</label>
                            <select id="text_scope" name="text_scope">
                                <option value="claims+abstract">Claims + Abstract</option>
                                <option value="claims">Claims Only</option>
                            </select>
                        </div>

                        <button type="submit" class="btn btn-primary">Add Patents</button>
                    </form>
                </div>

                <!-- Patents List & Screening -->
                <?php if (!empty($patents)): ?>
                    <div class="card">
                        <h3>Patent List (<?php echo count($patents); ?> patents)</h3>

                        <form method="POST" style="margin-bottom: 20px;">
                            <input type="hidden" name="action" value="start_screening">
                            <input type="hidden" name="text_scope" value="claims+abstract">
                            <button type="submit" class="btn btn-primary">Start Screening</button>
                        </form>

                        <table class="results-table">
                            <thead>
                                <tr>
                                    <th>Patent Number</th>
                                    <th>Status</th>
                                    <th>Label</th>
                                    <th>Score</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($patents as $patent):
                                    $result = $db->getScreeningResult($patent['id']);
                                    $status = $result ? 'Screened' : 'Pending';
                                    $statusClass = $result ? 'status-ok' : 'status-pending';
                                ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($patent['patent_number']); ?></strong></td>
                                        <td><span class="status <?php echo $statusClass; ?>"><?php echo $status; ?></span></td>
                                        <td><?php echo $result ? htmlspecialchars($result['overall_label']) : '—'; ?></td>
                                        <td><?php echo $result ? htmlspecialchars($result['overall_score']) : '—'; ?></td>
                                        <td>
                                            <?php if ($result): ?>
                                                <a href="patent_detail.php?patent_id=<?php echo $patent['id']; ?>" class="link">View Details</a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>

                <!-- Navigation -->
                <div class="step-nav">
                    <a href="step1.php?analysis_id=<?php echo $analysisId; ?>" class="btn btn-secondary">← Step 1</a>
                    <a href="step3.php?analysis_id=<?php echo $analysisId; ?>" class="btn btn-primary">Step 3 →</a>
                </div>
            </section>
        </main>
    </div>

    <footer>
        <p>&copy; 2026 Patent Analysis MVP</p>
    </footer>
</body>
</html>
