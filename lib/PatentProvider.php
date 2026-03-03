<?php
/**
 * Patent Analysis MVP - Patent Data Provider (Adapter Pattern)
 * 
 * This adapter handles fetching patent data.
 * For MVP, uses mock data; can be replaced with real API calls later.
 */

class PatentProvider {
    
    /**
     * Fetch patent data by number
     * 
     * @param string $patentNumber e.g., "US10123456B2"
     * @param string $scope "claims" or "claims+abstract"
     * @return array ['claims' => '...', 'abstract' => '...', 'meta' => [...]]
     */
    public static function fetchPatent($patentNumber, $scope = 'claims+abstract') {
        if (USE_MOCK_PATENT_API) {
            return self::mockFetchPatent($patentNumber, $scope);
        }
        
        return self::realFetchPatent($patentNumber, $scope);
    }
    
    /**
     * Mock implementation - returns deterministic sample data
     */
    private static function mockFetchPatent($patentNumber, $scope) {
        Logger::logApiRequest('PatentAPI', 'GET', 'mock://patent/' . $patentNumber, ['scope' => $scope]);
        
        $mockClaims = <<<'CLAIMS'
1. A method for patent analysis comprising:
   - receiving a patent document;
   - extracting key elements from the document;
   - comparing with a reference set;
   - generating a relevance score.
   
2. The method of claim 1, wherein the key elements include:
   - claim scope
   - technical field
   - distinguishing features
   
3. A system implementing the method of claim 1, comprising:
   - a document processor
   - an element extractor
   - a comparison engine
   - a scoring module
CLAIMS;
        
        $mockAbstract = <<<'ABSTRACT'
This patent discloses a system and method for analyzing patents with respect to predefined key elements.
The system extracts technical features, identifies scope elements, and provides relevance scoring.
Applications include prior art searching, competitive analysis, and technology landscape assessment.
ABSTRACT;
        
        $result = [
            'patent_number' => $patentNumber,
            'status' => 'ok',
            'claims' => $scope === 'claims' || $scope === 'claims+abstract' ? $mockClaims : '',
            'abstract' => $scope === 'abstract' || $scope === 'claims+abstract' ? $mockAbstract : '',
            'meta' => [
                'title' => 'Patent Analysis System and Method',
                'filing_date' => '2020-01-15',
                'issue_date' => '2022-06-20',
                'assignee' => 'Tech Corp Inc.'
            ]
        ];
        
        Logger::logApiResponse('PatentAPI', 200, $result);
        return $result;
    }
    
    /**
     * Real implementation - calls actual Patent API
     * TODO: Implement when credentials are available
     */
    private static function realFetchPatent($patentNumber, $scope) {
        $url = PATENT_API_BASE_URL . '/' . urlencode($patentNumber);
        $params = ['scope' => $scope];
        
        Logger::logApiRequest('PatentAPI', 'GET', $url, $params);
        
        try {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url . '?scope=' . urlencode($scope));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . PATENT_API_KEY,
                'Content-Type: application/json'
            ]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);
            
            if ($error) {
                Logger::error("PatentAPI curl error: $error");
                return ['status' => 'failed', 'error' => $error];
            }
            
            $data = json_decode($response, true);
            Logger::logApiResponse('PatentAPI', $httpCode, $data);
            
            if ($httpCode !== 200) {
                return ['status' => 'failed', 'error' => 'HTTP ' . $httpCode];
            }
            
            return $data;
        } catch (Exception $e) {
            Logger::error("PatentAPI exception: " . $e->getMessage());
            return ['status' => 'failed', 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Parse CSV of patent numbers
     */
    public static function parsePatentList($csvText) {
        $lines = explode("\n", $csvText);
        $patents = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if (!empty($line)) {
                $patents[] = $line;
            }
        }
        return $patents;
    }
}
