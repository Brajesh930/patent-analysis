
<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../lib/Database.php';
require_once __DIR__ . '/../lib/Logger.php';
require_once __DIR__ . '/../lib/PatentProvider.php';
require_once __DIR__ . '/../lib/AiApiService.php';
require_once __DIR__ . '/../lib/PatentDataService.php';

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

// Handle patent actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    // Add single patent with full data fetch
    if ($action === 'add_single_patent') {
        $patentNumber = trim($_POST['patent_number'] ?? '');
        $scope = $_POST['text_scope'] ?? 'full';
        
        if (empty($patentNumber)) {
            $error = 'Please enter a patent number.';
        } else {
            $runScreening = isset($_POST['run_screening']);
            $result = PatentDataService::addPatentForScreening($analysisId, $patentNumber, $runScreening, $scope);
            
            if ($result['success']) {
                $patents = $db->getPatentsByAnalysis($analysisId);
                $msg = 'Patent ' . htmlspecialchars($patentNumber) . ' added successfully.';
                if (isset($result['screening'])) {
                    $msg .= ' Screening completed.';
                }
                $success = $msg;
            } else {
                $error = 'Failed to add patent: ' . ($result['error'] ?? 'Unknown error');
            }
        }
    }
    
    // Bulk add patents
    if ($action === 'add_patents') {
        $patentInput = $_POST['patent_input'] ?? '';
        $scope = $_POST['text_scope'] ?? 'full';
        
        $patentNumbers = PatentProvider::parsePatentList($patentInput);
        
        if (empty($patentNumbers)) {
            $error = 'No patent numbers found.';
        } else if (count($patentNumbers) > MAX_PATENTS_MVP) {
            $error = 'Too many patents (max ' . MAX_PATENTS_MVP . ').';
        } else {
            $added = 0;
            foreach ($patentNumbers as $num) {
                $existing = array_filter($patents, fn($p) => $p['patent_number'] === $num);
                if (empty($existing)) {
                    $result = PatentDataService::addPatentForScreening($analysisId, $num, false, $scope);
                    if ($result['success']) {
                        $added++;
                    }
                }
            }
            $patents = $db->getPatentsByAnalysis($analysisId);
            $success = "Added $added patents with full data (title, abstract, claims, description, bibliographic info).";
        }
    }
    
    // Remove patent from screening
    if ($action === 'remove_patent') {
        $patentId = $_POST['patent_id'] ?? 0;
        if ($patentId) {
            $result = PatentDataService::removePatentFromScreening($patentId);
            if ($result) {
                $patents = $db->getPatentsByAnalysis($analysisId);
                $success = 'Patent removed from screening.';
            } else {
                $error = 'Failed to remove patent.';
            }
        }
    }
    
    // Re-screen single patent
    if ($action === 'rescreen_patent') {
        $patentId = $_POST['patent_id'] ?? 0;
        if ($patentId) {
            $result = PatentDataService::rescreenPatent($patentId);
            if ($result['success']) {
                $patents = $db->getPatentsByAnalysis($analysisId);
                $success = 'Patent re-screened successfully.';
            } else {
                $error = 'Failed to re-screen: ' . ($result['error'] ?? 'Unknown error');
            }
        }
    }
    
    // Start bulk screening
    if ($action === 'start_screening') {
        $scope = $_POST['text_scope'] ?? 'full';
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
                    $error = 'No approved key elements with context. Please complete Step 1 and generate context.';
                } else {
                    $screened = 0;
                    $total = count($unscreenedPatents);
                    
                    foreach ($unscreenedPatents as $idx => $patent) {
                        if (!$patent['claims_text']) {
                            $fetchResult = PatentProvider::fetchCompletePatentData($patent['patent_number'], $scope);
                            if ($fetchResult && isset($fetchResult['status']) && $fetchResult['status'] === 'ok') {
                                $db->updatePatentText(
                                    $patent['id'],
                                    $fetchResult['claims'] ?? '',
                                    $fetchResult['abstract'] ?? '',
                                    'ok'
                                );
                                $patent['claims_text'] = $fetchResult['claims'] ?? '';
                                $patent['abstract_text'] = $fetchResult['abstract'] ?? '';
                            } else {
                                $errorMsg = $fetchResult['error'] ?? 'Unknown error';
                                $db->updatePatentText($patent['id'], '', '', 'failed', $errorMsg);
                                $screened++;
                                $progress = "Processing $screened / $total patents";
                                continue;
                            }
                        }
                        
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
    <style>
        .patent-form-row { display: flex; gap: 10px; align-items: flex-end; margin-bottom: 15px; }
        .patent-form-row .form-group { flex: 1; margin-bottom: 0; }
        .patent-form-row .btn { margin-bottom: 0; }
        .checkbox-inline { display: flex; align-items: center; gap: 5px; }
        .checkbox-inline input { width: auto; }
        .action-buttons { display: flex; gap: 5px; flex-wrap: wrap; }
        .action-buttons form { display: inline; }
        .action-buttons .btn { padding: 4px 8px; font-size: 11px; }
        .btn-danger { background: #dc3545; color: white; }
        .btn-warning { background: #ffc107; color: #333; }
        .btn-success { background: #28a745; color: white; }
        
        /* Modal Styles */
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); }
        .modal-content { background-color: #fefefe; margin: 5% auto; padding: 20px; border: 1px solid #888; width: 80%; max-width: 900px; max-height: 85vh; overflow-y: auto; border-radius: 8px; position: relative; }
        .modal-close { float: right; font-size: 28px; font-weight: bold; cursor: pointer; color: #aaa; }
        .modal-close:hover { color: #000; }
        .modal-loading { text-align: center; padding: 40px; color: #666; }
        .modal-error { color: red; padding: 20px; text-align: center; }
        .patent-data-section { background: #f8f9fa; padding: 15px; border-radius: 4px; margin-bottom: 15px; }
        .patent-data-section h3 { margin: 0 0 10px 0; color: #0066cc; font-size: 16px; }
        .patent-data-section p { margin: 5px 0; font-size: 14px; }
        .patent-claims { background: #fff; padding: 15px; border: 1px solid #ddd; border-radius: 4px; max-height: 300px; overflow-y: auto; }
        .patent-claims pre { white-space: pre-wrap; font-family: inherit; font-size: 13px; line-height: 1.5; margin: 0; }
    </style>
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

                <!-- Single Patent Add with Options -->
                <div class="card">
                    <h3>Add Single Patent</h3>
                    <form method="POST">
                        <input type="hidden" name="action" value="add_single_patent">
                        <div class="patent-form-row">
                            <div class="form-group">
                                <label for="patent_number">Patent Number:</label>
                                <input type="text" id="patent_number" name="patent_number" placeholder="e.g., US10123456B2" style="width:100%;">
                            </div>
                            <div class="form-group">
                                <label for="text_scope_single">Data Scope:</label>
                                <select id="text_scope_single" name="text_scope" style="width:100%;">
                                    <option value="full">Full (Title + Abstract + Claims + Description)</option>
                                    <option value="claims+abstract">Claims + Abstract</option>
                                    <option value="claims">Claims Only</option>
                                </select>
                            </div>
                            <div class="form-group checkbox-inline">
                                <input type="checkbox" id="run_screening" name="run_screening">
                                <label for="run_screening">Screen immediately</label>
                            </div>
                            <button type="submit" class="btn btn-primary">Add & Screen</button>
                        </div>
                    </form>
                </div>

                <!-- Bulk Patent Upload -->
                <div class="card">
                    <h3>Bulk Add Patents</h3>
                    <form method="POST">
                        <input type="hidden" name="action" value="add_patents">
                        
                        <div class="form-group">
                            <label for="patent_input">Patent Numbers (one per line):</label>
                            <textarea id="patent_input" name="patent_input" rows="5" 
                                      placeholder="US10123456B2&#10;US10123457B2"></textarea>
                        </div>

                        <div class="form-group">
                            <label for="text_scope">Data Scope:</label>
                            <select id="text_scope" name="text_scope">
                                <option value="full">Full (Title + Abstract + Claims + Description)</option>
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
                            <input type="hidden" name="text_scope" value="full">
                            <button type="submit" class="btn btn-primary">Start Screening All</button>
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
                                        <td class="action-buttons">
                                            <button type="button" class="btn btn-success" onclick="viewPatentData(<?php echo $patent['id']; ?>, '<?php echo htmlspecialchars($patent['patent_number']); ?>')">View Data</button>
                                            <?php if ($result): ?>
                                                <a href="patent_detail.php?patent_id=<?php echo $patent['id']; ?>" class="btn" style="background:#0066cc; color:white;">Details</a>
                                                <form method="POST" onsubmit="return confirm('Re-screen this patent?');">
                                                    <input type="hidden" name="action" value="rescreen_patent">
                                                    <input type="hidden" name="patent_id" value="<?php echo $patent['id']; ?>">
                                                    <button type="submit" class="btn btn-warning">Re-screen</button>
                                                </form>
                                            <?php endif; ?>
                                            <form method="POST" onsubmit="return confirm('Remove this patent from screening?');">
                                                <input type="hidden" name="action" value="remove_patent">
                                                <input type="hidden" name="patent_id" value="<?php echo $patent['id']; ?>">
                                                <button type="submit" class="btn btn-danger">Remove</button>
                                            </form>
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

    <!-- Patent Data Modal Overlay -->
    <div id="patentModal" class="modal">
        <div class="modal-content">
            <span class="modal-close" onclick="closePatentModal()">&times;</span>
            <h2 id="modalPatentTitle" style="color:#0066cc; margin-bottom:15px;">Patent Data</h2>
            <div id="modalLoading" class="modal-loading">Loading patent data...</div>
            <div id="modalContent" style="display:none;">
                <div class="patent-data-section">
                    <h3>Bibliographic Information</h3>
                    <p><strong>Patent Number:</strong> <span id="modalPatentNumber"></span></p>
                    <p><strong>Title:</strong> <span id="modalTitle"></span></p>
                    <p><strong>Abstract:</strong> <span id="modalAbstract"></span></p>
                </div>
                <div class="patent-data-section">
                    <h3>Claims</h3>
                    <div class="patent-claims">
                        <pre id="modalClaims"></pre>
                    </div>
                </div>
            </div>
            <div id="modalError" class="modal-error" style="display:none;"></div>
        </div>
    </div>

    <footer>
        <p>&copy; 2026 Patent Analysis MVP</p>
    </footer>

    <script>
    function viewPatentData(patentId, patentNumber) {
        var modal = document.getElementById('patentModal');
        var loading = document.getElementById('modalLoading');
        var content = document.getElementById('modalContent');
        var error = document.getElementById('modalError');
        
        document.getElementById('modalPatentTitle').textContent = 'Patent Data: ' + patentNumber;
        document.getElementById('modalPatentNumber').textContent = patentNumber;
        
        modal.style.display = 'block';
        loading.style.display = 'block';
        content.style.display = 'none';
        error.style.display = 'none';
        
        // Fetch patent data via AJAX
        fetch('api.php?op=get_patent_data&patent_id=' + patentId)
            .then(response => response.json())
            .then(data => {
                loading.style.display = 'none';
                if (data.success) {
                    document.getElementById('modalTitle').textContent = data.title || 'N/A';
                    document.getElementById('modalAbstract').textContent = data.abstract || 'N/A';
                    document.getElementById('modalClaims').textContent = data.claims || 'No claims available';
                    content.style.display = 'block';
                } else {
                    error.textContent = 'Error: ' + (data.error || 'Failed to load patent data');
                    error.style.display = 'block';
                }
            })
            .catch(err => {
                loading.style.display = 'none';
                error.textContent = 'Error loading patent data: ' + err.message;
                error.style.display = 'block';
            });
    }
    
    function closePatentModal() {
        document.getElementById('patentModal').style.display = 'none';
    }
    
    // Close modal when clicking outside
    window.onclick = function(event) {
        var modal = document.getElementById('patentModal');
        if (event.target == modal) {
            modal.style.display = 'none';
        }
    }
    </script>
</body>
</html>

