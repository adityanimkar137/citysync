<?php
/**
 * CitySync - Submit Complaint API
 * POST /backend/submit_complaint.php
 *
 * Expected POST fields:
 *   email        (string, required)
 *   description  (string, required)
 *   location     (string, optional)
 *   latitude     (float, optional) - GPS latitude
 *   longitude    (float, optional) - GPS longitude
 *   image        (file, optional)
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/ai_classification.php';
require_once __DIR__ . '/geolocation.php';
require_once __DIR__ . '/image_validation.php';

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

// --- Input Validation ---
$email       = trim($_POST['email'] ?? '');
$description = trim($_POST['description'] ?? '');
$location    = trim($_POST['location'] ?? '');

// GPS coordinates (add this clearly)
$latitude  = isset($_POST['latitude']) && $_POST['latitude'] !== '' 
             ? (float) $_POST['latitude'] 
             : null;

$longitude = isset($_POST['longitude']) && $_POST['longitude'] !== '' 
             ? (float) $_POST['longitude'] 
             : null;

if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'A valid email address is required.']);
    exit();
}

if (empty($description) || mb_strlen($description) < 10) {
    echo json_encode(['success' => false, 'message' => 'Complaint description must be at least 10 characters.']);
    exit();
}

// --- Validate Location (must be within Nagpur or mention Nagpur) ---
$locationValidation = validateLocationForNagpur($latitude, $longitude, $location);

if (!$locationValidation['valid']) {
    echo json_encode([
        'success' => false,
        'message' => $locationValidation['message'],
        'code' => 'LOCATION_OUTSIDE_NAGPUR'
    ]);
    exit();
}

// --- Validate Description doesn't mention other cities ---
// List of cities to check
$otherCities = ['Amravati', 'Pune', 'Mumbai', 'Aurangabad', 'Nasik', 
                'Kolhapur', 'Delhi', 'Bangalore', 'Hyderabad', 'Chennai',
                'Kolkata', 'Ahmedabad', 'Jaipur', 'Lucknow', 'Wardha', 'Yavatmal'];

$descLower = mb_strtolower($description);

foreach ($otherCities as $city) {
    if (strpos($descLower, mb_strtolower($city)) !== false) {
        echo json_encode([
            'success' => false,
            'message' => 'Your complaint mentions "' . $city . '" but the location is set to Nagpur. Please verify your location or update your description.',
            'code' => 'CITY_MISMATCH'
        ]);
        exit();
    }
}

// --- Get or Create User ---
$user = getOrCreateUser($email);

// --- Handle Image Upload ---
$imagePath = null;

if (!empty($_FILES['image']['tmp_name'])) {

    $imageFile = $_FILES['image'];

    $ext     = strtolower(pathinfo($imageFile['name'], PATHINFO_EXTENSION));
    $allowed = ALLOWED_EXTENSIONS;

    if (!in_array($ext, $allowed)) {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid image format. Allowed: jpg, png, gif, webp'
        ]);
        exit();
    }

    if ($imageFile['size'] > MAX_FILE_SIZE) {
        echo json_encode([
            'success' => false,
            'message' => 'Image too large. Max 5MB.'
        ]);
        exit();
    }

    // Cloudinary config
    $cloudName    = CLOUDINARY_CLOUD_NAME;
    $uploadPreset = CLOUDINARY_UPLOAD_PRESET;

    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL, "https://api.cloudinary.com/v1_1/$cloudName/image/upload");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POST, 1);

    $postData = [
        'file' => new CURLFile($imageFile['tmp_name']),
        'upload_preset' => $uploadPreset
    ];

    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);

    $response = curl_exec($ch);

    if (curl_errno($ch)) {
        echo json_encode([
            'success' => false,
            'message' => 'Curl error: ' . curl_error($ch)
        ]);
        exit();
    }

    curl_close($ch);

    $result = json_decode($response, true);

    if (!isset($result['secure_url'])) {
        echo json_encode([
            'success' => false,
            'message' => 'Cloudinary upload failed',
            'error' => $result
        ]);
        exit();
    }

    $imagePath = $result['secure_url'];
}

// IMAGE VALIDATION
if (!empty($_FILES['image']['tmp_name'])) {
    $validation = validateComplaintImage($_FILES['image']['tmp_name'], $description);

    // Only reject if validation explicitly returns NO
    if ($validation === 'NO') {
        echo json_encode([
            "success" => false,
            "message" => "Please provide more detail in your complaint description (at least 30 characters) to match the image.",
            "code" => "IMAGE_VALIDATION_FAILED"
        ]);
        exit;
    }
}

// --- Extract GPS from Image EXIF Data ---
// If image has GPS metadata, use that location instead of current GPS
$exifLatitude  = null;
$exifLongitude = null;

if (!empty($_FILES['image']['tmp_name'])) {
    $exif = @exif_read_data($_FILES['image']['tmp_name']);
    
    if ($exif && isset($exif['GPSInfo'])) {
        $gpsInfo = $exif['GPSInfo'];
        
        // Extract latitude
        if (isset($gpsInfo[2]) && is_array($gpsInfo[2])) {
            $lat_parts = $gpsInfo[2];
            $exifLatitude = $lat_parts[0] + $lat_parts[1]/60 + $lat_parts[2]/3600;
            if (isset($gpsInfo[1]) && $gpsInfo[1] === 'S') {
                $exifLatitude = -$exifLatitude;
            }
        }
        
        // Extract longitude
        if (isset($gpsInfo[4]) && is_array($gpsInfo[4])) {
            $lon_parts = $gpsInfo[4];
            $exifLongitude = $lon_parts[0] + $lon_parts[1]/60 + $lon_parts[2]/3600;
            if (isset($gpsInfo[3]) && $gpsInfo[3] === 'W') {
                $exifLongitude = -$exifLongitude;
            }
        }
    }
}

// --- Use EXIF GPS if available, fallback to current GPS ---
// Priority: Photo's GPS > Current GPS > Manual location
if ($exifLatitude !== null && $exifLongitude !== null) {
    $latitude = $exifLatitude;
    $longitude = $exifLongitude;
}
// If no EXIF GPS but current GPS provided, keep current GPS
// If neither, $latitude and $longitude stay null

$imageHash = null;
if (!empty($_FILES['image']['name'])) {
    // Create a simple hash of the image file for basic similarity detection
    // This is a basic approach; for production, use perceptual hashing (pHash library)
    $imageHash = hash_file('sha256', $_FILES['image']['tmp_name']);
}

// --- Enhanced Duplicate Detection ---
// Detection logic:
// 1. If location (lat/lng) is provided, find complaints within 100m radius
// 2. Check text similarity + image similarity
// 3. Flag as duplicate if text similarity >= 70% AND location proximity < 100m
//    OR if image hash matches exactly (same photo uploaded twice)

$isDuplicate  = 0;
$duplicateOf  = null;

$db = getDB();

// Check for exact image match (same file uploaded twice)
if (!empty($imageHash)) {
    $stmt = $db->prepare("
        SELECT id FROM complaints
        WHERE image_hash = ? AND created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)
        LIMIT 1
    ");
    $stmt->execute([$imageHash]);
    $exactMatch = $stmt->fetch();
    
    if ($exactMatch) {
        $isDuplicate = 1;
        $duplicateOf = $exactMatch['id'];
    }
}

// If not exact match, check by location proximity + text similarity
if (empty($duplicateOf) && ($latitude !== null && $longitude !== null)) {
    // Formula for distance: Haversine formula approximation
    // For small distances (< 1km), we can use simple calculation:
    // Distance ≈ √((lat2-lat1)² + (lng2-lng1)²) * 111km
    
    $maxDistance = 0.0009; // ~100 meters in degrees
    
    $stmt = $db->prepare("
        SELECT id, description, latitude, longitude
        FROM complaints
        WHERE ABS(latitude - ?) < ? 
          AND ABS(longitude - ?) < ?
          AND created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
        ORDER BY created_at DESC
        LIMIT 20
    ");
    $stmt->execute([$latitude, $maxDistance, $longitude, $maxDistance]);
    $nearby = $stmt->fetchAll();

    foreach ($nearby as $existing) {
        // Calculate actual distance (Haversine)
        $lat1 = deg2rad($latitude);
        $lon1 = deg2rad($longitude);
        $lat2 = deg2rad($existing['latitude']);
        $lon2 = deg2rad($existing['longitude']);
        
        $dlat = $lat2 - $lat1;
        $dlon = $lon2 - $lon1;
        $a = sin($dlat/2) * sin($dlat/2) + 
             cos($lat1) * cos($lat2) * sin($dlon/2) * sin($dlon/2);
        $c = 2 * asin(sqrt($a));
        $distanceKm = 6371 * $c; // Earth radius in km
        
        if ($distanceKm < 0.1) { // Within 100 meters
            // Check text similarity
            $similarity = 0;
            similar_text(
                mb_strtolower($description),
                mb_strtolower($existing['description']),
                $similarity
            );

            if ($similarity >= 70) {
                $isDuplicate = 1;
                $duplicateOf = $existing['id'];
                break;
            }
        }
    }
}

// Fallback: Check by manual location string + text similarity (if GPS not available)
if (empty($duplicateOf) && !empty($location)) {
    $stmt = $db->prepare("
        SELECT id, description
        FROM complaints
        WHERE location = ? AND created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
        ORDER BY created_at DESC
        LIMIT 20
    ");
    $stmt->execute([$location]);
    $recent = $stmt->fetchAll();

    foreach ($recent as $existing) {
        $similarity = 0;
        similar_text(
            mb_strtolower($description),
            mb_strtolower($existing['description']),
            $similarity
        );

        if ($similarity >= 70) { // 70% similarity threshold
            $isDuplicate = 1;
            $duplicateOf = $existing['id'];
            break;
        }
    }
}

// --- AI Classification ---
$classification = classifyComplaint($description);
$category       = $classification['category'];
$priority       = $classification['priority'];
$department     = getDepartment($category);

$severity_score = getSeverityFromAI($description);

if ($severity_score === null) {
    $severity_score = 5; // default medium
}

$location_weight = 0;

if ($latitude !== null && $longitude !== null) {

    $stmt = $db->prepare("
        SELECT COUNT(*) as cnt
        FROM complaints
        WHERE ABS(latitude - ?) < 0.01
          AND ABS(longitude - ?) < 0.01
    ");
    $stmt->execute([$latitude, $longitude]);
    $row = $stmt->fetch();

    $location_weight = $row['cnt'] * 0.5;
}
$final_severity = $severity_score + $location_weight;
if ($final_severity >= 8) {
    $priority = "High";
} elseif ($final_severity >= 5) {
    $priority = "Medium";
} else {
    $priority = "Low";
}



// --- Determine city for complaint ---
$city = 'Nagpur'; // Default to Nagpur (already validated above)
if (!empty($locationValidation['detectedCity'])) {
    $city = $locationValidation['detectedCity'];
}

// --- Get Exact Address (reverse geocoding from GPS) ---
$addressData = getCompleteAddress($location, $latitude, $longitude);
$exactAddress = $addressData['address'];
$addressSource = $addressData['source']; // 'manual', 'geocoded', or null

// --- Insert Complaint into DB ---
$db   = getDB();
$stmt = $db->prepare("
    INSERT INTO complaints
        (user_id, description, image_path, category, priority, department, city, status, location, address, latitude, longitude, image_hash, is_duplicate, duplicate_of)
    VALUES
        (?, ?, ?, ?, ?, ?, ?, 'Pending', ?, ?, ?, ?, ?, ?, ?)
");

$stmt->execute([
    $user['id'],
    $description,
    $imagePath,
    $category,
    $priority,
    $department,
    $city,
    $location ?: null,
    $exactAddress,
    $latitude,
    $longitude,
    $imageHash,
    $isDuplicate,
    $duplicateOf
]);

$complaintId = $db->lastInsertId();

// --- Return Response ---
echo json_encode([
    'success'      => true,
    'message'      => $isDuplicate
                        ? 'Complaint submitted (marked as duplicate of #' . $duplicateOf . ')'
                        : 'Complaint submitted successfully!',
    'complaint_id' => $complaintId,
    'anonymous_id' => $user['anonymous_id'],
    'category'     => $category,
    'priority'     => $priority,
    'department'   => $department,
    'is_duplicate' => (bool) $isDuplicate,
    'duplicate_of' => $duplicateOf,
    'location_source' => ($exifLatitude !== null && $exifLongitude !== null)
        ? 'Image EXIF GPS'
        : (($latitude !== null && $longitude !== null)
            ? 'Current GPS'
            : 'Manual Location'),
    'address'      => $exactAddress,
    'address_source' => $addressSource,
    'coordinates'  => [
        'latitude'  => $latitude,
        'longitude' => $longitude
    ]
]);
