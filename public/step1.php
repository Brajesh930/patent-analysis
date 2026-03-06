<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../lib/Database.php';
require_once __DIR__ . '/../lib/Logger.php';
require_once __DIR__ . '/../lib/PatentProvider.php';
require_once __DIR__ . '/../lib/AiApiService.php'; // Consolidated AI service

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
$seedInfo = null; // store last fetch result for display
$normalizedDisplay = '';

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
    if ($action === 'fetch_seed' || $action === 'regenerate_seed') {
        if ($analysis['seed_type'] === 'patent_number') {
            // normalize the input number via transform API
            $normalized = PatentProvider::transformPatentNumber($analysis['seed_value']);
            $patNum = $normalized ?: $analysis['seed_value'];
            // if normalized result is different, save it back so future operations use it
            if ($patNum !== $analysis['seed_value']) {
                $db->updateAnalysisSeedValue($analysisId, $patNum);
                $analysis['seed_value'] = $patNum;
                $normalizedDisplay = $patNum;
                Logger::info("Normalized seed value saved for analysis $analysisId: $patNum");
            } else {
                // still show what we used
                $normalizedDisplay = $patNum;
            }

            $result = PatentProvider::fetchPatent($patNum, 'claims+abstract');
            $seedInfo = $result;
            if ($result['status'] === 'ok') {
                $extractedText = "CLAIMS:\n" . ($result['claims'] ?? '') . "\n\nDESCRIPTION:\n" . ($result['description'] ?? '') . "\n\nABSTRACT:\n" . ($result['abstract'] ?? '');
                $db->updateAnalysisSeedText($analysisId, $extractedText);
                $analysis['seed_extracted_text'] = $extractedText;
                $success = ($action === 'regenerate_seed' ? 'Seed patent text regenerated successfully.' : 'Seed patent text fetched successfully.');
                Logger::info("Fetched seed patent text for analysis $analysisId using number $patNum");
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
                $failed = 0;
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
                    } else {
                        // No context returned - likely API error or limit exceeded
                        $errorMsg = 'API limit exceeded or service unavailable. Please try again later.';
                        $db->updateKeyElementError($elem['id'], $errorMsg);
                        $failed++;
                    }
                    usleep(AI_SLEEP_MS * 1000);
                }
                $keyElements = $db->getKeyElements($analysisId);
                $success = "Generated context for $processed elements";
                if ($failed > 0) {
                    $success .= " ($failed failed - API limits exceeded)";
                }
                $success .= ".";
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

// Handle element deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_element_id'])) {
    $elementId = $_POST['delete_element_id'];
    
    try {
        $db->deleteKeyElement($elementId);
        $keyElements = $db->getKeyElements($analysisId);
        $success = 'Element deleted.';
        Logger::info("Deleted key element $elementId from analysis $analysisId");
    } catch (Exception $e) {
        $error = 'Failed to delete element: ' . $e->getMessage();
    }
}

