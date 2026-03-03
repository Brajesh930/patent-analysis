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
$error = '';
$success = '';

// Handle adding key elements
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    if ($action === 'add_elements') {
        $elementsText = $_POST['elements_text'] ?? '';
        $lines = array_filter(array_map('trim', explode("\n", $elementsText)));
        
        if (empty($lines)) {
            $error = 'Please enter at least one key element.';
        } else {
            try {
                $order = 1;
                foreach ($lines as $element) {
                    $db->addKeyElement($analysisId, $order++, $element);
                }
                $keyElements = $db->getKeyElements($analysisId);
                $success = 'Added ' . count($lines) . ' key elements.';
                Logger::info("Added " . count($lines) . " key elements to analysis $analysisId");
            } catch (Exception $e) {
                $error = 'Failed to add elements: ' . $e->getMessage();
            }
        }
    }
    
    // Handle fetching seed text
    if ($action === 'fetch_seed') {
        if ($analysis['seed_type'] === 'patent_number') {
            $result = PatentProvider::fetchPatent($analysis['seed_value'], 'claims+abstract');
            if ($result['status'] === 'ok') {
                $extractedText = "CLAIMS:\n" . $result['claims'] . "\n\nABSTRACT:\n" . $result['abstract'];
                $db->updateAnalysisSeedText($analysisId, $extractedText);
                $analysis['seed_extracted_text'] = $extractedText;
                $success = 'Seed patent text fetched successfully.';
                Logger::info("Fetched seed patent text for analysis $analysisId");
            } else {
                $error = 'Failed to fetch patent: ' . ($result['error'] ?? 'Unknown error');
            }
        } else {
            $db->updateAnalysisSeedText($analysisId, $analysis['seed_value']);
            $analysis['seed_extracted_text'] = $analysis['seed_value'];
            $success = 'Seed text saved.';
        }
    }
    
    // Handle generating context for elements
    if ($action === 'generate_context') {
        $elementsToProcess = [];
        foreach ($keyElements as $elem) {
            if (empty($elem['context_json'])) {
                $elementsToProcess[] = $elem;
            }
        }
        
        if (empty($elementsToProcess)) {
            $error = 'No elements need context generation.';
        } else if (empty($analysis['seed_extracted_text'])) {
            $error = 'Please fetch seed text first.';
        } else {
            try {
                $processed = 0;
                foreach ($elementsToProcess as $elem) {
                    $context = AiProvider::buildElementContext(
                        $analysis['seed_extracted_text'],
                        $elem['element_text']
                    );
                    
                    if ($context) {
                        $contextJson = json_encode($context);
                        if (!json_last_error()) {
                            $db->updateKeyElementContext($elem['id'], $contextJson);
                            $processed++;
                        }
                    }
                    usleep(AI_SLEEP_MS * 1000);
                }
                $keyElements = $db->getKeyElements($analysisId);
                $success = "Generated context for $processed elements.";
                Logger::info("Generated context for $processed elements in analysis $analysisId");
            } catch (Exception $e) {
                $error = 'Failed to generate context: ' . $e->getMessage();
            }
        }
    }
}

