<?php
/**
 * CitySync - Submit Complaint API
 * POST /backend/submit_complaint.php
 *
 * Expected POST fields:
 *   email        (string, required)
 *   description  (string, required)
 *   location     (string, optional)
 *   image        (file, optional)
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/ai_classification.php';

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

if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'A valid email address is required.']);
    exit();
}

if (empty($description) || mb_strlen($description) < 10) {
    echo json_encode(['success' => false, 'message' => 'Complaint description must be at least 10 characters.']);
    exit();
}

// --- Get or Create User ---
$user = getOrCreateUser($email);

// --- Handle Image Upload ---
$imagePath = null;

if (!empty($_FILES['image']['name'])) {
    $file      = $_FILES['image'];
    $ext       = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed   = ALLOWED_EXTENSIONS;

    if (!in_array($ext, $allowed)) {
        echo json_encode(['success' => false, 'message' => 'Invalid image format. Allowed: jpg, png, gif, webp']);
        exit();
    }

    if ($file['size'] > MAX_FILE_SIZE) {
        echo json_encode(['success' => false, 'message' => 'Image too large. Max 5MB.']);
        exit();
    }

    $filename  = uniqid('img_', true) . '.' . $ext;
    $destPath  = UPLOAD_DIR . $filename;

    if (move_uploaded_file($file['tmp_name'], $destPath)) {
        $imagePath = 'uploads/' . $filename;
    }
}

// --- Duplicate Detection ---
$isDuplicate  = 0;
$duplicateOf  = null;

if (!empty($location)) {
    $db = getDB();
    // Fetch recent complaints from same location (last 7 days)
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

// --- Insert Complaint into DB ---
$db   = getDB();
$stmt = $db->prepare("
    INSERT INTO complaints
        (user_id, description, image_path, category, priority, department, status, location, is_duplicate, duplicate_of)
    VALUES
        (?, ?, ?, ?, ?, ?, 'Pending', ?, ?, ?)
");

$stmt->execute([
    $user['id'],
    $description,
    $imagePath,
    $category,
    $priority,
    $department,
    $location ?: null,
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
]);
