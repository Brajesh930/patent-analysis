<?php
/**
 * Patent Analysis MVP - Database Access Layer
 */

class Database {
    private static $instance = null;
    private $pdo;
    
    private function __construct() {
        try {
            $this->pdo = new PDO('sqlite:' . DB_PATH);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            Logger::error("Database connection failed: " . $e->getMessage());
            die("Database connection error. Please run: php scripts/init_db.php");
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function pdo() {
        return $this->pdo;
    }
    
    // ========================================================================
    // ANALYSES
    // ========================================================================
    
    public function createAnalysis($name, $seedType, $seedValue) {
        $stmt = $this->pdo->prepare("
            INSERT INTO analyses (name, seed_type, seed_value, created_at, updated_at)
            VALUES (?, ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
        ");
        $stmt->execute([$name, $seedType, $seedValue]);
        return $this->pdo->lastInsertId();
    }
    
    public function getAnalysis($id) {
        $stmt = $this->pdo->prepare("SELECT * FROM analyses WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    public function getAllAnalyses() {
        $stmt = $this->pdo->query("SELECT * FROM analyses ORDER BY created_at DESC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function updateAnalysisSeedText($id, $extractedText) {
        $stmt = $this->pdo->prepare("
            UPDATE analyses SET seed_extracted_text = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?
        ");
        return $stmt->execute([$extractedText, $id]);
    }

    /**
     * Replace the stored seed_value for an analysis (e.g. after normalization)
     */
    public function updateAnalysisSeedValue($id, $newValue) {
        $stmt = $this->pdo->prepare(" 
            UPDATE analyses SET seed_value = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?
        ");
        return $stmt->execute([$newValue, $id]);
    }

    public function deleteAnalysis($id) {
        $stmt = $this->pdo->prepare("DELETE FROM analyses WHERE id = ?");
        return $stmt->execute([$id]);
    }

    /**
     * Insert a new key element for an analysis
     */
    public function addKeyElement($analysisId, $order, $elementText) {
        $stmt = $this->pdo->prepare("
            INSERT INTO key_elements (analysis_id, element_order, element_text, created_at)
            VALUES (?, ?, ?, CURRENT_TIMESTAMP)
        ");
        return $stmt->execute([$analysisId, $order, $elementText]);
    }
    
    public function getKeyElements($analysisId) {
        $stmt = $this->pdo->prepare("
            SELECT * FROM key_elements WHERE analysis_id = ? ORDER BY element_order
        ");
        $stmt->execute([$analysisId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function updateKeyElementContext($elementId, $contextJson) {
        $stmt = $this->pdo->prepare("
            UPDATE key_elements SET context_json = ?, context_error = NULL WHERE id = ?
        ");
        return $stmt->execute([$contextJson, $elementId]);
    }

    public function updateKeyElementError($elementId, $errorMessage) {
        $stmt = $this->pdo->prepare("
            UPDATE key_elements SET context_error = ?, context_json = NULL WHERE id = ?
        ");
        return $stmt->execute([$errorMessage, $elementId]);
    }

    public function clearKeyElementContext($elementId) {
        $stmt = $this->pdo->prepare("
            UPDATE key_elements SET context_json = NULL, context_error = NULL WHERE id = ?
        ");
        return $stmt->execute([$elementId]);
    }
    
    public function updateKeyElementApproval($elementId, $approved, $userNotes = null) {
        $stmt = $this->pdo->prepare("
            UPDATE key_elements SET approved = ?, user_notes = ? WHERE id = ?
        ");
        return $stmt->execute([$approved, $userNotes, $elementId]);
    }

    public function deleteKeyElement($elementId) {
        $stmt = $this->pdo->prepare("DELETE FROM key_elements WHERE id = ?");
        return $stmt->execute([$elementId]);
    }
    
    // ========================================================================
    // PATENTS
    // ========================================================================
    
    public function addPatent($analysisId, $patentNumber) {
        $stmt = $this->pdo->prepare("
            INSERT INTO patents (analysis_id, patent_number, created_at)
            VALUES (?, ?, CURRENT_TIMESTAMP)
        ");
        $stmt->execute([$analysisId, $patentNumber]);
        return $this->pdo->lastInsertId();
    }
    
    public function getPatent($id) {
        $stmt = $this->pdo->prepare("SELECT * FROM patents WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    public function getPatentsByAnalysis($analysisId) {
        $stmt = $this->pdo->prepare("
            SELECT * FROM patents WHERE analysis_id = ? ORDER BY patent_number
        ");
        $stmt->execute([$analysisId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function updatePatentText($patentId, $claims, $abstract, $status, $error = null) {
        $stmt = $this->pdo->prepare("
            UPDATE patents SET claims_text = ?, abstract_text = ?, fetch_status = ?, fetch_error = ?
            WHERE id = ?
        ");
        return $stmt->execute([$claims, $abstract, $status, $error, $patentId]);
    }
    
    // ========================================================================
    // SCREENING RESULTS
    // ========================================================================
    
    public function addScreeningResult($patentId, $overallLabel, $overallScore, $reasoningShort, $perElementJson) {
        $stmt = $this->pdo->prepare("
            INSERT INTO screening_results 
            (patent_id, overall_label, overall_score, reasoning_short, per_element_json, created_at)
            VALUES (?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
        ");
        return $stmt->execute([$patentId, $overallLabel, $overallScore, $reasoningShort, $perElementJson]);
    }
    
    public function getScreeningResult($patentId) {
        $stmt = $this->pdo->prepare("
            SELECT * FROM screening_results WHERE patent_id = ? ORDER BY created_at DESC LIMIT 1
        ");
        $stmt->execute([$patentId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    public function getAnalysisResults($analysisId) {
        $stmt = $this->pdo->prepare("
            SELECT p.id, p.patent_number, sr.overall_label, sr.overall_score, sr.reasoning_short, sr.per_element_json
            FROM patents p
            LEFT JOIN screening_results sr ON p.id = sr.patent_id
            WHERE p.analysis_id = ?
            ORDER BY sr.overall_score DESC NULLS LAST
        ");
        $stmt->execute([$analysisId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // ========================================================================
    // EXPORTS
    // ========================================================================
    
    public function addExport($analysisId, $exportType, $filePath) {
        $stmt = $this->pdo->prepare("
            INSERT INTO exports (analysis_id, export_type, file_path, created_at)
            VALUES (?, ?, ?, CURRENT_TIMESTAMP)
        ");
        return $stmt->execute([$analysisId, $exportType, $filePath]);
    }
}
