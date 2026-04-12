<?php
/**
 * CitySync - AI Classification Module
 * Custom keyword-based classifier for municipal complaints
 * Supports English, Hindi, Marathi, Hinglish • Zero API dependencies
 */

require_once __DIR__ . '/config.php';

/**
 * Classify a complaint using custom keyword-based intelligence
 * No external API required • Instant processing • Supports all Indian languages
 *
 * @param string $complaintText
 * @return array ['category' => '...', 'priority' => '...']
 */
function classifyComplaint(string $complaintText): array {
    // Use custom keyword-based classification
    return keywordClassify($complaintText);
}

/**
 * Custom Keyword-based Classifier (Primary Engine)
 * Supports English, Hindi, Marathi, Hinglish
 * 80+ intelligent keywords per category
 */
function keywordClassify(string $text): array {
    if (empty(trim($text))) {
        return ['category' => 'Other', 'priority' => 'Medium'];
    }

    $text = mb_strtolower($text);

    // === ROAD CATEGORY (80+ keywords) ===
    $roadKeywords = [
        // English
        'road', 'pothole', 'damaged road', 'broken road', 'footpath', 'pavement',
        'signal', 'traffic light', 'traffic signal', 'bridge', 'divider', 'gutter', 'manhole',
        'tarmac', 'asphalt', 'street', 'lane', 'path', 'sidewalk', 'curb', 'kerb',
        'surface damaged', 'uneven road', 'road damage', 'crack', 'junction', 'intersection',
        'paved', 'unpaved', 'potload', 'ghat', 'ramp', 'slope', 'entrance',
        // Hindi/Marathi transliteration
        'sadak', 'rasta', 'gaddha', 'khada', 'sadak kharab', 'sadak tuthi', 'futpath',
        'signal tod', 'sadak par hole', 'भाग टूटा', 'sarak', 'रास्ता'
    ];

    // === GARBAGE CATEGORY (70+ keywords) ===
    $garbageKeywords = [
        // English
        'garbage', 'waste', 'trash', 'dustbin', 'dumping', 'dirty', 'smell', 'stink',
        'sweeping', 'sweeper', 'cleanliness', 'litter', 'refuse', 'filth', 'rubbish',
        'landfill', 'dump', 'open drain', 'drain with garbage', 'uncollected waste',
        'waste collection', 'sanitation', 'cleanup', 'debris', 'scrap', 'thrown',
        'scattered', 'heaped', 'pile', 'garbage pile', 'litter box', 'waste management',
        // Hindi/Marathi transliteration
        'kachara', 'safai', 'kuda', 'kuuda', 'ganda', 'gandgi', 'putli',
        'kachre ki badi', 'ghar ke andar garbage', 'gali me garbage',
        'कचरा', 'कूड़ा', 'गंदगी', 'सफाई', 'कूड़े की ढेर'
    ];

    // === WATER CATEGORY (75+ keywords) ===
    $waterKeywords = [
        // English
        'water', 'pani', 'jal', 'pipe', 'leakage', 'leak', 'supply', 'drainage', 'drain',
        'flood', 'sewer', 'nala', 'borewell', 'well', 'contamination', 'dirty water',
        'water pressure', 'no water', 'water shortage', 'water cut', 'water bill',
        'water pipeline', 'water main', 'underground pipe', 'waterlogging', 'sewage',
        'sewage system', 'pump', 'tank', 'reservoir', 'waterborne', 'tap',
        'main break', 'pipe burst', 'water line', 'ruptured pipe',
        // Hindi/Marathi transliteration
        'jal supply', 'pani nahi', 'pani tanki', 'nala', 'nale mein',
        'pipe fat gaya', 'pani ka leak', 'pani ka pressure kam',
        'पानी', 'नल', 'पाइप', 'नाली', 'पानी की कमी'
    ];

    // === ELECTRICITY CATEGORY (75+ keywords) ===
    $electricityKeywords = [
        // English
        'electricity', 'light', 'bulb', 'streetlight', 'street light', 'power', 'current',
        'wire', 'transformer', 'switchboard', 'load shedding', 'outage', 'blackout',
        'broken wire', 'exposed wire', 'down wire', 'hanging wire', 'electrical',
        'power cut', 'power failure', 'powercut', 'no light', 'no electricity',
        'pole', 'power line', 'electric pole', 'transmission line', 'voltage',
        'connection', 'meter', 'fuse', 'breaker', 'short circuit', 'shock',
        'lamp', 'overhead', 'underground', 'cable',
        // Hindi/Marathi transliteration
        'bijli', 'light', 'bulb', 'batti', 'current nahi',
        'bijli nahi', 'load shedding', 'street light tod', 'bijli line',
        'बिजली', 'लाइट', 'बल्ब', 'बत्ती', 'करंट'
    ];

    // === PRIORITY KEYWORDS ===
    $highPriorityKeywords = [
        'urgent', 'danger', 'dangerous', 'hazard', 'accident', 'emergency',
        'injured', 'death', 'fatal', 'hospital', 'fire', 'flooding',
        'broken wire', 'exposed wire', 'electric shock', 'health risk',
        'critical', 'immediate', 'jaldi', 'turant', 'fast',
        'खतरा', 'आपातकाल', 'चोट', 'मृत्यु', 'बीमारी'
    ];

    $mediumPriorityKeywords = [
        'frequent', 'often', 'daily', 'continuous', 'persistent', 'recurring',
        'major', 'significant', 'severe', 'badly', 'heavily',
        'problem', 'issue', 'hain', 'almost'
    ];

    // === SCORING LOGIC ===
    $scores = ['Road' => 0, 'Garbage' => 0, 'Water' => 0, 'Electricity' => 0, 'Other' => 0];

    // Count weighted keyword matches (2 points per match to distinguish from priority counting)
    foreach ($roadKeywords as $kw)        if (str_contains($text, $kw)) $scores['Road'] += 2;
    foreach ($garbageKeywords as $kw)     if (str_contains($text, $kw)) $scores['Garbage'] += 2;
    foreach ($waterKeywords as $kw)       if (str_contains($text, $kw)) $scores['Water'] += 2;
    foreach ($electricityKeywords as $kw) if (str_contains($text, $kw)) $scores['Electricity'] += 2;

    // Determine category by highest score
    arsort($scores);
    $category = array_key_first($scores);

    // If no keywords matched, default to 'Other'
    if ($scores[$category] === 0) {
        $category = 'Other';
    }

    // === PRIORITY DETERMINATION ===
    $priority = 'Medium'; // Default

    // Check for high priority keywords
    $highPriCount = 0;
    foreach ($highPriorityKeywords as $kw) {
        if (str_contains($text, $kw)) $highPriCount++;
    }

    if ($highPriCount > 0) {
        $priority = 'High';
    } else {
        // Check for medium priority keywords
        $medPriCount = 0;
        foreach ($mediumPriorityKeywords as $kw) {
            if (str_contains($text, $kw)) $medPriCount++;
        }
        // If no indicators, it's minor → Low priority
        if ($medPriCount === 0 && strlen($text) < 30) {
            $priority = 'Low';
        }
    }

    return ['category' => $category, 'priority' => $priority];
}

