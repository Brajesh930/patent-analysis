<?php
/**
 * Patent Data Service - Single function to fetch complete patent data
 * 
 * This file provides a single function to fetch complete patent information
 * including title, abstract, claims, description, and bibliographic data.
 * 
 * Usage:
 *   $patentData = PatentDataService::getPatentData('US10123456B2');
 *   // Returns: ['title' => ..., 'abstract' => ..., 'claims' => ..., 'description' => ..., 'bibliographic' => [...]]
 */

require_once __DIR__ . '/Logger.php';
require_once __DIR__ . '/PatentProvider.php';

class PatentDataService {
    
    /**
     * Get complete patent data for a given patent number
     * 
     * @param string $patentNumber The patent number (e.g., "US10123456B2")
     * @param string $scope Scope of data: 'full', 'claims', 'claims+abstract'
     * @return array Patent data with keys: title, abstract, claims, description, bibliographic
     */
    public static function getPatentData($patentNumber, $scope = 'full') {
        // Fetch complete patent data using PatentProvider
        $result = PatentProvider::fetchCompletePatentData($patentNumber, $scope);
        
        if (!$result || !isset($result['status']) || $result['status'] !== 'ok') {
            return [
                'success' => false,
                'error' => $result['error'] ?? 'Failed to fetch patent data',
                'patent_number' => $patentNumber
            ];
        }
        
        // Format the response
        return [
            'success' => true,
            'patent_number' => $result['patent_number'],
            'title' => $result['title'] ?? '',
            'abstract' => $result['abstract'] ?? '',
            'claims' => $result['claims'] ?? '',
            'description' => $result['description'] ?? '',
            'bibliographic' => $result['bibliographic'] ?? []
        ];
    }
    
    /**
     * Screen a single patent for relevance against key elements
     * 
     * @param int $patentId The patent ID in the database
     * @param array $elemContexts Array of element contexts from key elements
     * @param string|null $patentText Optional patent text to use directly (avoids DB fetch)
     * @return array Screening result
     */
    public static function screenSinglePatent($patentId, $elemContexts, $patentText = null) {
        require_once __DIR__ . '/AiApiService.php';
        
        $db = Database::getInstance();
        
        // Use provided patent text or fetch from database
        if ($patentText === null) {
            $patent = $db->getPatent($patentId);
            if (!$patent) {
                return ['success' => false, 'error' => 'Patent not found'];
            }
            $patentText = ($patent['claims_text'] ?? '') . "\n" . ($patent['abstract_text'] ?? '');
        }
        
        // Check if we have valid patent text
        $trimmedText = trim($patentText);
        if (empty($trimmedText) || strlen($trimmedText) < 10) {
            return ['success' => false, 'error' => 'No patent text available for screening'];
        }
        
        // Screen the patent
        $screenResult = AiProvider::screenPatent(json_encode($elemContexts), $patentText);
        
        if ($screenResult && isset($screenResult['overall'])) {
            // Save the screening result
            $db->addScreeningResult(
                $patentId,
                $screenResult['overall']['label'],
                $screenResult['overall']['score'],
                $screenResult['overall']['reasoning_short'],
                json_encode($screenResult['per_element'])
            );
            
            return [
                'success' => true,
                'result' => $screenResult
            ];
        }
        
        return ['success' => false, 'error' => 'Screening failed'];
    }
    
