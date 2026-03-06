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

// Handle analysis deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_analysis_id'])) {
    $analysisId = $_POST['delete_analysis_id'];
    
    try {
        $db->deleteAnalysis($analysisId);
        Logger::info("Deleted analysis $analysisId");
    } catch (Exception $e) {
        Logger::error("Failed to delete analysis: " . $e->getMessage());
    }
}

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
                <a href="diagnostics.php" class="btn" style="background:#6c757d; color:white; padding:8px 12px; font-size:12px; margin-right:5px;">🔧 Diagnostics</a>
                <a href="ai_config.php" class="btn" style="background:#6c757d; color:white; padding:8px 12px; font-size:12px; margin-right:10px;">⚙️ AI Config</a>
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
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="delete_analysis_id" value="<?php echo $analysis['id']; ?>">
                                            <button type="submit" class="link" style="color:#d32f2f; cursor:pointer; border:none; background:none; padding:0; text-decoration:underline;" onclick="return confirm('Delete this analysis and all its data?')">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </section>

            <!-- Patent Info Viewer Section -->
            <section class="patent-viewer-section" style="margin-top:40px;">
                <div class="section-header">
                    <h2>Patent Info Viewer</h2>
                </div>
                <div class="form" style="background:#fff;">
                    <div class="form-group">
                        <label for="patentNumbers">Enter patent numbers (comma or newline separated):</label>
                        <textarea id="patentNumbers" placeholder="e.g. WO-2001003468-A2, US10741160B1" style="height:100px;"></textarea>
                    </div>
                    <div class="form-group">
                        <label>Exclude patents from saved lists:</label>
                        <div id="excludeListsContainer" style="max-height: 150px; overflow-y: auto; border: 1px solid #ccc; padding: 0.5em; border-radius: 6px;"></div>
                    </div>
                    <button id="fetchBtn" class="btn btn-primary">Fetch Patent Info</button>
                    <div id="status" style="margin-top:10px; font-style:italic;"></div>
                    <div id="results" class="patent-info" style="margin-top:20px;"></div>
                </div>
            </section>
        </main>
    </div>

    <footer>
        <p>&copy; 2026 Patent Analysis MVP | <a href="https://example.com">Documentation</a></p>
    </footer>

    <!-- Patent Viewer Scripts -->
    <script>
        // load saved lists into exclude container
        function loadSavedLists() {
            const savedLists = JSON.parse(localStorage.getItem('patentFamilyLists') || '{}');
            const container = document.getElementById('excludeListsContainer');
            if (!container) return;
            container.innerHTML = '';
            for (const listName in savedLists) {
                const label = document.createElement('label');
                label.style.display = 'block';
                label.style.marginBottom = '0.3em';
                const checkbox = document.createElement('input');
                checkbox.type = 'checkbox';
                checkbox.value = listName;
                checkbox.style.marginRight = '0.5em';
                label.appendChild(checkbox);
                label.appendChild(document.createTextNode(listName));
                container.appendChild(label);
            }
        }

        async function transformPatentNumbers(patentNumbers) {
            const response = await fetch('api.php?op=transform', {
                method: 'POST',
                headers: {'Content-Type': 'application/json', 'Accept': 'application/json'},
                body: JSON.stringify({ publications: patentNumbers })
            });
            if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
            const data = await response.json();
            if (!Array.isArray(data)) throw new Error('Invalid response from transform API');
            return data;
        }

        async function fetchPatentData(patentNo) {
            const response = await fetch(`api.php?op=patent&number=${encodeURIComponent(patentNo)}`);
            if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
            return response.json();
        }

        function displayPatentInfo(patentNo, data, displayedFamilyIds, excludedFamilyIds) {
            const resultsDiv = document.getElementById('results');
            const itemDiv = document.createElement('div');
            itemDiv.className = 'patent-item';
            if (data.error) {
                itemDiv.innerHTML = `<div class="error">Error fetching data for patent ${patentNo}: ${data.error}</div>`;
            } else if (!data._source) {
                itemDiv.innerHTML = `<div class="error">No data found for patent ${patentNo}</div>`;
            } else {
                const source = data._source;
                const familyId = source.family_id || null;
                if (familyId && (displayedFamilyIds.has(familyId) || excludedFamilyIds.has(familyId))) {
                    return false;
                }
                if (familyId) displayedFamilyIds.add(familyId);
                const title = source.title || 'No title available';
                const abstract = source.abstract || 'No abstract available';
                const pubNumber = source.publication_number || 'N/A';
                const priorityDate = source.priority_date ? new Date(source.priority_date).toLocaleDateString() : 'N/A';
                const pubDate = source.publication_date ? new Date(source.publication_date).toLocaleDateString() : 'N/A';

                itemDiv.innerHTML = `
                    <div class="patent-title">${title}</div>
                    <div class="patent-meta">
                        <span><strong>Patent No:</strong> ${pubNumber}</span>
                        <span><strong>Priority Date:</strong> ${priorityDate}</span>
                        <span><strong>Publication Date:</strong> ${pubDate}</span>
                    </div>
                    <div class="patent-abstract">${abstract}</div>
                `;
                resultsDiv.appendChild(itemDiv);
                return true;
            }
            resultsDiv.appendChild(itemDiv);
            return true;
        }

        document.getElementById('fetchBtn').addEventListener('click', async () => {
            const rawInput = document.getElementById('patentNumbers').value.trim();
            const statusDiv = document.getElementById('status');
            const resultsDiv = document.getElementById('results');
            resultsDiv.innerHTML = '';
            statusDiv.textContent = '';

            if (!rawInput) {
                alert('Please enter at least one patent number.');
                return;
            }
            const patents = rawInput.split(/[
,]+/).map(p => p.trim()).filter(p => p.length > 0);
            if (patents.length === 0) {
                alert('Please enter valid patent numbers.');
                return;
            }

            const savedLists = JSON.parse(localStorage.getItem('patentFamilyLists') || '{}');
            const checkboxes = document.querySelectorAll('#excludeListsContainer input[type="checkbox"]:checked');
            const excludedFamilyIds = new Set();
            checkboxes.forEach(cb => {
                const listName = cb.value;
                const ids = savedLists[listName];
                if (Array.isArray(ids)) ids.forEach(i => excludedFamilyIds.add(i));
            });

            statusDiv.textContent = 'Transforming patent numbers...';
            let transformed;
            try {
                transformed = await transformPatentNumbers(patents);
            } catch (e) {
                statusDiv.textContent = '';
                alert(`Error transforming patent numbers: ${e.message}`);
                return;
            }
            statusDiv.textContent = 'Fetching patent data...';
            const displayedFamilyIds = new Set();
            for (let i = 0; i < transformed.length; i++) {
                const pn = transformed[i];
                statusDiv.textContent = `Fetching data for patent ${pn} (${i + 1} of ${transformed.length})...`;
                try {
                    const data = await fetchPatentData(pn);
                    displayPatentInfo(pn, data, displayedFamilyIds, excludedFamilyIds);
                } catch (e) {
                    displayPatentInfo(pn, {error: e.message}, displayedFamilyIds, excludedFamilyIds);
                }
            }
            statusDiv.textContent = 'All patent data fetched.';
        });

        window.addEventListener('load', loadSavedLists);
    </script>
</body>
</html>
