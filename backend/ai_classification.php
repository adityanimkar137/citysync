<?php
/**
 * CitySync - AI Classification Module
 * Classifies complaint text into category + priority using OpenAI
 */

require_once __DIR__ . '/config.php';

/**
 * Classify a complaint using OpenAI API
 * Supports multilingual input (Hindi, Marathi, Hinglish, English)
 *
 * @param string $complaintText
 * @return array ['category' => '...', 'priority' => '...']
 */
function classifyComplaint(string $complaintText): array {
    // Default fallback
    $default = ['category' => 'Other', 'priority' => 'Medium'];

    if (empty(trim($complaintText))) {
        return $default;
    }

    $prompt = <<<PROMPT
You are a smart municipal complaint classifier for Indian cities. Classify the following complaint text into exactly one category and one priority level.

The complaint may be in English, Hindi, Marathi, or a mix (Hinglish/Manglish). Understand it and classify accurately.

Categories (choose exactly one):
- Road (potholes, damaged roads, footpath issues, traffic signals)
- Garbage (waste collection, dumping, cleanliness, open drains with garbage)
- Water (water supply, leakage, contamination, drainage)
- Electricity (streetlights, power cuts, broken wires, transformers)
- Other (anything else)

Priority (choose exactly one):
- High (urgent safety hazard, affects many people, or health risk)
- Medium (significant inconvenience, should be fixed soon)
- Low (minor issue, can wait)

Complaint text:
"{$complaintText}"

Respond ONLY with a valid JSON object and nothing else:
{"category": "Road|Garbage|Water|Electricity|Other", "priority": "High|Medium|Low"}
PROMPT;

    $payload = json_encode([
        'model'       => OPENAI_MODEL,
        'messages'    => [
            [
                'role'    => 'system',
                'content' => 'You are a precise municipal complaint classifier. Always respond with valid JSON only.'
            ],
            [
                'role'    => 'user',
                'content' => $prompt
            ]
        ],
        'max_tokens'  => 60,
        'temperature' => 0.1,
    ]);

    $ch = curl_init('https://api.openai.com/v1/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . OPENAI_API_KEY,
        ],
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError || $httpCode !== 200) {
        // Fall back to keyword-based classification
        return keywordClassify($complaintText);
    }

    $data = json_decode($response, true);

    if (!isset($data['choices'][0]['message']['content'])) {
        return keywordClassify($complaintText);
    }

    $content = trim($data['choices'][0]['message']['content']);

    // Strip markdown code fences if present
    $content = preg_replace('/```json\s*|\s*```/', '', $content);

    $result = json_decode($content, true);

    if (!$result || !isset($result['category']) || !isset($result['priority'])) {
        return keywordClassify($complaintText);
    }

    // Validate values
    $validCategories = ['Road', 'Garbage', 'Water', 'Electricity', 'Other'];
    $validPriorities = ['High', 'Medium', 'Low'];

    $category = in_array($result['category'], $validCategories) ? $result['category'] : 'Other';
    $priority  = in_array($result['priority'], $validPriorities)  ? $result['priority']  : 'Medium';

    return ['category' => $category, 'priority' => $priority];
}

/**
 * Keyword-based fallback classifier (no API needed)
 * Works with basic English and transliterated Hindi/Marathi keywords
 */
function keywordClassify(string $text): array {
    $text = mb_strtolower($text);

    // Road keywords (English + Hindi transliteration)
    $roadKeywords = ['road', 'pothole', 'khudra', 'sadak', 'rasta', 'footpath', 'pavement',
                     'signal', 'traffic', 'bridge', 'divider', 'gutter', 'manhole', 'khada'];

    // Garbage keywords
    $garbageKeywords = ['garbage', 'waste', 'trash', 'dustbin', 'kachara', 'safai', 'sweeping',
                        'dumping', 'dirty', 'smell', 'stink', 'ganda', 'kuda', 'kuuda'];

    // Water keywords
    $waterKeywords = ['water', 'pani', 'jal', 'pipe', 'leakage', 'leak', 'supply', 'drainage',
                      'drain', 'flood', 'sewer', 'nala', 'borewell', 'contamination', 'dirty water'];

    // Electricity keywords
    $electricityKeywords = ['electricity', 'light', 'bulb', 'streetlight', 'power', 'current',
                             'wire', 'transformer', 'bijli', 'load shedding', 'outage', 'broken wire'];

    // High priority keywords
    $highKeywords = ['urgent', 'danger', 'accident', 'hazard', 'emergency', 'injured', 'death',
                     'hospital', 'fire', 'flooding', 'broken wire', 'exposed wire', 'jaldi'];

    // Count matches
    $scores = ['Road' => 0, 'Garbage' => 0, 'Water' => 0, 'Electricity' => 0, 'Other' => 0];

    foreach ($roadKeywords as $kw)        if (str_contains($text, $kw)) $scores['Road']++;
    foreach ($garbageKeywords as $kw)     if (str_contains($text, $kw)) $scores['Garbage']++;
    foreach ($waterKeywords as $kw)       if (str_contains($text, $kw)) $scores['Water']++;
    foreach ($electricityKeywords as $kw) if (str_contains($text, $kw)) $scores['Electricity']++;

    arsort($scores);
    $category = array_key_first($scores);
    if ($scores[$category] === 0) $category = 'Other';

    // Priority
    $priority = 'Medium';
    foreach ($highKeywords as $kw) {
        if (str_contains($text, $kw)) {
            $priority = 'High';
            break;
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