// Handle element approval/edit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_element_id'])) {
    $elementId = $_POST['update_element_id'];
    $approved = isset($_POST['approve_' . $elementId]) ? 1 : 0;
    $userNotes = $_POST['notes_' . $elementId] ?? '';
    
    try {
        $db->updateKeyElementApproval($elementId, $approved, $userNotes);
        $keyElements = $db->getKeyElements($analysisId);
        $success = 'Element updated.';
    } catch (Exception $e) {
        $error = 'Failed to update element: ' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Step 1: Seed + Elements - Patent Analysis MVP</title>
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
                <a href="step1.php?analysis_id=<?php echo $analysisId; ?>" class="nav-step active">Step 1: Seed</a>
                <a href="step2.php?analysis_id=<?php echo $analysisId; ?>" class="nav-step">Step 2: Screening</a>
                <a href="step3.php?analysis_id=<?php echo $analysisId; ?>" class="nav-step">Step 3: Report</a>
            </nav>

            <section class="step-section">
                <h2>STEP 1: Seed + Key Elements + Context</h2>

                <?php if ($error): ?>
                    <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                <?php endif; ?>

                <!-- Seed Section -->
                <div class="card">
                    <h3>Seed Information</h3>
                    <p><strong>Type:</strong> <?php echo htmlspecialchars($analysis['seed_type']); ?></p>
                    <p><strong>Value:</strong></p>
                    <pre class="code-block"><?php echo htmlspecialchars(substr($analysis['seed_value'], 0, 300)); ?><?php if (strlen($analysis['seed_value']) > 300) echo '...'; ?></pre>
                    
                    <?php if (!$analysis['seed_extracted_text']): ?>
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="action" value="fetch_seed">
                            <button type="submit" class="btn btn-primary">Fetch Seed Text</button>
                        </form>
                    <?php else: ?>
                        <p class="success-text">✓ Seed text extracted</p>
                        <details>
                            <summary>Show extracted text</summary>
                            <pre class="code-block"><?php echo htmlspecialchars(substr($analysis['seed_extracted_text'], 0, 500)); ?><?php if (strlen($analysis['seed_extracted_text']) > 500) echo '...'; ?></pre>
                        </details>
                    <?php endif; ?>
                </div>

                <!-- Key Elements Input -->
                <div class="card">
                    <h3>Add Key Elements</h3>
                    <form method="POST">
                        <input type="hidden" name="action" value="add_elements">
                        <div class="form-group">
                            <label for="elements_text">Key Elements (one per line):</label>
                            <textarea id="elements_text" name="elements_text" rows="5" 
                                      placeholder="e.g., machine learning&#10;data processing&#10;automated analysis"></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary">Add Elements</button>
                    </form>
                </div>

                <!-- Key Elements List & Context -->
                <?php if (!empty($keyElements)): ?>
                    <div class="card">
                        <h3>Key Elements & Context</h3>
                        
                        <?php if ($analysis['seed_extracted_text']): ?>
                            <form method="POST" style="margin-bottom: 20px;">
                                <input type="hidden" name="action" value="generate_context">
                                <button type="submit" class="btn btn-primary">Generate Context for All</button>
                            </form>
                        <?php else: ?>
                            <p class="info-text">Please fetch seed text first before generating context.</p>
                        <?php endif; ?>

                        <div class="elements-list">
                            <?php foreach ($keyElements as $elem): ?>
                                <div class="element-card">
                                    <div class="element-header">
                                        <h4><?php echo htmlspecialchars($elem['element_text']); ?></h4>
                                        <?php if ($elem['context_json']): ?>
                                            <span class="badge badge-success">Context Ready</span>
                                        <?php else: ?>
                                            <span class="badge badge-warning">Pending</span>
                                        <?php endif; ?>
                                    </div>

                                    <?php if ($elem['context_json']): 
                                        $ctx = json_decode($elem['context_json'], true);
                                    ?>
                                        <div class="context-display">
                                            <p><strong>Definition:</strong> <?php echo htmlspecialchars($ctx['definition'] ?? ''); ?></p>
                                            <p><strong>Synonyms:</strong> <?php echo htmlspecialchars(implode(', ', $ctx['synonyms'] ?? [])); ?></p>
                                            <p><strong>Explicit markers:</strong> <?php echo htmlspecialchars(implode(', ', $ctx['explicit_markers'] ?? [])); ?></p>
                                        </div>
                                    <?php endif; ?>

                                    <form method="POST" class="element-form">
                                        <input type="hidden" name="update_element_id" value="<?php echo $elem['id']; ?>">
                                        <div class="form-group">
                                            <label>
                                                <input type="checkbox" name="approve_<?php echo $elem['id']; ?>" 
                                                       <?php echo $elem['approved'] ? 'checked' : ''; ?>>
                                                Approve this element
                                            </label>
                                        </div>
                                        <div class="form-group">
                                            <label for="notes_<?php echo $elem['id']; ?>">Notes:</label>
                                            <textarea id="notes_<?php echo $elem['id']; ?>" name="notes_<?php echo $elem['id']; ?>" rows="2"></textarea>
                                        </div>
                                        <button type="submit" class="btn btn-small">Save</button>
                                    </form>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Navigation -->
                <div class="step-nav">
                    <a href="index.php" class="btn btn-secondary">← Back to Analyses</a>
                    <a href="step2.php?analysis_id=<?php echo $analysisId; ?>" class="btn btn-primary">Next: Step 2 →</a>
                </div>
            </section>
        </main>
    </div>

    <footer>
        <p>&copy; 2026 Patent Analysis MVP</p>
    </footer>
</body>
</html>