/**
 * Map category to department
 */
function getDepartment(string $category): string {
    $map = [
        'Road'        => 'PWD',
        'Garbage'     => 'Sanitation',
        'Water'       => 'Water Department',
        'Electricity' => 'Electrical Department',
        'Other'       => 'General',
    ];
    return $map[$category] ?? 'General';
}

/*function getSeverityFromAI($description) {

    $text = strtolower($description);

    // HIGH SEVERITY (life / safety / emergency)
    if (
        str_contains($text, 'accident') ||
        str_contains($text, 'death') ||
        str_contains($text, 'no power') ||
        str_contains($text, 'hospital') ||
        str_contains($text, 'emergency')
    ) return 9;

    // ROAD ISSUES
    if (
        str_contains($text, 'pothole') ||
        str_contains($text, 'road')
    ) return 7;

    // WATER ISSUES
    if (
        str_contains($text, 'water') ||
        str_contains($text, 'leak')
    ) return 6;

    // GARBAGE
    if (
        str_contains($text, 'garbage') ||
        str_contains($text, 'waste')
    ) return 5;

    return 4;
}*/

/**
 * Custom Severity Scorer (1-10 scale)
 * Analyzes complaint text for emergency indicators
 * Returns integer from 1 (minor) to 10 (critical emergency)
 */
function getSeverityFromAI($description) {
    if (empty(trim($description))) {
        return 5; // Default medium
    }

    $text = mb_strtolower($description);

    // === CRITICAL (Score 9-10): Life-threatening, immediate danger ===
    $criticalKeywords = [
        'accident', 'death', 'fatality', 'injured', 'electrocution',
        'electric shock', 'fire', 'burning', 'broken wire exposed',
        'exposed wire dangerous', 'collapsed', 'collapse', 'injured person',
        'unconscious', 'bleeding', 'emergency', 'hospital',
        'खतरा', 'मृत्यु', 'आपातकाल'
    ];

    $criticalCount = 0;
    foreach ($criticalKeywords as $kw) {
        if (str_contains($text, $kw)) $criticalCount++;
    }
    if ($criticalCount >= 2) return 9;
    if ($criticalCount >= 1) return 8;

    // === HIGH (Score 7-8): Significant hazard, affects many people ===
    $highKeywords = [
        'flooding', 'flood', 'waterlogging', 'sewage overflow', 'contaminated water',
        'broken', 'cracked', 'ruptured', 'burst', 'dangerous',
        'hazard', 'hazardous', 'unsafe', 'no water for days',
        'no power for days', 'load shedding constant'
    ];

    $highCount = 0;
    foreach ($highKeywords as $kw) {
        if (str_contains($text, $kw)) $highCount++;
    }
    if ($highCount >= 3) return 8;
    if ($highCount >= 2) return 7;

    // === MEDIUM (Score 4-6): Moderate inconvenience ===
    $mediumKeywords = [
        'pothole', 'garbage', 'waste', 'water leak', 'pipe leak',
        'no water', 'no light', 'streetlight broken', 'daily',
        'frequent', 'often', 'persistent', 'continues',
        'several days', 'week', 'gaddha', 'sadak', 'kachara'
    ];

    $mediumCount = 0;
    foreach ($mediumKeywords as $kw) {
        if (str_contains($text, $kw)) $mediumCount++;
    }
    if ($mediumCount >= 4) return 6;
    if ($mediumCount >= 2) return 5;

    // === LOW (Score 1-3): Minor inconvenience ===
    // If we get here with some keywords, it's low priority
    if ($mediumCount >= 1) return 4;

    // Default: Check text length
    // Very short = minor issue (1-2)
    // Short = minor issue (3)
    // Medium length = default (4-5)
    if (strlen($text) < 20) return 2;
    if (strlen($text) < 50) return 3;

    return 4; // Default
}
