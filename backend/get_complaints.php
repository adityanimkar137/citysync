<?php
/**
 * CitySync - Get Complaints API
 * GET /backend/get_complaints.php
 *
 * Query params (all optional):
 *   department  - filter by department name
 *   status      - filter by status (Pending|In Progress|Resolved)
 *   email       - filter by user email (shows only that user's complaints)
 *   limit       - number of results (default 50)
 *   offset      - pagination offset (default 0)
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

$db = getDB();

// Filters
$department = trim($_GET['department'] ?? '');
$status     = trim($_GET['status'] ?? '');
$email      = trim($_GET['email'] ?? '');
$limit      = min((int)($_GET['limit'] ?? 50), 100);
$offset     = max((int)($_GET['offset'] ?? 0), 0);

// Build query
$where  = [];
$params = [];

if (!empty($department)) {
    $where[]  = 'c.department = ?';
    $params[] = $department;
}

if (!empty($status)) {
    $where[]  = 'c.status = ?';
    $params[] = $status;
}

if (!empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $where[]  = 'u.email = ?';
    $params[] = $email;
}

$whereSQL = count($where) > 0 ? 'WHERE ' . implode(' AND ', $where) : '';

$sql = "
    SELECT
        c.id,
        c.description,
        c.image_path,
        c.category,
        c.priority,
        c.department,
        c.status,
        c.location,
        c.is_duplicate,
        c.duplicate_of,
        c.created_at,
        c.updated_at,
        u.anonymous_id
    FROM complaints c
    JOIN users u ON c.user_id = u.id
    {$whereSQL}
    ORDER BY c.created_at DESC
    LIMIT ? OFFSET ?
";

$params[] = $limit;
$params[] = $offset;

$stmt = $db->prepare($sql);
$stmt->execute($params);
$complaints = $stmt->fetchAll();

// Count total (for pagination)
$countSQL    = "SELECT COUNT(*) FROM complaints c JOIN users u ON c.user_id = u.id {$whereSQL}";
$countParams = array_slice($params, 0, -2); // Remove limit/offset
$countStmt   = $db->prepare($countSQL);
$countStmt->execute($countParams);
$total = (int) $countStmt->fetchColumn();

// Stats summary
$statsStmt = $db->query("
    SELECT
        COUNT(*) AS total,
        SUM(status = 'Pending') AS pending,
        SUM(status = 'In Progress') AS in_progress,
        SUM(status = 'Resolved') AS resolved
    FROM complaints
");
$stats = $statsStmt->fetch();

echo json_encode([
    'success'    => true,
    'total'      => $total,
    'complaints' => $complaints,
    'stats'      => [
        'total'       => (int) $stats['total'],
        'pending'     => (int) $stats['pending'],
        'in_progress' => (int) $stats['in_progress'],
        'resolved'    => (int) $stats['resolved'],
    ]
]);