// Handle clearing context for regeneration
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'clear_context') {
    $elementId = $_POST['element_id'] ?? null;
    
    if ($elementId) {
        try {
            $db->clearKeyElementContext($elementId);
            $keyElements = $db->getKeyElements($analysisId);
            $success = 'Element context cleared. You can now regenerate it.';
            Logger::info("Cleared context for element $elementId in analysis $analysisId");
        } catch (Exception $e) {
            $error = 'Failed to clear context: ' . $e->getMessage();
        }
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
                <a href="diagnostics.php" class="btn" style="background:#6c757d; color:white; padding:8px 12px; font-size:12px; margin-right:5px;">🔧 Diagnostics</a>
                <a href="ai_config.php" class="btn" style="background:#6c757d; color:white; padding:8px 12px; font-size:12px; margin-right:10px;">⚙️ AI Config</a>
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
                        <?php if ($normalizedDisplay && $normalizedDisplay !== $analysis['seed_value']): ?>
                            <div class="info-text">Normalized as: <?php echo htmlspecialchars($normalizedDisplay); ?></div>
                        <?php endif; ?>
                        <p class="success-text">✓ Seed text extracted</p>
                        <form method="POST" style="display:inline; margin-left:10px;">
                            <input type="hidden" name="action" value="regenerate_seed">
                            <button type="submit" class="btn btn-small" style="background:#6c757d; color:white;">🔄 Regenerate</button>
                        </form>
                        <details>
                            <summary>Show extracted text</summary>
                            <pre class="code-block"><?php echo htmlspecialchars(substr($analysis['seed_extracted_text'], 0, 500)); ?><?php if (strlen($analysis['seed_extracted_text']) > 500) echo '...'; ?></pre>
                        </details>
                        <?php if ($seedInfo && isset($seedInfo['meta'])): ?>
                            <div class="seed-meta" style="margin-top:10px;">
                                <strong>Title:</strong> <?php echo htmlspecialchars($seedInfo['meta']['title'] ?? ''); ?><br>
                                <strong>Filing:</strong> <?php echo htmlspecialchars($seedInfo['meta']['filing_date'] ?? ''); ?><br>
                                <strong>Publication:</strong> <?php echo htmlspecialchars($seedInfo['meta']['issue_date'] ?? ''); ?><br>
                                <strong>Assignee:</strong> <?php echo htmlspecialchars($seedInfo['meta']['assignee'] ?? ''); ?><br>
                            </div>
                            
                            <?php if (!empty($seedInfo['description'])): ?>
                                <div style="margin-top:15px; padding:10px; background:#f5f5f5; border-radius:5px;">
                                    <strong>Description:</strong>
                                    <p style="margin-top:5px; color:#555;">
                                        <?php echo htmlspecialchars(substr($seedInfo['description'], 0, 300)); ?>
                                        <?php if (strlen($seedInfo['description']) > 300): ?>...<?php endif; ?>
                                    </p>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($seedInfo['claims'])): ?>
                                <div style="margin-top:15px; padding:10px; background:#f0f8ff; border-radius:5px;">
                                    <strong>Claims Summary:</strong>
                                    <p style="margin-top:5px; color:#555; font-size:0.9em;">
                                        <?php 
                                            $claimsArr = array_filter(array_map('trim', explode('\\n\\n', $seedInfo['claims'])));
                                            echo htmlspecialchars(implode(' | ', array_slice($claimsArr, 0, 2)));
                                            if (count($claimsArr) > 2) echo '...';
                                        ?>
                                    </p>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
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
                                            <span class="badge badge-success">✓ Context Ready</span>
                                        <?php elseif ($elem['context_error']): ?>
                                            <span class="badge" style="background:#dc3545; color:white;">⚠️ Error</span>
                                        <?php else: ?>
                                            <span class="badge badge-warning">⏳ Pending</span>
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
                                    <?php elseif ($elem['context_error']): ?>
                                        <div class="context-display" style="background:#ffe0e0; padding:12px; border-radius:4px; border-left:4px solid #dc3545;">
                                            <p><strong style="color:#dc3545;">⚠️ Error:</strong></p>
                                            <p style="color:#555; margin-top:8px;"><?php echo htmlspecialchars($elem['context_error']); ?></p>
                                            <small style="color:#999; margin-top:8px; display:block;">Try again later or regenerate this element's context.</small>
                                        </div>
                                    <?php endif; ?>

                                    <form method="POST" class="element-form">
                                        <input type="hidden" name="update_element_id" value="<?php echo $elem['id']; ?>">
                                        <?php if ($elem['context_error']): ?>
                                            <div style="margin-bottom:10px;">
                                                <form method="POST" style="display:inline;">
                                                    <input type="hidden" name="action" value="clear_context">
                                                    <input type="hidden" name="element_id" value="<?php echo $elem['id']; ?>">
                                                    <button type="submit" class="btn btn-small" style="background:#6c757d; color:white; padding:6px 12px; font-size:12px;">🔄 Try Again</button>
                                                </form>
                                            </div>
                                        <?php endif; ?>
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
                                    <form method="POST" class="element-form" style="display:inline; margin-top:10px;">
                                        <input type="hidden" name="delete_element_id" value="<?php echo $elem['id']; ?>">
                                        <button type="submit" class="btn btn-small btn-danger" onclick="return confirm('Delete this element?')">Delete</button>
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
