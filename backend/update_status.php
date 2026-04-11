<?php
/**
 * CitySync - Update Complaint Status API
 * POST /backend/update_status.php
 *
 * Expected POST fields:
 *   complaint_id  (int, required)
 *   status        (string: Pending|In Progress|Resolved, required)
 *   officer_dept  (string, required) - department of the logged-in officer
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

$complaintId = (int)($_POST['complaint_id'] ?? 0);
$newStatus   = trim($_POST['status'] ?? '');
$officerDept = trim($_POST['officer_dept'] ?? '');

// Validate
$validStatuses = ['Pending', 'In Progress', 'Resolved'];

if ($complaintId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid complaint ID.']);
    exit();
}

if (!in_array($newStatus, $validStatuses)) {
    echo json_encode(['success' => false, 'message' => 'Invalid status value.']);
    exit();
}

if (empty($officerDept)) {
    echo json_encode(['success' => false, 'message' => 'Officer department is required.']);
    exit();
}

$db = getDB();

// Fetch the complaint
$stmt = $db->prepare("SELECT id, department, status FROM complaints WHERE id = ?");
$stmt->execute([$complaintId]);
$complaint = $stmt->fetch();

if (!$complaint) {
    echo json_encode(['success' => false, 'message' => 'Complaint not found.']);
    exit();
}

// Check if officer belongs to the same department
if ($complaint['department'] !== $officerDept) {
    echo json_encode([
        'success' => false,
        'message' => 'You are not authorized to update complaints for another department.'
    ]);
    exit();
}

// Update status
$update = $db->prepare("UPDATE complaints SET status = ? WHERE id = ?");
$update->execute([$newStatus, $complaintId]);

echo json_encode([
    'success'      => true,
    'message'      => 'Status updated to "' . $newStatus . '" successfully.',
    'complaint_id' => $complaintId,
    'new_status'   => $newStatus,
]);
