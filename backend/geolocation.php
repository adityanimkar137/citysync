<?php
/**
 * CitySync - Geolocation & City Detection Module
 * Validates location and detects city from address/GPS
 */

// Nagpur city boundaries (approximate)
// Center: 21.1458°N, 79.0882°E
define('NAGPUR_LATITUDE_CENTER', 21.1458);
define('NAGPUR_LONGITUDE_CENTER', 79.0882);
define('NAGPUR_RADIUS_KM', 25); // ~25km radius from city center

/**
 * Check if coordinates are within Nagpur city
 * @param float $latitude
 * @param float $longitude
 * @return bool
 */
function isWithinNagpur($latitude, $longitude) {
    if ($latitude === null || $longitude === null) {
        return false;
    }

    $lat1 = deg2rad(NAGPUR_LATITUDE_CENTER);
    $lon1 = deg2rad(NAGPUR_LONGITUDE_CENTER);
    $lat2 = deg2rad($latitude);
    $lon2 = deg2rad($longitude);
    
    $dlat = $lat2 - $lat1;
    $dlon = $lon2 - $lon1;
    $a = sin($dlat/2) * sin($dlat/2) + 
         cos($lat1) * cos($lat2) * sin($dlon/2) * sin($dlon/2);
    $c = 2 * asin(sqrt($a));
    $distanceKm = 6371 * $c;
    
    return $distanceKm <= NAGPUR_RADIUS_KM;
}

/**
 * Detect city from location string
 * @param string $locationString
 * @return string|null - city name or null if unable to detect
 */
function detectCityFromLocation($locationString) {
    if (empty($locationString)) {
        return null;
    }

    $location = strtolower($locationString);

    // Common city names and variants
    $cityPatterns = [
        'nagpur' => ['nagpur', 'nagpurmandi'],
        'amravati' => ['amravati', 'amraoti'],
        'wardha' => ['wardha'],
        'yavatmal' => ['yavatmal', 'yawatmal'],
        'pune' => ['pune', 'poona'],
        'mumbai' => ['mumbai', 'bombay'],
        'aurangabad' => ['aurangabad'],
        'nashik' => ['nashik'],
        'kolhapur' => ['kolhapur'],
        'satara' => ['satara'],
        'delhi' => ['delhi', 'new delhi'],
        'bangalore' => ['bangalore', 'bengaluru'],
        'hyderabad' => ['hyderabad'],
        'chennai' => ['chennai', 'madras'],
        'kolkata' => ['kolkata', 'calcutta'],
    ];

    foreach ($cityPatterns as $city => $patterns) {
        foreach ($patterns as $pattern) {
            if (strpos($location, $pattern) !== false) {
                return ucfirst($city);
            }
        }
    }

    return null;
}

/**
 * Validate location - check if it's within Nagpur or if address mentions Nagpur
 * @param float|null $latitude
 * @param float|null $longitude
 * @param string|null $locationString
 * @return array ['valid' => bool, 'message' => string, 'detectedCity' => string|null]
 */
function validateLocationForNagpur($latitude, $longitude, $locationString) {
    $result = [
        'valid' => true,
        'message' => '',
        'detectedCity' => null,
        'isNagpur' => null
    ];

    // Try to detect city from location string first
    if (!empty($locationString)) {
        $detectedCity = detectCityFromLocation($locationString);
        $result['detectedCity'] = $detectedCity;
        
        if ($detectedCity && strtolower($detectedCity) !== 'nagpur') {
            $result['valid'] = false;
            $result['message'] = "Location '{$locationString}' appears to be in {$detectedCity}, not Nagpur. Please check your address.";
            $result['isNagpur'] = false;
            return $result;
        }
        
        if ($detectedCity === 'Nagpur') {
            $result['isNagpur'] = true;
            return $result; // Valid Nagpur location
        }
    }

    // Check GPS coordinates if available
    if ($latitude !== null && $longitude !== null) {
        if (isWithinNagpur($latitude, $longitude)) {
            $result['isNagpur'] = true;
            $result['message'] = 'Location verified: Within Nagpur city bounds';
            return $result;
        } else {
            $result['valid'] = false;
            $result['message'] = 'GPS location is outside Nagpur city boundaries (~25km radius). Please verify your location.';
            $result['isNagpur'] = false;
            return $result;
        }
    }

    // If no GPS and no location string, show warning
    if (empty($locationString) && ($latitude === null || $longitude === null)) {
        $result['message'] = 'Location not verified. Consider enabling GPS for accurate location.';
    }

    return $result;
}

/**
 * Get exact address from GPS coordinates using reverse geocoding
 * Uses OpenStreetMap's Nominatim API (free, no auth required)
 * @param float $latitude
 * @param float $longitude
 * @return string - address or fallback coordinates
 */
function getAddressFromCoordinates($latitude, $longitude) {
    if ($latitude === null || $longitude === null) {
        return null;
    }

    try {
        $url = sprintf(
            'https://nominatim.openstreetmap.org/reverse?format=json&lat=%f&lon=%f&zoom=18&addressdetails=1',
            $latitude,
            $longitude
        );

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_USERAGENT => 'CitySync/1.0'
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200 && !empty($response)) {
            $data = json_decode($response, true);
            
            if (isset($data['address'])) {
                $addr = $data['address'];
                
                // Build formatted address: Street, Area, City, State, Pincode
                $parts = [];
                
                if (!empty($addr['house_number'])) {
                    $parts[] = $addr['house_number'];
                }
                if (!empty($addr['road'])) {
                    $parts[] = $addr['road'];
                }
                if (!empty($addr['suburb']) && !empty($addr['city'])) {
                    $parts[] = $addr['suburb']; // Area/locality
                }
                if (!empty($addr['city'])) {
                    $parts[] = $addr['city'];
                }
                if (!empty($addr['state'])) {
                    $parts[] = $addr['state'];
                }
                if (!empty($addr['postcode'])) {
                    $parts[] = $addr['postcode'];
                }
                
                if (!empty($parts)) {
                    return implode(', ', $parts);
                }
                
                // Fallback: display whatever is available
                if (!empty($data['display_name'])) {
                    return $data['display_name'];
                }
            }
        }
    } catch (Exception $e) {
        // Silently fail, use fallback
        error_log('Geocoding error: ' . $e->getMessage());
    }

    // Fallback: return coordinates if address lookup fails
    return sprintf('%.4f°N, %.4f°E', $latitude, $longitude);
}

/**
 * Get address from location string (if available) or coordinates
 * Priority: Manual location > Reverse geocoded address > Coordinates
 * @param string|null $manualLocation
 * @param float|null $latitude
 * @param float|null $longitude
 * @return array ['address' => string, 'source' => 'manual'|'geocoded'|'coordinates']
 */
function getCompleteAddress($manualLocation, $latitude, $longitude) {
    // If manual location provided, use it
    if (!empty(trim($manualLocation))) {
        return [
            'address' => trim($manualLocation),
            'source' => 'manual'
        ];
    }

    // If GPS available, try reverse geocoding
    if ($latitude !== null && $longitude !== null) {
        $address = getAddressFromCoordinates($latitude, $longitude);
        return [
            'address' => $address,
            'source' => 'geocoded'
        ];
    }

    // Fallback
    return [
        'address' => 'Location not provided',
        'source' => null
    ];
}
