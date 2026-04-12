<?php
/**
 * Image Validation Module
 * Validates that complaint description contains relevant problem keywords
 * No external API calls - pure keyword-based validation
 */

/**
 * Validate complaint image against description
 * Returns YES if valid, NO otherwise
 * Very lenient - accepts almost any image with a reasonably detailed complaint
 */
function validateComplaintImage($imagePath, $description) {
    // Accept image if:
    // 1. Description is at least 20 characters, OR
    // 2. Description contains any problem-related keyword
    
    if (empty(trim($description))) {
        return 'NO'; // Reject only if description is completely empty
    }

    $text = mb_strtolower($description);

    // Keywords to accept (very broad)
    $keywords = [
        'road', 'pothole', 'gaddha', 'sadak', 'garbage', 'waste', 'water', 'pani',
        'electricity', 'light', 'problem', 'issue', 'broken', 'damaged', 'repair',
        'pipe', 'leak', 'wire', 'signal', 'drain', 'flood', 'trash', 'dirty',
        'help', 'fix', 'damage', 'hazard', 'unsafe', 'broken', 'kachara'
    ];

    // If description has any keyword, accept it
    foreach ($keywords as $kw) {
        if (str_contains($text, $kw)) {
            return 'YES';
        }
    }

    // If description is reasonably detailed (30+ chars), accept it anyway
    // (User made effort to describe, trust them)
    if (mb_strlen($description) >= 30) {
        return 'YES';
    }

    // Only reject if very vague and too short
    return 'NO';
}
