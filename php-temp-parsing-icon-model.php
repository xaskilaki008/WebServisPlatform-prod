<?php
/**
 * Class for parsing wave data from DWD/ICON API
 * 
 * This class fetches wave height and period data from Open-Meteo API
 * and calculates wave rating and beach safety status according to Russian regulations
 * 
 * @author Your Name
 * @version 1.0
 */

class WaveDataParser {
    
    /**
     * Base URL for Open-Meteo API
     * @var string
     */
    private $apiUrl = 'https://api.open-meteo.com/v1/forecast';
    
    /**
     * Beach coordinates (latitude and longitude)
     * @var array
     */
    private $coordinates;
    
    /**
     * API response data
     * @var array
     */
    private $apiData;
    
    /**
     * Constructor
     * 
     * @param float $latitude Beach latitude
     * @param float $longitude Beach longitude
     */
    public function __construct($latitude, $longitude) {
        $this->coordinates = [
            'latitude' => $latitude,
            'longitude' => $longitude
        ];
    }
    
    /**
     * Fetch data from Open-Meteo API using cURL
     * 
     * This method makes HTTP GET request to retrieve wave parameters
     * 
     * @return bool True if successful, false otherwise
     */
    private function fetchData() {
        // Prepare API parameters
        $params = [
            'latitude' => $this->coordinates['latitude'],
            'longitude' => $this->coordinates['longitude'],
            'hourly' => 'wave_height,wave_period',
            'timezone' => 'auto'
        ];
        
        // Build query string
        $queryString = http_build_query($params);
        $fullUrl = $this->apiUrl . '?' . $queryString;
        
        // Initialize cURL
        $curl = curl_init();
        
        try {
            // Set cURL options
            curl_setopt_array($curl, [
                CURLOPT_URL => $fullUrl,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_USERAGENT => 'WaveDataParser/1.0'
            ]);
            
            // Execute request
            $response = curl_exec($curl);
            $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            
            // Check for cURL errors
            if (curl_error($curl)) {
                throw new Exception('cURL Error: ' . curl_error($curl));
            }
            
            // Check HTTP response code
            if ($httpCode !== 200) {
                throw new Exception('API returned HTTP code: ' . $httpCode);
            }
            
            // Decode JSON response
            $this->apiData = json_decode($response, true);
            
            // Validate response structure
            if (!isset($this->apiData['hourly'])) {
                throw new Exception('Invalid API response format');
            }
            
            return true;
            
        } catch (Exception $e) {
            error_log('Error fetching data: ' . $e->getMessage());
            return false;
        } finally {
            // Close cURL connection
            curl_close($curl);
        }
    }
    
    /**
     * Calculate wave rating based on wave height
     * 
     * Uses standard marine scale for wave rating:
     * 0 - Calm (0.0m)
     * 1 - Very light (0.1-0.5m)
     * 2 - Light (0.6-1.25m)
     * 3 - Moderate (1.25-2.5m)
     * 4 - Strong (2.5-4.0m)
     * 5 - High (4.0-6.0m)
     * 6 - Very high (6.0-9.0m)
     * 7 - Extreme (9.0-14.0m)
     * 8 - Very extreme (>14.0m)
     * 9 - Exceptional (>20.0m)
     * 
     * @param float $waveHeight Wave height in meters
     * @return int Rating from 0 to 9
     */
    private function calculateWaveRating($waveHeight) {
        // Handle negative values
        if ($waveHeight < 0) {
            return 0;
        }
        
        // Apply wave rating scale
        if ($waveHeight <= 0.0) {
            return 0; // Calm
        } elseif ($waveHeight <= 0.5) {
            return 1; // Very light
        } elseif ($waveHeight <= 1.25) {
            return 2; // Light
        } elseif ($waveHeight <= 2.5) {
            return 3; // Moderate
        } elseif ($waveHeight <= 4.0) {
            return 4; // Strong
        } elseif ($waveHeight <= 6.0) {
            return 5; // High
        } elseif ($waveHeight <= 9.0) {
            return 6; // Very high
        } elseif ($waveHeight <= 14.0) {
            return 7; // Extreme
        } elseif ($waveHeight <= 20.0) {
            return 8; // Very extreme
        } else {
            return 9; // Exceptional
        }
    }
    
    /**
     * Determine beach safety status based on wave height
     * 
     * Implementation according to Order No. 732 of MCHS Russia:
     * Green flag: wave height < 1.5m
     * Yellow flag: wave height >= 1.5m and < 2.5m
     * Red flag: wave height >= 2.5m
     * 
     * @param float $waveHeight Wave height in meters
     * @return string Safety status ('green', 'yellow', 'red')
     */
    private function determineSafetyStatus($waveHeight) {
        // Handle negative values
        if ($waveHeight < 0) {
            return 'green'; // Safe for negative values
        }
        
        // Apply safety thresholds according to MCHS Order No. 732
        if ($waveHeight < 1.5) {
            return 'green'; // Safe conditions
        } elseif ($waveHeight < 2.5) {
            return 'yellow'; // Caution needed
        } else {
            return 'red'; // Danger - beach closed
        }
    }
    
    /**
     * Get latest available wave data with calculations
     * 
     * This is the main method that orchestrates data fetching and processing
     * 
     * @return array|null Processed data or null on failure
     */
    public function getWaveData() {
        // Fetch raw data from API
        if (!$this->fetchData()) {
            return null;
        }
        
        // Extract relevant data
        $hourlyData = $this->apiData['hourly'];
        
        // Get latest wave data (first element)
        $latestWaveHeight = $hourlyData['wave_height'][0] ?? null;
        $latestWavePeriod = $hourlyData['wave_period'][0] ?? null;
        
        // Validate required data
        if ($latestWaveHeight === null || $latestWavePeriod === null) {
            error_log('Missing wave data in API response');
            return null;
        }
        
        // Calculate rating and safety status
        $waveRating = $this->calculateWaveRating($latestWaveHeight);
        $safetyStatus = $this->determineSafetyStatus($latestWaveHeight);
        
        // Return structured result
        return [
            'coordinates' => $this->coordinates,
            'wave_data' => [
                'height' => round($latestWaveHeight, 2),
                'period' => round($latestWavePeriod, 2),
                'rating' => $waveRating,
                'safety_status' => $safetyStatus
            ],
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }
}

// Example usage
// Parse data for Sevastopol coast (approximate coordinates)
$parser = new WaveDataParser(44.6099, 33.5215); // Sevastopol coordinates
$result = $parser->getWaveData();

// Output result in JSON format
if ($result !== null) {
    header('Content-Type: application/json');
    echo json_encode($result, JSON_PRETTY_PRINT);
} else {
    // Handle error case
    http_response_code(500);
    echo json_encode(['error' => 'Failed to fetch or process wave data']);
}
?>