    /**
     * Add a patent for screening and optionally run screening immediately
     * 
     * @param int $analysisId The analysis ID
     * @param string $patentNumber The patent number
     * @param bool $runScreening Whether to run screening immediately
     * @param string $scope Scope of patent data to fetch
     * @return array Result with patent ID and screening result if runScreening is true
     */
    public static function addPatentForScreening($analysisId, $patentNumber, $runScreening = false, $scope = 'full') {
        $db = Database::getInstance();
        
        // Check if patent already exists
        $patents = $db->getPatentsByAnalysis($analysisId);
        $existing = array_filter($patents, fn($p) => $p['patent_number'] === $patentNumber);
        
        $patentId = null;
        
        if (empty($existing)) {
            // Add the patent
            $patentId = $db->addPatent($analysisId, $patentNumber);
        } else {
            $patentId = reset($existing)['id'];
        }
        
        // Fetch complete patent data
        $patentData = self::getPatentData($patentNumber, $scope);
        
        if ($patentData['success']) {
            // Build patent text from claims and abstract
            $patentText = ($patentData['claims'] ?? '') . "\n" . ($patentData['abstract'] ?? '');
            
            // Update patent text in database
            $db->updatePatentText(
                $patentId,
                $patentData['claims'] ?? '',
                $patentData['abstract'] ?? '',
                'ok'
            );
            
            $result = [
                'success' => true,
                'patent_id' => $patentId,
                'patent_data' => $patentData
            ];
            
    // Run screening if requested - pass patent text directly
        if ($runScreening && $patentData['success']) {
            // Get key elements for this analysis
            $keyElements = $db->getKeyElements($analysisId);
            
            $elemContexts = [];
            foreach ($keyElements as $elem) {
                if ($elem['context_json'] && $elem['approved']) {
                    $elemContexts[] = json_decode($elem['context_json'], true);
                }
            }
            
            // Build patent text
            $patentText = ($patentData['claims'] ?? '') . "\n" . ($patentData['abstract'] ?? '');
            
            // Check if we have valid patent text and context before screening
            if (!empty($elemContexts) && !empty(trim($patentText)) && strlen(trim($patentText)) > 10) {
                // Pass the patent text directly to avoid DB fetch timing issue
                $screeningResult = self::screenSinglePatent($patentId, $elemContexts, $patentText);
                $result['screening'] = $screeningResult;
            } else {
                $result['screening'] = ['success' => false, 'error' => 'Cannot screen: no patent text or no approved key elements'];
            }
        }
            
            return $result;
        } else {
            $db->updatePatentText($patentId, '', '', 'failed', $patentData['error'] ?? 'Unknown error');
            
            return [
                'success' => false,
                'error' => $patentData['error'] ?? 'Failed to fetch patent data',
                'patent_id' => $patentId
            ];
        }
    }
    
    /**
     * Remove a patent from screening
     * 
     * @param int $patentId The patent ID
     * @return bool Success
     */
    public static function removePatentFromScreening($patentId) {
        $db = Database::getInstance();
        
        // Delete screening results first (if any)
        $stmt = $db->pdo()->prepare("DELETE FROM screening_results WHERE patent_id = ?");
        $stmt->execute([$patentId]);
        
        // Delete the patent
        $stmt = $db->pdo()->prepare("DELETE FROM patents WHERE id = ?");
        return $stmt->execute([$patentId]);
    }
    
    /**
     * Re-screen a single patent
     * 
     * @param int $patentId The patent ID
     * @return array Screening result
     */
    public static function rescreenPatent($patentId) {
        $db = Database::getInstance();
        $patent = $db->getPatent($patentId);
        
        if (!$patent) {
            return ['success' => false, 'error' => 'Patent not found'];
        }
        
        // Delete existing screening result
        $stmt = $db->pdo()->prepare("DELETE FROM screening_results WHERE patent_id = ?");
        $stmt->execute([$patentId]);
        
        // Get the analysis ID
        $analysisId = $patent['analysis_id'];
        
        // Get key elements
        $keyElements = $db->getKeyElements($analysisId);
        
        $elemContexts = [];
        foreach ($keyElements as $elem) {
            if ($elem['context_json'] && $elem['approved']) {
                $elemContexts[] = json_decode($elem['context_json'], true);
            }
        }
        
        if (empty($elemContexts)) {
            return ['success' => false, 'error' => 'No approved key elements with context'];
        }
        
        // Re-screen the patent
        return self::screenSinglePatent($patentId, $elemContexts);
    }
}
?>

